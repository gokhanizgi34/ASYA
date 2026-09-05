<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreAgencyRequest;
use App\Http\Requests\UpdateAgencyRequest;
use App\Models\Agency;
use App\Models\RawNewsItem;
use App\RawNewsStatus;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

class AgencyController extends Controller
{
    public function index(Request $request): View
    {
        Gate::authorize('viewAny', Agency::class);

        $query = Agency::query()->withCount('users')->orderBy('name');

        if (! $request->user()->isSystemAdministrator()) {
            $query->whereKey($request->user()->agency_id);
        }

        return view('agencies.index', ['agencies' => $query->paginate(15)]);
    }

    public function create(): View
    {
        Gate::authorize('create', Agency::class);

        return view('agencies.create');
    }

    public function store(StoreAgencyRequest $request): RedirectResponse
    {
        DB::transaction(function () use ($request): void {
            $data = $request->validated();
            if ($request->hasFile('logo')) {
                $data['logo_path'] = $request->file('logo')->store('agency-logos', 'public');
            }
            $data['trial_ends_at'] = today()->addDays(2);
            $agency = Agency::query()->create($data);

            RawNewsItem::query()
                ->where('agency_id', '!=', $agency->id)
                ->where('status', RawNewsStatus::Pending)
                ->where(function ($query): void {
                    $query->where('expires_at', '>', now())
                        ->orWhere(function ($query): void {
                            $query->whereNull('expires_at')->where('created_at', '>', now()->subDays(2));
                        });
                })
                ->orderBy('id')
                ->chunkById(100, function ($items) use ($agency): void {
                    $items->each(function (RawNewsItem $item) use ($agency): void {
                        RawNewsItem::query()->create([
                            'agency_id' => $agency->id,
                            'news_source_id' => null,
                            'external_id' => $item->external_id,
                            'source_name' => $item->source_name,
                            'source_url' => $item->source_url,
                            'original_title' => $item->original_title,
                            'original_body' => $item->original_body,
                            'original_image_url' => $item->original_image_url,
                            'language' => $item->language,
                            'status' => RawNewsStatus::Pending,
                            'priority' => $item->priority,
                            'checksum' => $item->checksum,
                            'discovered_at' => $item->discovered_at,
                            'expires_at' => $item->expires_at ?? $item->created_at?->addDays(2),
                        ]);
                    });
                });
        });

        return redirect()->route('agencies.index')->with('success', 'Ajans başarıyla oluşturuldu.');
    }

    public function edit(Agency $agency): View
    {
        Gate::authorize('update', $agency);

        return view('agencies.edit', ['agency' => $agency]);
    }

    public function update(UpdateAgencyRequest $request, Agency $agency): RedirectResponse
    {
        $data = $request->validated();
        $data['recipe_daily_quota'] ??= $agency->recipe_daily_quota;
        if ($request->hasFile('logo')) {
            if ($agency->logo_path) {
                Storage::disk('public')->delete($agency->logo_path);
            }
            $data['logo_path'] = $request->file('logo')->store('agency-logos', 'public');
        }
        $agency->update($data);

        return redirect()->route('agencies.index')->with('success', 'Ajans bilgileri güncellendi.');
    }
}
