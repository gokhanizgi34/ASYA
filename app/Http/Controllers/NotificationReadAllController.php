<?php

namespace App\Http\Controllers;

use App\Models\SystemNotification;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class NotificationReadAllController extends Controller
{
    public function __invoke(Request $request): RedirectResponse
    {
        Gate::authorize('viewAny', SystemNotification::class);
        $user = $request->user();
        abort_unless($user instanceof User, 401);

        SystemNotification::query()->visibleTo($user)->whereNull('read_at')->update(['read_at' => now(), 'updated_at' => now()]);

        return back()->with('success', 'Tüm bildirimler okundu olarak işaretlendi.');
    }
}
