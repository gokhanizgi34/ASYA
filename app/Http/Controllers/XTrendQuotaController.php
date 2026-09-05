<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpdateXTrendQuotaRequest;
use App\Models\User;
use App\Services\SystemSettings;
use Illuminate\Http\RedirectResponse;

class XTrendQuotaController extends Controller
{
    public function __invoke(UpdateXTrendQuotaRequest $request, SystemSettings $settings): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user instanceof User, 401);
        $agencyId = $request->validated('agency_id');
        $dailyLimit = (int) $request->validated('daily_limit');

        $settings->save(
            $agencyId,
            ['trends_x_daily_item_limit' => $dailyLimit],
            [],
            $user,
        );

        return redirect()
            ->route('trends.index', array_filter(['provider' => 'x', 'agency_id' => $agencyId]))
            ->with('success', "X gündemi günlük haber kotası {$dailyLimit} olarak güncellendi.");
    }
}
