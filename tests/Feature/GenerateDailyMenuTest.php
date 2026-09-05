<?php

namespace Tests\Feature;

use App\ArticleStatus;
use App\Models\Agency;
use App\Models\Article;
use App\Models\PublishingTarget;
use App\Models\Recipe;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class GenerateDailyMenuTest extends TestCase
{
    use DatabaseMigrations;

    public function test_command_creates_one_daily_menu_article_per_active_agency(): void
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

        $this->assertSame(1, Article::query()->where('agency_id', $agency->id)->count());
        $this->assertStringContainsString('Ana yemek', Article::query()->firstOrFail()->body);
        $this->assertSame(ArticleStatus::Published, Article::query()->firstOrFail()->status);
        $this->assertDatabaseHas('publications', ['article_id' => Article::query()->firstOrFail()->id]);
    }
}
