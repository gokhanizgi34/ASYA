<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreAgencyMailSettingRequest;
use App\Models\AgencyMailSetting;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;

class AgencyMailSettingController extends Controller
{
    public function store(StoreAgencyMailSettingRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $agencyId = $data['agency_id'] ?? null;
        $setting = AgencyMailSetting::query()->where('agency_id', $agencyId)->first() ?? new AgencyMailSetting;
        Gate::authorize($setting->exists ? 'update' : 'create', $setting->exists ? $setting : AgencyMailSetting::class);
        if (($data['password'] ?? '') === '') {
            unset($data['password']);
        }
        $setting->fill($data);
        $setting->updated_by = $request->user()->getKey();
        $setting->save();

        return to_route('api-integrations.index')->with('success', 'E-posta entegrasyonu kaydedildi.');
    }
}
