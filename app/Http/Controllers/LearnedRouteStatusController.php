<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpdateLearnedRouteStatusRequest;
use App\Models\LearnedRoute;
use Illuminate\Http\RedirectResponse;

class LearnedRouteStatusController extends Controller
{
    public function __invoke(UpdateLearnedRouteStatusRequest $request, LearnedRoute $learnedRoute): RedirectResponse
    {
        $learnedRoute->update(['is_enabled' => $request->validated('is_enabled')]);

        return back()->with('success', $learnedRoute->is_enabled ? 'Öğrenilen rota etkinleştirildi.' : 'Öğrenilen rota devre dışı bırakıldı.');
    }
}
