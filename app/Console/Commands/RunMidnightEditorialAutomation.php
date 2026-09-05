<?php

namespace App\Console\Commands;

use App\Models\Agency;
use App\Models\EditorialCalendarEvent;
use App\Models\User;
use App\Services\GeneratedContentPublicationService;
use App\Services\HoroscopeDayBuilder;
use App\Services\SpecialDayAiPlanner;
use Carbon\CarbonImmutable;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Throwable;

#[Signature('automation:midnight')]
#[Description('Gece yarısı haber, burç ve özel gün içerik otomasyonunu başlatır')]
class RunMidnightEditorialAutomation extends Command
{
    public function handle(HoroscopeDayBuilder $horoscopeBuilder, SpecialDayAiPlanner $specialDayPlanner, GeneratedContentPublicationService $publisher): int
    {
        Artisan::call('news:import');
        $this->line(Artisan::output());

        $today = CarbonImmutable::today();
        $created = 0;

        Agency::query()->where('is_active', true)->orderBy('id')->each(function (Agency $agency) use ($today, $horoscopeBuilder, $specialDayPlanner, $publisher, &$created): void {
            $user = User::query()->where('agency_id', $agency->id)->where('is_active', true)->orderBy('id')->first();
            if (! $user) {
                return;
            }

            try {
                $horoscopeBuilder->build($agency->id, $today, $user);
                $this->info($agency->name.': günlük burçlar üretildi ve yayın kuyruğuna gönderildi.');
            } catch (Throwable $exception) {
                $this->error($agency->name.': burç üretimi başarısız: '.Str::limit($exception->getMessage(), 240));
            }

            try {
                $events = $specialDayPlanner->plan($agency->id, $today->year, 1);
                foreach ($events as $event) {
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
                    $publisher->send($agency->id, $user, [
                        'title' => $record->title.' Ne Zaman? Tarihi ve Merak Edilenler',
                        'summary' => $record->event_date->format('d.m.Y').' tarihindeki '.$record->title.' için tarih ve öne çıkan bilgiler.',
                        'body' => $record->title.', '.$record->event_date->translatedFormat('d F Y l').' günü gerçekleşecek.'."\n\n".$topics->map(fn (string $topic): string => '## '.$topic)->implode("\n\n"),
                        'keywords' => $topics->take(10)->all(),
                        'hashtags' => ['#ÖzelGün', '#'.$record->event_date->format('Y')],
                        'category' => 'Özel Günler',
                        'source_type' => 'special_day',
                        'source_id' => $record->id,
                        'slug' => Str::slug($record->title).'-'.$record->event_date->format('Y-m-d'),
                        'destination' => 'publish',
                    ]);
                    $created++;
                }
                $this->info($agency->name.': özel gün takvimi ve yayınlar işlendi.');
            } catch (Throwable $exception) {
                $this->error($agency->name.': özel gün üretimi başarısız: '.Str::limit($exception->getMessage(), 240));
            }
        });

        $this->info($created.' özel gün içeriği yayın kuyruğuna gönderildi.');

        return self::SUCCESS;
    }
}
