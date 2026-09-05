<?php

namespace App\Http\Controllers;

use App\Http\Requests\GenerateEditorialCalendarRequest;
use App\Models\EditorialCalendarEvent;
use App\Models\User;
use App\Services\GeneratedContentPublicationService;
use App\Services\SpecialDayAiPlanner;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Throwable;

class EditorialCalendarGenerationController extends Controller
{
    public function __invoke(GenerateEditorialCalendarRequest $request, SpecialDayAiPlanner $planner, GeneratedContentPublicationService $publisher): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user instanceof User, 401);
        $data = $request->validated();
        if (! $user->isSystemAdministrator() && $user->agency_id !== (int) $data['agency_id']) {
            throw ValidationException::withMessages(['agency_id' => 'Yalnızca kendi ajansınız için takvim oluşturabilirsiniz.']);
        }

        try {
            $events = $planner->plan((int) $data['agency_id'], (int) $data['start_year'], (int) $data['years']);
        } catch (Throwable $exception) {
            return back()->withErrors(['editorial_calendar' => $exception->getMessage()])->withInput();
        }

        $records = DB::transaction(function () use ($events, $data, $user): array {
            return collect($events)->map(function (array $event) use ($data, $user): EditorialCalendarEvent {
                $eventDate = Carbon::parse($event['event_date'])->startOfDay();
                $record = EditorialCalendarEvent::query()->firstOrNew([
                    'agency_id' => (int) $data['agency_id'],
                    'event_date' => $eventDate,
                    'title' => $event['title'],
                ]);
                $record->fill([
                    'created_by' => $user->id, 'content_due_at' => Carbon::parse($event['content_due_at'])->startOfDay(), 'seo_topics' => $event['seo_topics'],
                    'status' => 'planned', 'ai_provider' => $event['ai_provider'],
                ])->save();

                return $record;
            })->all();
        }, 5);

        foreach ($records as $record) {
            $topics = collect($record->seo_topics)->filter()->values();
            $publisher->send($record->agency_id, $user, [
                'title' => $record->title.' Ne Zaman? Tarihi ve Merak Edilenler',
                'summary' => $record->event_date->format('d.m.Y').' tarihindeki '.$record->title.' için tarih ve öne çıkan başlıklar.',
                'body' => $record->title.', '.$record->event_date->translatedFormat('d F Y l').' günü gerçekleşecek.'."\n\n".$topics->map(fn (string $topic): string => '## '.$topic)->implode("\n\n"),
                'keywords' => $topics->take(10)->all(), 'hashtags' => ['#ÖzelGün', '#'.$record->event_date->format('Y')],
                'category' => 'Özel Günler', 'source_type' => 'special_day', 'source_id' => $record->id,
                'slug' => str($record->title)->slug().'-'.$record->event_date->format('Y-m-d'), 'destination' => 'publish',
            ]);
        }

        return redirect()->route('schedules.index')->with('success', count($records).' özel gün takvime ve Yayın Merkezi’ne eklendi.');
    }
}
