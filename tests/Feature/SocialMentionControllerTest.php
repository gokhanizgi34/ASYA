<?php

namespace Tests\Feature;

use App\Models\Agency;
use App\Models\SocialListeningWatch;
use App\Models\SocialMention;
use App\Models\User;
use App\SocialMentionStatus;
use App\SocialSentiment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SocialMentionControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_matching_mention_is_analyzed_for_sentiment_and_urgency(): void
    {
        $agency = Agency::factory()->create();
        $editor = User::factory()->editor()->for($agency)->create();
        $watch = SocialListeningWatch::factory()->for($agency)->create([
            'keywords' => ['ASYA', 'belediye'],
            'platforms' => ['x'],
        ]);

        $this->actingAs($editor)->post(route('social-mentions.store'), $this->payload($watch, [
            'content' => 'ASYA hakkında acil şikayet: belediye haberindeki bilgi yanlış ve bu sorun hızla çözülmeli.',
            'engagement_count' => 2500,
        ]))->assertRedirect();

        $mention = SocialMention::query()->sole();
        $this->assertSame(SocialSentiment::Negative, $mention->sentiment);
        $this->assertGreaterThanOrEqual(70, $mention->urgency_score);
        $this->assertSame(['ASYA', 'belediye'], $mention->matched_keywords);
        $this->assertSame($agency->id, $mention->agency_id);
    }

    public function test_foreign_watch_and_unmatched_or_excluded_content_are_rejected(): void
    {
        $agency = Agency::factory()->create();
        $otherAgency = Agency::factory()->create();
        $editor = User::factory()->editor()->for($agency)->create();
        $foreignWatch = SocialListeningWatch::factory()->for($otherAgency)->create(['platforms' => ['x']]);
        $ownWatch = SocialListeningWatch::factory()->for($agency)->create([
            'keywords' => ['ASYA'],
            'excluded_terms' => ['çekiliş'],
            'platforms' => ['x'],
        ]);

        $this->actingAs($editor)->post(route('social-mentions.store'), $this->payload($foreignWatch))
            ->assertSessionHasErrors('social_listening_watch_id');

        $this->actingAs($editor)->post(route('social-mentions.store'), $this->payload($ownWatch, [
            'content' => 'ASYA adına yapılan çekiliş duyurusu ve reklam içeriği burada yer alıyor.',
        ]))->assertSessionHasErrors('content');

        $this->assertDatabaseCount('social_mentions', 0);
    }

    public function test_duplicate_external_id_is_rejected_and_status_is_tenant_protected(): void
    {
        $agency = Agency::factory()->create();
        $otherAgency = Agency::factory()->create();
        $editor = User::factory()->editor()->for($agency)->create();
        $otherEditor = User::factory()->editor()->for($otherAgency)->create();
        $watch = SocialListeningWatch::factory()->for($agency)->create(['platforms' => ['x']]);
        $mention = SocialMention::factory()->for($agency)->for($watch, 'watch')->create([
            'platform' => 'x',
            'external_id' => 'post-42',
        ]);

        $this->actingAs($editor)->post(route('social-mentions.store'), $this->payload($watch, ['external_id' => 'post-42']))
            ->assertSessionHasErrors('external_id');

        $this->actingAs($otherEditor)->patch(route('social-mentions.update', $mention), [
            'status' => SocialMentionStatus::Resolved->value,
        ])->assertForbidden();

        $this->actingAs($editor)->patch(route('social-mentions.update', $mention), [
            'status' => SocialMentionStatus::Reviewing->value,
        ])->assertRedirect();

        $this->assertSame(SocialMentionStatus::Reviewing, $mention->fresh()->status);
    }

    public function test_social_content_is_escaped_in_dashboard(): void
    {
        $agency = Agency::factory()->create();
        $editor = User::factory()->editor()->for($agency)->create();
        $watch = SocialListeningWatch::factory()->for($agency)->create();
        SocialMention::factory()->for($agency)->for($watch, 'watch')->create([
            'content' => '<script>alert(1)</script> ASYA hakkında güvenli test içeriği',
        ]);

        $this->actingAs($editor)->get(route('social-listening.index'))
            ->assertOk()
            ->assertSee('&lt;script&gt;alert(1)&lt;/script&gt;', false)
            ->assertDontSee('<script>alert(1)</script>', false);
    }

    /** @param array<string, mixed> $overrides
     * @return array<string, mixed>
     */
    private function payload(SocialListeningWatch $watch, array $overrides = []): array
    {
        return array_merge([
            'social_listening_watch_id' => $watch->id,
            'platform' => 'x',
            'external_id' => 'post-'.fake()->unique()->numberBetween(1, 999999),
            'author_handle' => '@haberhesabi',
            'url' => 'https://example.com/post/1',
            'title' => 'Sosyal medya paylaşımı',
            'content' => 'ASYA hakkında güvenilir ve iyi hazırlanmış bir haber paylaşımı gördüm.',
            'published_at' => now()->subMinute()->format('Y-m-d H:i:s'),
            'engagement_count' => 25,
        ], $overrides);
    }
}
