<?php

namespace App\Services;

use App\ArticleStatus;
use App\Models\Article;
use App\Models\Publication;
use App\Models\PublishingTarget;
use App\Models\ScheduleEntry;
use App\Models\User;
use App\PublicationStatus;
use App\ScheduleAction;
use App\ScheduleStatus;
use App\SourceTrustStatus;
use App\VisualAssetStatus;
use Carbon\CarbonImmutable;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class PublicationCreator
{
    public function __construct(
        private TaxonomyMapper $taxonomyMapper,
        private DistrictCategoryResolver $districtCategoryResolver,
    ) {}

    /** @param array<string, mixed> $data */
    public function create(array $data, User $user): Publication
    {
        $article = Article::query()->with(['seoAnalysis', 'selectedVisualAsset'])->lockForUpdate()->findOrFail($data['article_id']);
        $target = PublishingTarget::query()->lockForUpdate()->findOrFail($data['publishing_target_id']);

        if ($article->agency_id !== $data['agency_id'] || $target->agency_id !== $data['agency_id'] || ! $target->is_active) {
            throw ValidationException::withMessages(['publishing_target_id' => 'Haber veya hedef artık bu ajans için kullanılamaz.']);
        }

        if ($article->status !== ArticleStatus::Published
            || $article->source_trust_status !== SourceTrustStatus::Verified
            || ! $article->seoAnalysis
            || ! $article->selectedVisualAsset
            || $article->selectedVisualAsset->status !== VisualAssetStatus::Approved
            || blank($article->selectedVisualAsset->storage_path)) {
            throw ValidationException::withMessages(['article_id' => 'Haber artık yayın önkoşullarını karşılamıyor.']);
        }

        if (Publication::query()->where('article_id', $article->id)->where('publishing_target_id', $target->id)->exists()) {
            throw ValidationException::withMessages(['publishing_target_id' => 'Bu haber seçilen hedef için daha önce yayın kuyruğuna alınmıştır.']);
        }

        $taxonomy = $this->taxonomyMapper->resolve($article, $target);
        $categories = $data['remote_category_ids'] ?: $taxonomy['categories'];
        $tags = $data['remote_tag_ids'] ?: $taxonomy['tags'];
        $authorId = $data['remote_author_id'] ?: $target->default_author_id;
        $districtCategory = $this->districtCategoryResolver->resolve($article);
        $categoryNames = collect([
            $article->agency?->category_name,
            $districtCategory,
            (string) data_get($article->editorial_metadata, 'category'),
        ])->filter()->unique(fn (string $category): string => Str::lower($category))->values()->all();

        $publication = Publication::query()->create([
            'agency_id' => $data['agency_id'],
            'article_id' => $article->id,
            'publishing_target_id' => $target->id,
            'created_by' => $user->id,
            'status' => PublicationStatus::Queued,
            'remote_status' => $data['remote_status'],
            'payload' => [
                'title' => $article->seoAnalysis->meta_title ?: $article->title,
                'slug' => $article->slug,
                'content' => $article->body,
                'excerpt' => $article->seoAnalysis->meta_description ?: $article->summary,
                'author' => $authorId,
                'categories' => $categories,
                'tags' => $tags,
                'taxonomy_names' => [
                    'categories' => $categoryNames,
                    'tags' => collect([...($article->seoAnalysis->keywords ?? []), ...($article->seoAnalysis->hashtags ?? [])])
                        ->map(fn (string $term): string => Str::of($term)->replaceStart('#', '')->squish()->limit(100, '')->toString())
                        ->filter()
                        ->unique(fn (string $term): string => Str::lower($term))
                        ->take(12)
                        ->values()
                        ->all(),
                ],
                'meta' => [
                    'asya_focus_keyword' => $article->seoAnalysis->focus_keyword,
                    'asya_keywords' => $article->seoAnalysis->keywords,
                    'asya_hashtags' => $article->seoAnalysis->hashtags,
                    'asya_taxonomy_matches' => $taxonomy['matched_terms'],
                    'asya_district_category' => $districtCategory,
                ],
                'media' => [
                    'disk' => $article->selectedVisualAsset->storage_disk,
                    'path' => $article->selectedVisualAsset->storage_path,
                    'title' => $article->selectedVisualAsset->title,
                    'alt_text' => $article->selectedVisualAsset->alt_text,
                ],
            ],
            'queued_at' => now(),
        ]);

        if (filled($data['scheduled_for'] ?? null)) {
            ScheduleEntry::query()->create([
                'agency_id' => $publication->agency_id,
                'created_by' => $user->id,
                'publication_id' => $publication->id,
                'action' => ScheduleAction::PublishWordPress,
                'status' => ScheduleStatus::Pending,
                'active_key' => 'publication:'.$publication->id,
                'title' => $article->title.' → '.$target->name,
                'scheduled_for' => CarbonImmutable::parse($data['scheduled_for'], $data['schedule_timezone'])->setTimezone(config('app.timezone')),
                'timezone' => $data['schedule_timezone'],
            ]);
        }

        return $publication;
    }
}
