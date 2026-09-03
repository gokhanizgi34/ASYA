<?php

namespace App\Http\Controllers;

use App\ErrorSeverity;
use App\Models\SystemNotification;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class NotificationCenterController extends Controller
{
    public function __invoke(Request $request): View
    {
        Gate::authorize('viewAny', SystemNotification::class);
        $user = $request->user();
        abort_unless($user instanceof User, 401);
        $query = SystemNotification::query()->visibleTo($user);
        $severity = ErrorSeverity::tryFrom((string) $request->query('severity'));

        if ($severity) {
            $query->where('severity', $severity);
        }

        if ($request->boolean('unread')) {
            $query->whereNull('read_at');
        }

        return view('notifications.index', [
            'notifications' => $query->latest('last_occurred_at')->paginate(30)->withQueryString(),
            'severities' => ErrorSeverity::cases(),
            'unreadCount' => SystemNotification::query()->visibleTo($user)->whereNull('read_at')->count(),
        ]);
    }
}
