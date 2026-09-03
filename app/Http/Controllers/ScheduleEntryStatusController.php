<?php

namespace App\Http\Controllers;

use App\Models\ScheduleEntry;
use App\PublicationStatus;
use App\ScheduleAction;
use App\ScheduleStatus;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class ScheduleEntryStatusController extends Controller
{
    public function __invoke(Request $request, ScheduleEntry $schedule): RedirectResponse
    {
        Gate::authorize('update', $schedule);
        $data = $request->validate(['operation' => ['required', Rule::in(['cancel', 'retry'])]]);
        if ($data['operation'] === 'cancel') {
            abort_unless(in_array($schedule->status, [ScheduleStatus::Pending, ScheduleStatus::Failed], true), 422, 'Yalnızca bekleyen veya hatalı plan iptal edilebilir.');
            $schedule->update(['status' => ScheduleStatus::Cancelled, 'active_key' => null, 'completed_at' => now()]);
            if ($schedule->action === ScheduleAction::PublishWordPress && $schedule->publication?->status === PublicationStatus::Queued) {
                $schedule->publication->update(['status' => PublicationStatus::Failed, 'failure_message' => 'Planlanan yayın kullanıcı tarafından iptal edildi.', 'completed_at' => now()]);
            }
        } else {
            abort_unless($schedule->status === ScheduleStatus::Failed, 422, 'Yalnızca hatalı plan yeniden denenebilir.');
            $schedule->update(['status' => ScheduleStatus::Pending, 'active_key' => $schedule->action === ScheduleAction::PublishWordPress ? 'publication:'.$schedule->publication_id : 'campaign:'.$schedule->campaign_id.':'.$schedule->action->value, 'scheduled_for' => now(), 'failure_message' => null, 'started_at' => null, 'completed_at' => null]);
        }

        return back()->with('success', $data['operation'] === 'cancel' ? 'Plan iptal edildi.' : 'Plan yeniden yürütme kuyruğuna alındı.');
    }
}
