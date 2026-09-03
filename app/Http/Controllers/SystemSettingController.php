<?php

namespace App\Http\Controllers;

use App\Http\Requests\SystemSettingScopeRequest;
use App\Http\Requests\UpdateSystemSettingsRequest;
use App\Models\Agency;
use App\Models\SystemSetting;
use App\Models\User;
use App\Services\SystemSettings;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class SystemSettingController extends Controller
{
    public function index(SystemSettingScopeRequest $request, SystemSettings $settings): View
    {
        $user = $request->user();
        abort_unless($user instanceof User, 401);
        $agencyId = $request->validated('agency_id');

        return view('system-settings.index', [
            'agencyId' => $agencyId,
            'agencies' => Agency::query()
                ->when(! $user->isSystemAdministrator(), fn ($query) => $query->whereKey($user->agency_id))
                ->orderBy('name')
                ->get(),
            'groupedSettings' => collect($settings->resolved($agencyId))->groupBy('group'),
            'isSystemScope' => $agencyId === null,
        ]);
    }

    public function update(UpdateSystemSettingsRequest $request, SystemSettings $settings): RedirectResponse
    {
        Gate::authorize('updateAny', SystemSetting::class);
        $data = $request->validated();
        $user = $request->user();
        abort_unless($user instanceof User, 401);

        $settings->save(
            $data['agency_id'] ?? null,
            $data['settings'],
            $data['inherit'] ?? [],
            $user,
        );

        return redirect()
            ->route('system-settings.index', array_filter(['agency_id' => $data['agency_id'] ?? null]))
            ->with('success', 'Sistem ayarları kaydedildi.');
    }
}
