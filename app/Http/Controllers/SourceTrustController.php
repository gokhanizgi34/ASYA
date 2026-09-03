<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreNewsSourceRequest;
use App\Http\Requests\StoreSourceTrustAssessmentRequest;
use App\Http\Requests\UpdateNewsSourceRequest;
use App\Jobs\ImportNewsSource;
use App\Models\Agency;
use App\Models\NewsSource;
use App\Models\SourceTrustAssessment;
use App\Models\User;
use App\Services\SourceTrustScorer;
use App\SourceTrustBand;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class SourceTrustController extends Controller
{
    public function index(Request $request): View
    {
        Gate::authorize('viewAny', NewsSource::class);
        $user = $request->user();
        abort_unless($user instanceof User, 401);

        return view('source-trust.index', [
            'sources' => NewsSource::query()->visibleTo($user)
                ->with(['assessments' => fn ($query) => $query->latest('assessed_at')->limit(5)])
                ->withCount(['rawNewsItems as today_raw_news_count' => fn ($query) => $query->whereDate('created_at', today())])
                ->orderByDesc('latest_score')->get(),
            'recentAssessments' => SourceTrustAssessment::query()->visibleTo($user)->with('source')->latest('assessed_at')->limit(20)->get(),
            'agencies' => Agency::query()->where('is_active', true)->when(! $user->isSystemAdministrator(), fn ($query) => $query->whereKey($user->agency_id))->get(),
        ]);
    }

    public function storeSource(StoreNewsSourceRequest $request): RedirectResponse
    {
        $source = DB::transaction(function () use ($request): NewsSource {
            $source = NewsSource::query()->create([
                ...$request->validated(),
                'created_by' => $request->user()?->id,
                'latest_score' => 100,
                'latest_band' => SourceTrustBand::High,
                'last_assessed_at' => now(),
            ]);

            SourceTrustAssessment::query()->create([
                'agency_id' => $source->agency_id,
                'news_source_id' => $source->id,
                'assessed_by' => $request->user()?->id,
                'identity_transparency' => 100,
                'evidence_quality' => 100,
                'correction_policy' => 100,
                'historical_accuracy' => 100,
                'editorial_independence' => 100,
                'weighted_score' => 100,
                'trust_band' => SourceTrustBand::High,
                'notes' => null,
                'assessed_at' => now(),
            ]);

            return $source;
        }, 3);

        if ($source->is_active) {
            ImportNewsSource::dispatch($source->id)->onQueue('news-ingestion');
        }

        return back()->with('success', 'Haber kaynağı 100 güven puanıyla eklendi; otomatik haber alımı başlatıldı.');
    }

    public function storeAssessment(StoreSourceTrustAssessmentRequest $request, NewsSource $newsSource, SourceTrustScorer $scorer): RedirectResponse
    {
        $score = $request->integer('trust_score');
        $scores = [
            'identity_transparency' => $score,
            'evidence_quality' => $score,
            'correction_policy' => $score,
            'historical_accuracy' => $score,
            'editorial_independence' => $score,
        ];
        $result = $scorer->score($scores);

        DB::transaction(function () use ($request, $newsSource, $scores, $result): void {
            SourceTrustAssessment::query()->create([
                'agency_id' => $newsSource->agency_id,
                'news_source_id' => $newsSource->id,
                'assessed_by' => $request->user()?->id,
                ...$scores,
                'weighted_score' => $result['score'],
                'trust_band' => $result['band'],
                'notes' => null,
                'assessed_at' => now(),
            ]);
            $newsSource->update([
                'latest_score' => $result['score'],
                'latest_band' => $result['band'],
                'last_assessed_at' => now(),
            ]);
        }, 3);

        return back()->with('success', 'Kaynak güven puanı güncellendi.');
    }

    public function update(UpdateNewsSourceRequest $request, NewsSource $newsSource): RedirectResponse
    {
        $validated = $request->validated();
        $shouldRestartImport = $validated['is_active'] && (
            ! $newsSource->is_active
            || $newsSource->feed_url !== $validated['feed_url']
        );

        $newsSource->update($validated);

        if ($shouldRestartImport) {
            ImportNewsSource::dispatch($newsSource->id)->onQueue('news-ingestion');
        }

        return back()->with('success', 'Haber kaynağı güncellendi'.($shouldRestartImport ? '; otomatik alım yeniden başlatıldı.' : '.'));
    }

    public function destroy(NewsSource $newsSource): RedirectResponse
    {
        Gate::authorize('delete', $newsSource);
        $newsSource->delete();

        return back()->with('success', 'Haber kaynağı ve güven geçmişi silindi.');
    }
}
