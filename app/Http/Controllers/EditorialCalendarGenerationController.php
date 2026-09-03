<?php

namespace App\Http\Controllers;

use App\Http\Requests\GenerateEditorialCalendarRequest;
use App\Models\EditorialCalendarEvent;
use App\Models\User;
use App\Services\SpecialDayAiPlanner;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Throwable;

class EditorialCalendarGenerationController extends Controller
{
    public function __invoke(GenerateEditorialCalendarRequest $request, SpecialDayAiPlanner $planner): RedirectResponse
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
        DB::transaction(function () use ($events, $data, $user): void {
            foreach ($events as $event) {
                $record = EditorialCalendarEvent::query()
                    ->where('agency_id', (int) $data['agency_id'])
                    ->whereDate('event_date', $event['event_date'])
                    ->where('title', $event['title'])
                    ->first() ?? new EditorialCalendarEvent;
                $record->fill([
                    'agency_id' => (int) $data['agency_id'],
                    'event_date' => $event['event_date'],
                    'title' => $event['title'],
                    'created_by' => $user->id,
                    'content_due_at' => $event['content_due_at'],
                    'seo_topics' => $event['seo_topics'],
                    'status' => 'planned',
                    'ai_provider' => $event['ai_provider'],
                ])->save();
            }
        }, 3);

        return redirect()->route('schedules.index')->with('success', count($events).' özel gün AI ile içerik takvimine eklendi.');
    }
}
