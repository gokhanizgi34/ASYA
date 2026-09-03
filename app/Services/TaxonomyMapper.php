<?php

namespace App\Services;

use App\Models\Article;
use App\Models\PublishingTarget;
use App\Models\TaxonomyMapping;
use App\TaxonomyType;
use Illuminate\Support\Str;

class TaxonomyMapper
{
    /** @return array{categories: array<int, int>, tags: array<int, int>, matched_terms: array<int, string>} */
    public function resolve(Article $article, PublishingTarget $target): array
    {
        $article->loadMissing('seoAnalysis');
        $terms = collect([
            $article->seoAnalysis?->focus_keyword,
            ...($article->seoAnalysis?->keywords ?? []),
            ...($article->seoAnalysis?->hashtags ?? []),
        ])->filter()->map(fn (string $term): string => $this->normalize($term))->filter()->unique();

        $mappings = TaxonomyMapping::query()
            ->where('agency_id', $article->agency_id)
            ->where('publishing_target_id', $target->id)
            ->where('is_active', true)
            ->whereIn('source_key', $terms)
            ->orderByDesc('priority')
            ->get();

        $categories = $mappings->where('type', TaxonomyType::Category)->pluck('remote_id')->unique()->values()->all();
        $tags = $mappings->where('type', TaxonomyType::Tag)->pluck('remote_id')->unique()->values()->all();

        return [
            'categories' => $categories ?: ($target->default_category_ids ?? []),
            'tags' => $tags ?: ($target->default_tag_ids ?? []),
            'matched_terms' => $mappings->pluck('source_term')->unique()->values()->all(),
        ];
    }

    public function normalize(string $term): string
    {
        return Str::slug(Str::of($term)->replaceStart('#', '')->squish()->toString());
    }
}
