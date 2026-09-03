<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreTaxonomyMappingRequest;
use App\Http\Requests\UpdateTaxonomyMappingRequest;
use App\Models\Agency;
use App\Models\PublishingTarget;
use App\Models\TaxonomyMapping;
use App\Models\User;
use App\TaxonomyType;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class TaxonomyMappingController extends Controller
{
    public function index(Request $request): View
    {
        Gate::authorize('viewAny', TaxonomyMapping::class);
        $user = $request->user();
        abort_unless($user instanceof User, 401);

        return view('taxonomy-mappings.index', [
            'mappings' => TaxonomyMapping::query()->visibleTo($user)->with(['agency', 'publishingTarget'])->orderBy('type')->orderByDesc('priority')->orderBy('source_term')->paginate(50),
        ]);
    }

    public function create(Request $request): View
    {
        Gate::authorize('create', TaxonomyMapping::class);
        $user = $request->user();
        abort_unless($user instanceof User, 401);

        return view('taxonomy-mappings.create', $this->formOptions($user));
    }

    public function store(StoreTaxonomyMappingRequest $request): RedirectResponse
    {
        TaxonomyMapping::query()->create($request->validated());

        return redirect()->route('taxonomy-mappings.index')->with('success', 'Kategori/etiket eşleştirmesi oluşturuldu.');
    }

    public function edit(Request $request, TaxonomyMapping $taxonomyMapping): View
    {
        Gate::authorize('update', $taxonomyMapping);
        $user = $request->user();
        abort_unless($user instanceof User, 401);

        return view('taxonomy-mappings.edit', ['mapping' => $taxonomyMapping, ...$this->formOptions($user)]);
    }

    public function update(UpdateTaxonomyMappingRequest $request, TaxonomyMapping $taxonomyMapping): RedirectResponse
    {
        $taxonomyMapping->update($request->validated());

        return redirect()->route('taxonomy-mappings.index')->with('success', 'Eşleştirme güncellendi.');
    }

    public function destroy(TaxonomyMapping $taxonomyMapping): RedirectResponse
    {
        Gate::authorize('delete', $taxonomyMapping);
        $taxonomyMapping->delete();

        return redirect()->route('taxonomy-mappings.index')->with('success', 'Eşleştirme silindi.');
    }

    /** @return array<string, mixed> */
    private function formOptions(User $user): array
    {
        return [
            'agencies' => Agency::query()->where('is_active', true)->when(! $user->isSystemAdministrator(), fn ($query) => $query->whereKey($user->agency_id))->orderBy('name')->get(),
            'targets' => PublishingTarget::query()->visibleTo($user)->where('is_active', true)->with('agency')->orderBy('name')->get(),
            'types' => TaxonomyType::cases(),
        ];
    }
}
