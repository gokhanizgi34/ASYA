<?php

namespace Tests\Feature;

use App\ArticleStatus;
use App\Jobs\PublishArticleToWordPress;
use App\Models\Agency;
use App\Models\Article;
use App\Models\Publication;
use App\Models\PublishingTarget;
use App\Models\Recipe;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class GenerateDailyMenuTest extends TestCase
{
    use DatabaseMigrations;

    public function test_command_sends_one_daily_menu_article_per_active_agency_to_publication_flow(): void
    {
        Queue::fake();
        $agency = Agency::factory()->create();
        User::factory()->agencyOwner()->for($agency)->create();
        PublishingTarget::factory()->for($agency)->create(['is_active' => true]);
        foreach (['main', 'cold', 'salad', 'dessert'] as $category) {
            Recipe::factory()->create(['category' => $category]);
        }

        $this->artisan('app:generate-daily-menu')->assertSuccessful();
        $this->artisan('app:generate-daily-menu')->assertSuccessful();

        $article = Article::query()->where('agency_id', $agency->id)->firstOrFail();
        $this->assertSame(1, Article::query()->where('agency_id', $agency->id)->count());
        $this->assertSame(ArticleStatus::Published, $article->status);
        $this->assertStringContainsString('Ana yemek', $article->body);
        $this->assertDatabaseHas('seo_analyses', ['article_id' => $article->id]);
        $this->assertDatabaseHas('publications', ['article_id' => $article->id]);
        $this->assertSame(1, Publication::query()->where('article_id', $article->id)->count());
        Queue::assertPushed(PublishArticleToWordPress::class, 1);
    }
}
