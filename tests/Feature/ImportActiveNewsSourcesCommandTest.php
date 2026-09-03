<?php

namespace Tests\Feature;

use App\Jobs\ImportNewsSource;
use App\Models\NewsSource;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class ImportActiveNewsSourcesCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_command_queues_only_active_news_sources(): void
    {
        Queue::fake();
        $active = NewsSource::factory()->create(['is_active' => true]);
        $inactive = NewsSource::factory()->create(['is_active' => false]);

        $this->artisan('news:import')
            ->expectsOutput('1 aktif haber kaynağı Akıllı Alım kuyruğuna gönderildi.')
            ->assertSuccessful();

        Queue::assertPushedOn('news-ingestion', ImportNewsSource::class);
        Queue::assertPushed(fn (ImportNewsSource $job): bool => $job->newsSourceId === $active->id);
        Queue::assertNotPushed(fn (ImportNewsSource $job): bool => $job->newsSourceId === $inactive->id);
    }
}
