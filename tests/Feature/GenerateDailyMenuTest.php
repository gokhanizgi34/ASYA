<?php

namespace Tests\Feature;

use App\ArticleStatus;
use App\IntegrationProvider;
use App\Jobs\PublishArticleToWordPress;
use App\Models\Agency;
use App\Models\ApiIntegration;
use App\Models\Article;
use App\Models\Publication;
use App\Models\PublishingTarget;
use App\Models\Recipe;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class GenerateDailyMenuTest extends TestCase
{
    use DatabaseMigrations;

    public function test_command_sends_one_daily_menu_article_per_active_agency_to_publication_flow(): void
    {
        Queue::fake();
        Storage::fake('public');
        Http::preventStrayRequests();
        Http::fake([
            'https://93.184.216.34/api/*' => Http::response(['hits' => [[
                'tags' => 'food, meal, cooking, kitchen',
                'pageURL' => 'https://pixabay.com/photos/food-42/',
                'largeImageURL' => 'https://93.184.216.34/images/food.png',
                'imageWidth' => 1920,
                'imageHeight' => 1080,
                'likes' => 80,
            ]]]),
            'https://93.184.216.34/images/food.png' => Http::response((string) base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII='), 200, ['Content-Type' => 'image/png']),
        ]);
        $agency = Agency::factory()->create();
        ApiIntegration::factory()->for($agency)->create([
            'provider' => IntegrationProvider::Pixabay,
            'base_url' => 'https://93.184.216.34/api/',
            'credential' => 'pixabay-key',
            'visual_enabled' => true,
            'is_active' => true,
        ]);
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
