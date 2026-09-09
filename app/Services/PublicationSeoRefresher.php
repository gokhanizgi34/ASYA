<?php

namespace App\Services;

use App\Jobs\PublishArticleToWordPress;
use App\Models\Publication;
use App\Models\SeoAnalysis;
use App\PublicationStatus;
use Illuminate\Support\Str;

class PublicationSeoRefresher
{
    public function __construct(
        private readonly SeoContentOptimizer $contentOptimizer,
        private readonly SeoAnalyzer $seoAnalyzer,
    ) {}

    public function refresh(Publication $publication, bool $dispatch = true): void
    {
        $publication->loadMissing(['article.seoAnalysis', 'article.selectedVisualAsset', 'publishingTarget']);
        $focusKeyword = $publication->article->seoAnalysis?->focus_keyword
            ?: data_get($publication->article->editorial_metadata, 'focus_keyword')
            ?: $this->seoAnalyzer->analyze($publication->article)['focus_keyword'];
        $article = $this->contentOptimizer->optimize($publication->article, $focusKeyword);
        $seo = $this->seoAnalyzer->analyze($article, $focusKeyword);

        SeoAnalysis::query()->updateOrCreate(
            ['article_id' => $article->id],
            ['agency_id' => $article->agency_id, ...$seo],
        );

        $payload = $publication->payload;
        $payload['title'] = $article->title;
        $payload['content'] = $article->body;
        $payload['excerpt'] = $seo['meta_description'];
        $payload['meta'] = [
            ...(array) data_get($payload, 'meta', []),
            'asya_focus_keyword' => $seo['focus_keyword'],
            'asya_keywords' => $seo['keywords'],
            'asya_hashtags' => $seo['hashtags'],
            'rank_math_title' => $seo['meta_title'],
            'rank_math_description' => $seo['meta_description'],
            'rank_math_focus_keyword' => $seo['focus_keyword'],
        ];

        if (is_array(data_get($payload, 'media'))) {
            $payload['media']['title'] = $seo['meta_title'];
            $payload['media']['alt_text'] = Str::limit(Str::contains(Str::lower($article->title), Str::lower($seo['focus_keyword']))
                ? $article->title
                : Str::ucfirst($seo['focus_keyword']).' - '.$article->title, 125, '');
        }

        $publication->forceFill([
            'payload' => $payload,
            'status' => $dispatch ? PublicationStatus::Queued : $publication->status,
            'failure_message' => null,
            'queued_at' => $dispatch ? now() : $publication->queued_at,
            'completed_at' => $dispatch ? null : $publication->completed_at,
        ])->save();

        if ($dispatch) {
            PublishArticleToWordPress::dispatch($publication->id)->onQueue('publishing');
        }
    }
}
