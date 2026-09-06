<?php

namespace App\Services;

use App\ArticleStatus;
use App\Jobs\PublishArticleToWordPress;
use App\Models\Article;
use App\Models\ContentBatchItem;
use App\Models\Publication;
use App\Models\PublishingTarget;
use App\Models\SeoAnalysis;
use App\Models\User;
use App\RemotePublicationStatus;
use App\SourceTrustStatus;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

class AutomaticArticlePublisher
{
    public function __construct(
        private readonly SeoAnalyzer $seoAnalyzer,
        private readonly AutomaticArticleVisualManager $visualManager,
        private readonly PublicationCreator $publicationCreator,
        private readonly NewsContentQualityGate $qualityGate,
        private readonly AutomaticSocialPublisher $socialPublisher,
    ) {}

    public function publish(int $articleId): void
    {
        $article = Article::query()->with('author')->findOrFail($articleId);
        $creator = $article->author;

        if (! $creator instanceof User || ! $creator->is_active) {
            throw new RuntimeException('Otomatik yayın için aktif bir haber üreticisi bulunamadı.');
        }

        $rawNewsItem = ContentBatchItem::query()
            ->where('article_id', $article->id)
            ->with('rawNewsItem.newsSource')
            ->first()
            ?->rawNewsItem;

        if (! $rawNewsItem) {
            throw new RuntimeException('Otomatik yayın için habere bağlı doğrulanabilir ham haber kaydı bulunamadı.');
        }

        $this->qualityGate->assertPublishable($article, $rawNewsItem);

        $targets = PublishingTarget::query()
            ->where('agency_id', $article->agency_id)
            ->where('is_active', true)
            ->orderBy('id')
            ->get();

        if ($targets->isEmpty()) {
            throw new RuntimeException('Haberi göndermek için aktif bir WordPress yayın hedefi bulunamadı.');
        }

        $seo = $this->seoAnalyzer->analyze($article, data_get($article->editorial_metadata, 'focus_keyword'));
        $seo['keywords'] = collect([...(array) data_get($article->editorial_metadata, 'keywords', []), ...$seo['keywords']])
            ->filter(fn (mixed $keyword): bool => is_string($keyword) && filled($keyword))
            ->map(fn (string $keyword): string => Str::of(strip_tags($keyword))->squish()->limit(120, '')->toString())
            ->unique(fn (string $keyword): string => Str::lower($keyword))
            ->take(12)
            ->values()
            ->all();
        $seo['hashtags'] = collect([...(array) data_get($article->editorial_metadata, 'hashtags', []), ...$seo['hashtags']])
            ->filter(fn (mixed $hashtag): bool => is_string($hashtag) && filled($hashtag))
            ->map(fn (string $hashtag): string => '#'.Str::studly(Str::of($hashtag)->replaceStart('#', '')->toString()))
            ->filter(fn (string $hashtag): bool => $hashtag !== '#')
            ->unique(fn (string $hashtag): string => Str::lower($hashtag))
            ->take(8)
            ->values()
            ->all();

        SeoAnalysis::query()->updateOrCreate(
            ['article_id' => $article->id],
            ['agency_id' => $article->agency_id, ...$seo],
        );

        $sourceImageUrl = $rawNewsItem->original_image_url;

        $visual = $this->visualManager->ensure(
            $article,
            $sourceImageUrl,
            $rawNewsItem->source_url,
            (bool) $rawNewsItem->newsSource?->allow_insecure_tls,
        );

        if ($visual === null) {
            throw new RuntimeException('Haber yayınlanamadı: kaynakta veya Pixabay sonuçlarında içerikle uyumlu bir görsel bulunamadı.');
        }

        $article->forceFill([
            'status' => ArticleStatus::Published,
            'source_trust_status' => SourceTrustStatus::Verified,
            'published_at' => $article->published_at ?? now(),
            'failure_message' => null,
        ])->save();

        foreach ($targets as $target) {
            $publication = Publication::query()
                ->where('article_id', $article->id)
                ->where('publishing_target_id', $target->id)
                ->first();

            if (! $publication) {
                $publication = DB::transaction(
                    fn (): Publication => $this->publicationCreator->create([
                        'agency_id' => $article->agency_id,
                        'article_id' => $article->id,
                        'publishing_target_id' => $target->id,
                        'remote_status' => RemotePublicationStatus::Publish->value,
                        'remote_author_id' => null,
                        'remote_category_ids' => [],
                        'remote_tag_ids' => [],
                        'scheduled_for' => null,
                        'schedule_timezone' => (string) config('app.timezone'),
                    ], $creator),
                    3,
                );
            }

            if ($publication->canBeDispatched()) {
                PublishArticleToWordPress::dispatch($publication->id)
                    ->onQueue('publishing')
                    ->afterCommit();
            }
        }

        $this->socialPublisher->publish($article);
    }
}
