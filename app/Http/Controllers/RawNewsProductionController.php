<?php

namespace App\Http\Controllers;

use App\Models\RawNewsItem;
use App\Models\User;
use App\RawNewsStatus;
use App\Services\AutomaticNewsPipelineStarter;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Throwable;

class RawNewsProductionController extends Controller
{
    public function __invoke(RawNewsItem $rawNewsItem, AutomaticNewsPipelineStarter $pipeline): RedirectResponse
    {
        Gate::authorize('create', RawNewsItem::class);
        $user = request()->user();
        abort_unless($user instanceof User, 401);

        if (! in_array($rawNewsItem->status, [RawNewsStatus::Pending, RawNewsStatus::Failed], true)) {
            return back()->withErrors(['raw_news' => 'Bu ham haber mevcut durumuyla üretime gönderilemez.']);
        }

        $rawNewsItem->update([
            'status' => RawNewsStatus::Pending,
            'failure_message' => null,
            'processed_at' => null,
        ]);

        try {
            $batch = $pipeline->startForAgency(
                agencyId: $rawNewsItem->agency_id,
                rawNewsItemIds: [$rawNewsItem->id],
                originLabel: 'Ham Haber Havuzu · Tekil üretim',
                preferredCreatorId: $user->id,
                newsSourceId: $rawNewsItem->news_source_id,
            );
        } catch (Throwable $exception) {
            return back()->withErrors(['raw_news' => $exception->getMessage()]);
        }

        if (! $batch) {
            return back()->withErrors(['raw_news' => 'Üretim bandı hazır değil. Aktif AI, prompt ve yayın hedefini kontrol edin.']);
        }

        return back()->with('success', 'Ham haber tekil üretim kuyruğuna gönderildi.');
    }
}
