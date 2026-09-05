<?php

namespace App\Console\Commands;

use App\Models\Agency;
use App\Models\EditorialCalendarEvent;
use App\Models\User;
use App\Services\GeneratedContentPublicationService;
use App\Services\SpecialDayAiPlanner;
use Carbon\CarbonImmutable;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Throwable;

#[Signature('automation:monthly-special-days')]
#[Description('Ayın ilk günü yaklaşan özel günün SEO içeriklerini planlar')]
class RunMonthlySpecialDayAutomation extends Command
{
    public function handle(SpecialDayAiPlanner $planner, GeneratedContentPublicationService $publisher): int
    {
        $today = CarbonImmutable::today();
        $scheduled = 0;

        Agency::query()->where('is_active', true)->orderBy('id')->each(function (Agency $agency) use ($today, $planner, $publisher, &$scheduled): void {
            $user = User::query()->where('agency_id', $agency->id)->where('is_active', true)->orderBy('id')->first();
            if (! $user) {
                return;
            }

            try {
                $event = collect($planner->plan($agency->id, $today->year, 2))
                    ->filter(fn (array $item): bool => CarbonImmutable::parse($item['event_date'])->gte($today))
                    ->sortBy('event_date')
                    ->first();

                if (! $event) {
                    $this->warn($agency->name.': yaklaşan özel gün bulunamadı.');

                    return;
                }

                $record = DB::transaction(function () use ($agency, $user, $event): EditorialCalendarEvent {
                    $record = EditorialCalendarEvent::query()->firstOrNew([
                        'agency_id' => $agency->id,
                        'event_date' => $event['event_date'],
                        'title' => $event['title'],
                    ]);
                    $record->fill([
                        'created_by' => $user->id,
                        'content_due_at' => $event['content_due_at'],
                        'seo_topics' => $event['seo_topics'],
                        'status' => 'planned',
                        'ai_provider' => $event['ai_provider'],
                    ])->save();

                    return $record;
                }, 3);

                $topics = collect($record->seo_topics)->filter()->values();
                $eventDate = CarbonImmutable::parse($record->event_date)->startOfDay();
                $startDate = max(CarbonImmutable::parse($record->content_due_at)->startOfDay(), $today);
                $availableDays = max(1, $startDate->diffInDays($eventDate->subDay()));
                $topicCount = max(1, $topics->count());

                $topics->each(function (string $topic, int $index) use ($agency, $user, $publisher, $record, $eventDate, $startDate, $availableDays, $topicCount, &$scheduled): void {
                    $publishDate = $startDate->addDays((int) floor($index * $availableDays / $topicCount));
                    $publishDate = min($publishDate, $eventDate->subDay());
                    $publisher->send($agency->id, $user, [
                        'title' => $topic,
                        'summary' => $record->title.' hakkında '.$topic.'.',
                        'body' => $topic.'. '.$record->title.', '.$eventDate->translatedFormat('d F Y l').' günü gerçekleşecek. Bu içerikte özel günün tarihçesi, kapsamı ve vatandaşların merak ettiği resmi bilgiler sade biçimde ele alınır.',
                        'keywords' => [$topic, $record->title, 'özel gün'],
                        'hashtags' => ['#ÖzelGün', '#'.$eventDate->format('Y')],
                        'category' => 'Özel Günler',
                        'source_type' => 'special_day',
                        'source_id' => $record->id.'-'.$index,
                        'slug' => Str::slug($record->title).'-'.Str::slug($topic).'-'.$eventDate->format('Y-m-d'),
                        'destination' => 'publish',
                        'scheduled_for' => $publishDate->setTime(9, 0),
                        'schedule_timezone' => (string) config('app.timezone'),
                    ]);
                    $scheduled++;
                });

                $record->update(['status' => 'scheduled']);
                $this->info($agency->name.': '.$record->title.' için '.$topics->count().' SEO içeriği planlandı.');
            } catch (Throwable $exception) {
                $this->error($agency->name.': özel gün otomasyonu başarısız: '.Str::limit($exception->getMessage(), 240));
            }
        });

        $this->info($scheduled.' özel gün SEO içeriği planlandı.');

        return self::SUCCESS;
    }
}
