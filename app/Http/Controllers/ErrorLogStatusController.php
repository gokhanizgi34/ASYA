<?php

namespace App\Http\Controllers;

use App\ErrorLogStatus;
use App\Http\Requests\UpdateErrorLogStatusRequest;
use App\Models\ErrorLog;
use Illuminate\Http\RedirectResponse;

class ErrorLogStatusController extends Controller
{
    public function __invoke(UpdateErrorLogStatusRequest $request, ErrorLog $errorLog): RedirectResponse
    {
        $data = $request->validated();

        if ($data['operation'] === 'reopen') {
            $errorLog->update([
                'status' => ErrorLogStatus::Open,
                'resolved_by_id' => null,
                'resolved_at' => null,
                'resolution_note' => null,
            ]);

            return back()->with('success', 'Hata kaydı yeniden açıldı.');
        }

        $status = $data['operation'] === 'resolve' ? ErrorLogStatus::Resolved : ErrorLogStatus::Ignored;
        $errorLog->update([
            'status' => $status,
            'resolved_by_id' => $request->user()?->getKey(),
            'resolved_at' => now(),
            'resolution_note' => $data['resolution_note'],
        ]);

        return back()->with('success', $status === ErrorLogStatus::Resolved ? 'Hata çözüldü olarak işaretlendi.' : 'Hata yok sayıldı.');
    }
}
