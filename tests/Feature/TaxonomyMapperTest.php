<?php

namespace Tests\Feature;

use App\Models\Agency;
use App\Models\Article;
use App\Models\PublishingTarget;
use App\Models\SeoAnalysis;
use App\Models\TaxonomyMapping;
use App\Services\TaxonomyMapper;
use App\TaxonomyType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TaxonomyMapperTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_matches_normalized_seo_terms_and_separates_categories_from_tags(): void
    {
        $agency = Agency::factory()->create();
        $article = Article::factory()->for($agency)->create();
        SeoAnalysis::factory()->for($article)->for($agency)->create([
            'focus_keyword' => 'Dünya Gündemi',
            'keywords' => ['Ekonomi'],
            'hashtags' => ['#SonDakika'],
        ]);
        $target = PublishingTarget::factory()->for($agency)->create(['default_category_ids' => [1], 'default_tag_ids' => [2]]);
        TaxonomyMapping::factory()->for($agency)->for($target, 'publishingTarget')->create(['type' => TaxonomyType::Category, 'source_term' => 'Dünya Gündemi', 'source_key' => 'dunya-gundemi', 'remote_id' => 30]);
        TaxonomyMapping::factory()->for($agency)->for($target, 'publishingTarget')->create(['type' => TaxonomyType::Tag, 'source_term' => 'SonDakika', 'source_key' => 'sondakika', 'remote_id' => 40]);

        $result = app(TaxonomyMapper::class)->resolve($article, $target);

        $this->assertSame([30], $result['categories']);
        $this->assertSame([40], $result['tags']);
        $this->assertEqualsCanonicalizing(['Dünya Gündemi', 'SonDakika'], $result['matched_terms']);
    }

    public function test_inactive_or_foreign_mappings_are_ignored_and_defaults_are_used(): void
    {
        $agency = Agency::factory()->create();
        $article = Article::factory()->for($agency)->create();
        SeoAnalysis::factory()->for($article)->for($agency)->create(['focus_keyword' => 'Spor', 'keywords' => [], 'hashtags' => []]);
        $target = PublishingTarget::factory()->for($agency)->create(['default_category_ids' => [7], 'default_tag_ids' => [8]]);
        TaxonomyMapping::factory()->for($agency)->for($target, 'publishingTarget')->create(['source_term' => 'Spor', 'source_key' => 'spor', 'remote_id' => 99, 'is_active' => false]);

        $result = app(TaxonomyMapper::class)->resolve($article, $target);

        $this->assertSame([7], $result['categories']);
        $this->assertSame([8], $result['tags']);
        $this->assertSame([], $result['matched_terms']);
    }
}
