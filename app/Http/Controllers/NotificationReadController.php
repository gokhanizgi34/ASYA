<?php

namespace App\Http\Controllers;

use App\Models\SystemNotification;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;

class NotificationReadController extends Controller
{
    public function __invoke(SystemNotification $systemNotification): RedirectResponse
    {
        Gate::authorize('update', $systemNotification);
        $systemNotification->markAsRead();

        return back()->with('success', 'Bildirim okundu olarak işaretlendi.');
    }
}
