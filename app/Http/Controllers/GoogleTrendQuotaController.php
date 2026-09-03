<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpdateGoogleTrendQuotaRequest;
use App\Models\User;
use App\Services\SystemSettings;
use Illuminate\Http\RedirectResponse;

class GoogleTrendQuotaController extends Controller
{
    public function __invoke(UpdateGoogleTrendQuotaRequest $request, SystemSettings $settings): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user instanceof User, 401);
        $agencyId = $request->validated('agency_id');
        $dailyLimit = (int) $request->validated('daily_limit');

        $settings->save(
            $agencyId,
            ['trends_google_daily_item_limit' => $dailyLimit],
            [],
            $user,
        );

        return redirect()
            ->route('trends.index', array_filter(['agency_id' => $agencyId]))
            ->with('success', "Google Trends günlük haber kotası {$dailyLimit} olarak güncellendi.");
    }
}
