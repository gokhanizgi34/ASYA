<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpdateEditorialStyleProfileRequest;
use App\Models\Agency;
use App\Models\EditorialStyleProfile;
use App\Models\User;
use App\Services\EditorialStyleLearner;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class EditorialStyleProfileController extends Controller
{
    public function index(Request $request): View
    {
        Gate::authorize('viewAny', EditorialStyleProfile::class);
        $user = $request->user();
        abort_unless($user instanceof User, 401);

        return view('editorial-style-profiles.index', [
            'profiles' => EditorialStyleProfile::query()->visibleTo($user)->with('agency')->orderBy('agency_id')->get(),
            'agencies' => Agency::query()->where('is_active', true)->when(! $user->isSystemAdministrator(), fn ($query) => $query->whereKey($user->agency_id))->orderBy('name')->get(),
        ]);
    }

    public function update(UpdateEditorialStyleProfileRequest $request, EditorialStyleLearner $learner): RedirectResponse
    {
        $data = $request->validated();
        $profile = EditorialStyleProfile::query()->firstOrNew(['agency_id' => $data['agency_id']]);
        if ($profile->exists) {
            Gate::authorize('update', $profile);
        }
        $profile->fill([
            'created_by' => $request->user()?->id, 'name' => $data['name'], 'sample_text' => ($data['sample_text'] ?? '') ?: null,
            'learned_terms' => $learner->learn((string) ($data['sample_text'] ?? ''), (string) ($data['preferred_terms'] ?? '')),
            'replacements' => $learner->replacements((string) ($data['replacements_text'] ?? '')),
            'forbidden_terms' => $learner->terms((string) ($data['forbidden_terms_text'] ?? '')),
            'daily_quota' => $data['daily_quota'], 'destination' => $data['destination'], 'is_active' => $data['is_active'],
        ])->save();

        return back()->with('success', 'Yazım dili hafızası güncellendi. Yerel motor önce bunu deneyecek, yeterli olmazsa metin AI devreye girecek.');
    }
}
