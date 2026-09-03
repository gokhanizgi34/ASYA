<?php

namespace Tests\Feature;

use App\Models\Agency;
use App\Models\SocialFeedImport;
use App\Models\SocialFeedSource;
use App\Models\SocialListeningWatch;
use App\Models\User;
use App\SocialFeedImportStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class SocialFeedIngestionTest extends TestCase
{
    use RefreshDatabase;

    public function test_editor_creates_source_only_for_own_watch_and_secret_is_encrypted(): void
    {
        $agency = Agency::factory()->create();
        $otherAgency = Agency::factory()->create();
        $editor = User::factory()->editor()->for($agency)->create();
        $watch = SocialListeningWatch::factory()->for($agency)->create(['platforms' => ['x']]);
        $foreignWatch = SocialListeningWatch::factory()->for($otherAgency)->create(['platforms' => ['x']]);

        $this->actingAs($editor)->post(route('social-feed-sources.store'), $this->sourcePayload($foreignWatch))
            ->assertSessionHasErrors('social_listening_watch_id');

        $this->actingAs($editor)->post(route('social-feed-sources.store'), $this->sourcePayload($watch, [
            'auth_secret' => 'token-cok-gizli',
        ]))->assertRedirect();

        $source = SocialFeedSource::query()->sole();
        $this->assertSame($agency->id, $source->agency_id);
        $this->assertSame('token-cok-gizli', $source->auth_secret);
        $this->assertStringNotContainsString('token-cok-gizli', (string) DB::table('social_feed_sources')->value('auth_secret'));
    }

    public function test_json_feed_import_is_mapped_analyzed_and_audited(): void
    {
        $agency = Agency::factory()->create();
        $editor = User::factory()->editor()->for($agency)->create();
        $watch = SocialListeningWatch::factory()->for($agency)->create(['keywords' => ['ASYA'], 'platforms' => ['x']]);
        $source = SocialFeedSource::factory()->for($agency)->for($watch, 'watch')->create(['platform' => 'x']);
        $payload = [
            ['id' => 'x-1', 'text' => 'ASYA hakkında güvenilir ve iyi hazırlanmış haber paylaşımı.', 'author' => '@bir', 'url' => 'https://example.com/1', 'published_at' => now()->subMinute()->toIso8601String(), 'engagement' => 40],
            ['id' => 'x-2', 'text' => 'Anahtar kelime içermeyen sıradan sosyal paylaşım metni.', 'author' => '@iki', 'published_at' => now()->subMinute()->toIso8601String(), 'engagement' => 1],
            ['id' => 'x-3', 'text' => 'kısa'],
        ];

        $this->actingAs($editor)->post(route('social-feed-imports.store', $source), [
            'payload' => json_encode($payload, JSON_THROW_ON_ERROR),
        ])->assertRedirect();

        $run = SocialFeedImport::query()->sole();
        $this->assertSame(SocialFeedImportStatus::Partial, $run->status);
        $this->assertSame(3, $run->received_count);
        $this->assertSame(1, $run->imported_count);
        $this->assertSame(1, $run->skipped_count);
        $this->assertSame(1, $run->failed_count);
        $this->assertDatabaseHas('social_mentions', ['external_id' => 'x-1', 'agency_id' => $agency->id]);
    }

    public function test_import_is_idempotent_and_foreign_source_is_forbidden(): void
    {
        $agency = Agency::factory()->create();
        $otherAgency = Agency::factory()->create();
        $editor = User::factory()->editor()->for($agency)->create();
        $otherEditor = User::factory()->editor()->for($otherAgency)->create();
        $watch = SocialListeningWatch::factory()->for($agency)->create(['keywords' => ['ASYA'], 'platforms' => ['x']]);
        $source = SocialFeedSource::factory()->for($agency)->for($watch, 'watch')->create(['platform' => 'x']);
        $item = [['id' => 'same-1', 'text' => 'ASYA hakkında yeterince uzun sosyal medya paylaşımı.', 'published_at' => now()->subMinute()->toIso8601String(), 'engagement' => 3]];
        $request = ['payload' => json_encode($item, JSON_THROW_ON_ERROR)];

        $this->actingAs($editor)->post(route('social-feed-imports.store', $source), $request)->assertRedirect();
        $this->actingAs($editor)->post(route('social-feed-imports.store', $source), $request)->assertRedirect();
        $this->assertDatabaseCount('social_mentions', 1);
        $this->assertSame(1, SocialFeedImport::query()->latest('id')->firstOrFail()->skipped_count);

        $this->actingAs($otherEditor)->post(route('social-feed-imports.store', $source), $request)->assertForbidden();
    }

    public function test_payload_must_be_a_bounded_json_list(): void
    {
        $agency = Agency::factory()->create();
        $editor = User::factory()->editor()->for($agency)->create();
        $watch = SocialListeningWatch::factory()->for($agency)->create(['platforms' => ['x']]);
        $source = SocialFeedSource::factory()->for($agency)->for($watch, 'watch')->create();

        $this->actingAs($editor)->post(route('social-feed-imports.store', $source), [
            'payload' => '{"id":"not-a-list"}',
        ])->assertSessionHasErrors('payload');

        $this->assertDatabaseCount('social_feed_imports', 0);
        $this->assertDatabaseCount('social_mentions', 0);
    }

    /** @param array<string, mixed> $overrides
     * @return array<string, mixed>
     */
    private function sourcePayload(SocialListeningWatch $watch, array $overrides = []): array
    {
        return array_merge([
            'social_listening_watch_id' => $watch->id,
            'name' => 'X Yerel Akışı',
            'platform' => 'x',
            'endpoint_url' => null,
            'auth_secret' => null,
            'is_active' => '1',
        ], $overrides);
    }
}
