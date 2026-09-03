<?php

namespace App\Http\Controllers;

use App\Http\Requests\LearnedRouteFilterRequest;
use App\HttpMethod;
use App\Models\Agency;
use App\Models\LearnedRoute;
use App\Models\User;
use Illuminate\View\View;

class LearnedRouteController extends Controller
{
    public function __invoke(LearnedRouteFilterRequest $request): View
    {
        $user = $request->user();
        abort_unless($user instanceof User, 401);
        $data = $request->validated();

        $query = LearnedRoute::query()->visibleTo($user)
            ->when($data['agency_id'] ?? null, fn ($builder, $agencyId) => $builder->where('agency_id', $agencyId))
            ->when($data['method'] ?? null, fn ($builder, $method) => $builder->where('method', $method))
            ->when(array_key_exists('enabled', $data) && $data['enabled'] !== null, fn ($builder) => $builder->where('is_enabled', $data['enabled']))
            ->when($data['q'] ?? null, function ($builder, string $search): void {
                $builder->where(function ($nested) use ($search): void {
                    $nested->where('host', 'like', '%'.$search.'%')
                        ->orWhere('path_pattern', 'like', '%'.$search.'%')
                        ->orWhere('purpose', 'like', '%'.$search.'%');
                });
            });

        $summary = (clone $query)->toBase()
            ->selectRaw('COUNT(*) as total')
            ->selectRaw('COALESCE(SUM(successful_count), 0) as successes')
            ->selectRaw('COALESCE(SUM(failed_count), 0) as failures')
            ->selectRaw('COALESCE(AVG(confidence), 0) as confidence')
            ->first();

        return view('learned-routes.index', [
            'learnedRoutes' => (clone $query)->with(['agency', 'publishingTarget'])->orderByDesc('last_observed_at')->paginate(50)->withQueryString(),
            'agencies' => Agency::query()->when(! $user->isSystemAdministrator(), fn ($builder) => $builder->whereKey($user->agency_id))->orderBy('name')->get(),
            'methods' => HttpMethod::cases(),
            'summary' => [
                'total' => (int) $summary->total,
                'successes' => (int) $summary->successes,
                'failures' => (int) $summary->failures,
                'confidence' => round((float) $summary->confidence, 1),
            ],
        ]);
    }
}
