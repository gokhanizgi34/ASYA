<?php

namespace Tests\Feature;

use App\Models\RawNewsItem;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Tests\TestCase;

class NewsRetentionTest extends TestCase
{
    use DatabaseMigrations;

    public function test_expired_news_is_removed_and_current_news_is_kept(): void
    {
        $expired = RawNewsItem::factory()->create(['expires_at' => now()->subSecond()]);
        $current = RawNewsItem::factory()->create(['expires_at' => now()->addDay()]);

        $this->artisan('news:purge-expired')->assertSuccessful();

        $this->assertDatabaseMissing('raw_news_items', ['id' => $expired->id]);
        $this->assertDatabaseHas('raw_news_items', ['id' => $current->id]);
    }
}
