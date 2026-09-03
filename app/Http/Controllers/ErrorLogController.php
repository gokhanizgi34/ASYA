<?php

namespace App\Http\Controllers;

use App\ErrorLogStatus;
use App\ErrorSeverity;
use App\Http\Requests\ErrorLogFilterRequest;
use App\Models\Agency;
use App\Models\ErrorLog;
use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class ErrorLogController extends Controller
{
    public function index(ErrorLogFilterRequest $request): View
    {
        $user = $request->user();
        abort_unless($user instanceof User, 401);
        $data = $request->validated();

        $query = ErrorLog::query()->visibleTo($user)
            ->when($data['agency_id'] ?? null, fn ($builder, $agencyId) => $builder->where('agency_id', $agencyId))
            ->when($data['status'] ?? null, fn ($builder, $status) => $builder->where('status', $status))
            ->when($data['severity'] ?? null, fn ($builder, $severity) => $builder->where('severity', $severity))
            ->when($data['from'] ?? null, fn ($builder, $from) => $builder->whereDate('last_seen_at', '>=', $from))
            ->when($data['to'] ?? null, fn ($builder, $to) => $builder->whereDate('last_seen_at', '<=', $to))
            ->when($data['q'] ?? null, function ($builder, string $search): void {
                $escaped = addcslashes($search, '%_');
                $builder->where(function ($nested) use ($escaped): void {
                    $nested->where('message', 'like', '%'.$escaped.'%')
                        ->orWhere('exception_class', 'like', '%'.$escaped.'%')
                        ->orWhere('path', 'like', '%'.$escaped.'%');
                });
            });

        $summary = (clone $query)->toBase()
            ->selectRaw('COUNT(*) as total')
            ->selectRaw("SUM(CASE WHEN status = 'open' THEN 1 ELSE 0 END) as open_count")
            ->selectRaw("SUM(CASE WHEN severity = 'critical' THEN 1 ELSE 0 END) as critical_count")
            ->selectRaw('COALESCE(SUM(occurrences), 0) as occurrence_count')
            ->first();

        return view('error-logs.index', [
            'errorLogs' => (clone $query)->with(['agency', 'user'])->orderByDesc('last_seen_at')->paginate(50)->withQueryString(),
            'agencies' => Agency::query()->when(! $user->isSystemAdministrator(), fn ($builder) => $builder->whereKey($user->agency_id))->orderBy('name')->get(),
            'statuses' => ErrorLogStatus::cases(),
            'severities' => ErrorSeverity::cases(),
            'summary' => [
                'total' => (int) $summary->total,
                'open' => (int) $summary->open_count,
                'critical' => (int) $summary->critical_count,
                'occurrences' => (int) $summary->occurrence_count,
            ],
        ]);
    }

    public function show(ErrorLog $errorLog): View
    {
        Gate::authorize('view', $errorLog);

        return view('error-logs.show', [
            'errorLog' => $errorLog->load(['agency', 'user', 'resolvedBy']),
        ]);
    }
}
