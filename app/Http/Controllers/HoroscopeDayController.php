<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreHoroscopeDayRequest;
use App\Models\User;
use App\Services\HoroscopeDayBuilder;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use RuntimeException;

class HoroscopeDayController extends Controller
{
    public function __invoke(StoreHoroscopeDayRequest $request, HoroscopeDayBuilder $builder): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user instanceof User, 401);
        $data = $request->validated();
        try {
            $builder->build((int) $data['agency_id'], CarbonImmutable::parse($data['forecast_date']), $user);
        } catch (RuntimeException $exception) {
            return back()->withInput()->withErrors(['agency_id' => $exception->getMessage()]);
        }

        return redirect()->route('horoscopes.index', ['date' => $data['forecast_date']])->with('success', 'On iki burç için AI destekli günlük taslaklar hazırlandı.');
    }
}
