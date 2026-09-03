<?php

namespace App\Http\Controllers;

use App\HoroscopeStatus;
use App\Http\Requests\UpdateHoroscopeForecastRequest;
use App\Models\Agency;
use App\Models\HoroscopeForecast;
use App\Models\User;
use App\ZodiacSign;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class HoroscopeForecastController extends Controller
{
    public function index(Request $request): View
    {
        Gate::authorize('viewAny', HoroscopeForecast::class);
        $user = $request->user();
        abort_unless($user instanceof User, 401);
        $date = $request->date('date')?->toDateString() ?? today()->toDateString();

        return view('horoscopes.index', [
            'date' => $date,
            'forecasts' => HoroscopeForecast::query()->visibleTo($user)->whereDate('forecast_date', $date)->with('agency')->get()->keyBy(fn (HoroscopeForecast $item) => $item->sign->value),
            'signs' => ZodiacSign::cases(),
            'agencies' => Agency::query()->where('is_active', true)->when(! $user->isSystemAdministrator(), fn ($query) => $query->whereKey($user->agency_id))->orderBy('name')->get(),
        ]);
    }

    public function edit(HoroscopeForecast $horoscopeForecast): View
    {
        Gate::authorize('update', $horoscopeForecast);

        return view('horoscopes.edit', ['forecast' => $horoscopeForecast, 'statuses' => HoroscopeStatus::cases()]);
    }

    public function update(UpdateHoroscopeForecastRequest $request, HoroscopeForecast $horoscopeForecast): RedirectResponse
    {
        $data = $request->validated();
        $status = HoroscopeStatus::from($data['status']);
        $horoscopeForecast->update([...$data, 'updated_by' => $request->user()?->id, 'published_at' => $status === HoroscopeStatus::Published ? ($horoscopeForecast->published_at ?? now()) : null]);

        return redirect()->route('horoscopes.index', ['date' => $horoscopeForecast->forecast_date->toDateString()])->with('success', $horoscopeForecast->sign->label().' yorumu güncellendi.');
    }
}
