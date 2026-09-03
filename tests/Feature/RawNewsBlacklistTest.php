<?php

namespace Tests\Feature;

use App\BlacklistAction;
use App\BlacklistRuleType;
use App\Models\Agency;
use App\Models\BlacklistRule;
use App\Models\RawNewsItem;
use App\Models\User;
use App\RawNewsStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RawNewsBlacklistTest extends TestCase
{
    use RefreshDatabase;

    public function test_block_rule_rejects_raw_news_and_records_match(): void
    {
        $agency = Agency::factory()->create();
        $owner = User::factory()->agencyOwner()->for($agency)->create();
        $rule = BlacklistRule::factory()->for($agency)->create([
            'type' => BlacklistRuleType::Domain,
            'pattern' => 'blocked.example',
            'normalized_pattern' => 'blocked.example',
            'action' => BlacklistAction::Block,
        ]);

        $this->actingAs($owner)->post(route('raw-news.store'), $this->payload($agency, [
            'source_url' => 'https://sub.blocked.example/haber',
        ]))->assertSessionHas('success', 'Ham haber kara liste kuralıyla engellendi.');

        $item = RawNewsItem::query()->firstOrFail();
        $this->assertSame(RawNewsStatus::Rejected, $item->status);
        $this->assertStringContainsString('blocked.example', (string) $item->failure_message);
        $this->assertSame(1, $rule->fresh()->hit_count);
    }

    public function test_review_rule_holds_raw_news_for_manual_review(): void
    {
        $agency = Agency::factory()->create();
        $owner = User::factory()->agencyOwner()->for($agency)->create();
        BlacklistRule::factory()->review()->for($agency)->create([
            'type' => BlacklistRuleType::Source,
            'pattern' => 'Kontrol Ajansı',
            'normalized_pattern' => 'kontrol ajansı',
        ]);

        $this->actingAs($owner)->post(route('raw-news.store'), $this->payload($agency, [
            'source_name' => 'KONTROL AJANSI',
        ]))->assertSessionHas('success', 'Ham haber kara liste incelemesine gönderildi.');

        $this->assertSame(RawNewsStatus::Review, RawNewsItem::query()->firstOrFail()->status);
    }

    /** @param array<string, mixed> $overrides
     * @return array<string, mixed>
     */
    private function payload(Agency $agency, array $overrides = []): array
    {
        return array_merge([
            'agency_id' => $agency->id,
            'external_id' => 'blacklist-test-1',
            'source_name' => 'Örnek Ajans',
            'source_url' => 'https://example.com/orijinal-haber',
            'original_title' => 'Kara liste deneme haberi',
            'original_body' => 'Bu işlenmemiş ham haber metni doğrulama sınırını aşacak kadar uzundur.',
            'original_image_url' => null,
            'language' => 'tr',
            'priority' => 70,
        ], $overrides);
    }
}
