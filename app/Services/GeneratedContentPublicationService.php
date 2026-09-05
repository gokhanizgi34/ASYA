<?php

namespace App\Services;

use App\ArticleStatus;
use App\Jobs\PublishArticleToWordPress;
use App\Models\Article;
use App\Models\Publication;
use App\Models\PublishingTarget;
use App\Models\SeoAnalysis;
use App\Models\User;
use App\RemotePublicationStatus;
use App\SourceTrustStatus;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class GeneratedContentPublicationService
{
    public function __construct(
        private readonly PublicationCreator $publicationCreator,
        private readonly AutomaticArticleVisualManager $visualManager,
    ) {}

    /**
     * @param  array{title:string,summary:string,body:string,keywords?:array<int,string>,hashtags?:array<int,string>,category:string,source_type:string,source_id:string|int,slug?:string,destination?:string,scheduled_for?:\DateTimeInterface|string,schedule_timezone?:string,uploaded_image?:UploadedFile}  $content
     */
    public function send(int $agencyId, User $creator, array $content): Article
    {
        $destination = $content['destination'] ?? 'publish';
        $slug = $content['slug'] ?? Str::slug($content['title']).'-'.Str::slug((string) $content['source_id']);
        $article = DB::transaction(function () use ($agencyId, $content, $creator, $destination, $slug): Article {
            $article = Article::query()->withTrashed()->firstOrNew(['agency_id' => $agencyId, 'slug' => Str::limit($slug, 250, '')]);
            if ($article->trashed()) {
                $article->restore();
            }
            $article->fill([
                'author_id' => $creator->id,
                'title' => Str::limit(Str::squish(strip_tags($content['title'])), 250, ''),
                'summary' => Str::limit(Str::squish(strip_tags($content['summary'])), 320, ''),
                'body' => trim($content['body']),
                'editorial_metadata' => [
                    'content_type' => $content['source_type'],
                    'content_source_id' => (string) $content['source_id'],
                    'category' => $content['category'],
                    'keywords' => $content['keywords'] ?? [],
                    'hashtags' => $content['hashtags'] ?? [],
                    'generation_mode' => 'text_only',
                ],
                'status' => $destination === 'publish' ? ArticleStatus::Published : ArticleStatus::Draft,
                'source_trust_status' => SourceTrustStatus::Verified,
                'source_name' => 'ASYA Otomatik İçerik',
                'source_url' => null,
                'published_at' => $destination === 'publish' ? now() : null,
                'failure_message' => null,
            ])->save();

            $keywords = collect($content['keywords'] ?? [])->filter()->take(12)->values()->all();
            SeoAnalysis::query()->updateOrCreate(['article_id' => $article->id], [
                'agency_id' => $agencyId,
                'focus_keyword' => $keywords[0] ?? Str::lower(Str::words($article->title, 4, '')),
                'meta_title' => Str::limit($article->title, 70, ''),
                'meta_description' => Str::limit($article->summary, 160, ''),
                'keywords' => $keywords,
                'hashtags' => collect($content['hashtags'] ?? [])->filter()->take(8)->values()->all(),
                'score' => 90,
                'readability_score' => 90,
                'word_count' => count(preg_split('/\s+/u', strip_tags($article->body)) ?: []),
                'keyword_density' => 0,
                'issues' => [],
                'recommendations' => [],
                'analyzed_at' => now(),
            ]);

            return $article;
        }, 5);

        if ($destination !== 'publish') {
            return $article;
        }

        if (($content['uploaded_image'] ?? null) instanceof UploadedFile) {
            $this->visualManager->importUploadedImage($article, $content['uploaded_image']);
        }

        $this->visualManager->ensure($article);

        PublishingTarget::query()->where('agency_id', $agencyId)->where('is_active', true)->orderBy('id')->each(function (PublishingTarget $target) use ($article, $creator): void {
            $publication = Publication::query()->where('article_id', $article->id)->where('publishing_target_id', $target->id)->first();
            if (! $publication) {
                $publication = DB::transaction(fn (): Publication => $this->publicationCreator->create([
                    'agency_id' => $article->agency_id,
                    'article_id' => $article->id,
                    'publishing_target_id' => $target->id,
                    'remote_status' => RemotePublicationStatus::Publish->value,
                    'remote_author_id' => null,
                    'remote_category_ids' => [],
                    'remote_tag_ids' => [],
                    'scheduled_for' => $content['scheduled_for'] ?? null,
                    'schedule_timezone' => $content['schedule_timezone'] ?? (string) config('app.timezone'),
                ], $creator), 5);
            }
            if (! filled($content['scheduled_for'] ?? null) && $publication->canBeDispatched()) {
                PublishArticleToWordPress::dispatch($publication->id)->onQueue('publishing')->afterCommit();
            }
        });

        return $article;
    }
}
