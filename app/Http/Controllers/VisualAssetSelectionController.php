<?php

namespace App\Http\Controllers;

use App\Models\VisualAsset;
use App\VisualAssetStatus;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class VisualAssetSelectionController extends Controller
{
    public function __invoke(VisualAsset $visualAsset): RedirectResponse
    {
        Gate::authorize('update', $visualAsset);
        abort_if($visualAsset->article_id === null, 422, 'Kapak seçimi için görsel bir habere bağlanmalıdır.');
        abort_unless($visualAsset->status === VisualAssetStatus::Approved, 422, 'Yalnızca onaylı görsel kapak seçilebilir.');

        DB::transaction(function () use ($visualAsset): void {
            VisualAsset::query()->where('article_id', $visualAsset->article_id)->lockForUpdate()->get();
            VisualAsset::query()->where('article_id', $visualAsset->article_id)->update(['is_selected' => false]);
            VisualAsset::query()->whereKey($visualAsset->id)->update(['is_selected' => true]);
        });

        return back()->with('success', 'Görsel haberin aktif kapağı olarak seçildi.');
    }
}
