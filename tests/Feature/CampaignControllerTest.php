<?php

namespace Tests\Feature;

use App\CampaignChannel;
use App\CampaignContentStatus;
use App\CampaignStatus;
use App\Models\Agency;
use App\Models\Article;
use App\Models\Campaign;
use App\Models\CampaignContent;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CampaignControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_is_redirected_from_campaigns(): void
    {
        $this->get(route('campaigns.index'))->assertRedirect(route('login'));
    }

    public function test_agency_user_sees_only_own_campaigns_and_output_is_escaped(): void
    {
        $agency = Agency::factory()->create();
        $otherAgency = Agency::factory()->create();
        $editor = User::factory()->editor()->for($agency)->create();
        $own = Campaign::factory()->for($agency)->for($editor, 'owner')->create(['name' => '<script>alert(1)</script>']);
        $other = Campaign::factory()->for($otherAgency)->create(['name' => 'Başka Kampanya']);

        $this->actingAs($editor)->get(route('campaigns.index'))->assertSee('&lt;script&gt;alert(1)&lt;/script&gt;', false)->assertDontSee('<script>alert(1)</script>', false)->assertDontSee($other->name);
        $this->actingAs($editor)->get(route('campaigns.show', $other))->assertForbidden();
        $this->assertModelExists($own);
    }

    public function test_editor_creates_campaign_for_own_agency_with_normalized_business_data(): void
    {
        $agency = Agency::factory()->create();
        $otherAgency = Agency::factory()->create();
        $editor = User::factory()->editor()->for($agency)->create();

        $response = $this->actingAs($editor)->post(route('campaigns.store'), $this->payload($otherAgency->id));

        $campaign = Campaign::query()->firstOrFail();
        $response->assertRedirect(route('campaigns.show', $campaign));
        $this->assertSame($agency->id, $campaign->agency_id);
        $this->assertSame($editor->id, $campaign->owner_id);
        $this->assertSame(CampaignStatus::Draft, $campaign->status);
        $this->assertSame([CampaignChannel::Website->value, CampaignChannel::Instagram->value], $campaign->channels);
        $this->assertSame(['reach' => 10000, 'clicks' => 500], $campaign->kpis);
    }

    public function test_campaign_rejects_invalid_period_and_other_agency_article(): void
    {
        $agency = Agency::factory()->create();
        $otherAgency = Agency::factory()->create();
        $editor = User::factory()->editor()->for($agency)->create();
        $otherArticle = Article::factory()->for($otherAgency)->create();
        $payload = $this->payload($agency->id, [
            'ends_at' => '2026-08-30 10:00',
            'starts_at' => '2026-09-01 10:00',
            'contents' => [['article_id' => $otherArticle->id, 'channel' => CampaignChannel::Website->value, 'title' => 'İçerik', 'body' => 'Metin']],
        ]);

        $this->actingAs($editor)->post(route('campaigns.store'), $payload)->assertSessionHasErrors(['ends_at', 'contents.0.article_id']);
        $this->assertDatabaseCount('campaigns', 0);
    }

    public function test_content_must_use_campaign_channel_and_same_tenant_article(): void
    {
        $agency = Agency::factory()->create();
        $otherAgency = Agency::factory()->create();
        $editor = User::factory()->editor()->for($agency)->create();
        $campaign = Campaign::factory()->for($agency)->for($editor, 'owner')->create(['channels' => [CampaignChannel::Website->value]]);
        $otherArticle = Article::factory()->for($otherAgency)->create();

        $this->actingAs($editor)->post(route('campaign-contents.store', $campaign), ['article_id' => $otherArticle->id, 'channel' => CampaignChannel::Instagram->value, 'title' => 'Sosyal içerik', 'body' => 'Kampanya metni'])->assertSessionHasErrors(['article_id', 'channel']);
        $this->assertDatabaseCount('campaign_contents', 0);
    }

    public function test_editor_adds_content_but_cannot_approve_it(): void
    {
        $agency = Agency::factory()->create();
        $editor = User::factory()->editor()->for($agency)->create();
        $campaign = Campaign::factory()->for($agency)->for($editor, 'owner')->create();

        $this->actingAs($editor)->post(route('campaign-contents.store', $campaign), ['channel' => CampaignChannel::Website->value, 'title' => 'Web duyurusu', 'body' => 'Duyuru metni'])->assertRedirect();
        $content = CampaignContent::query()->firstOrFail();
        $this->assertSame(CampaignContentStatus::Draft, $content->status);
        $this->actingAs($editor)->patch(route('campaign-contents.status', [$campaign, $content]), ['status' => CampaignContentStatus::Approved->value])->assertForbidden();
    }

    public function test_owner_cannot_schedule_until_each_channel_has_approved_content(): void
    {
        $agency = Agency::factory()->create();
        $owner = User::factory()->agencyOwner()->for($agency)->create();
        $campaign = Campaign::factory()->for($agency)->for($owner, 'owner')->create(['channels' => [CampaignChannel::Website->value, CampaignChannel::Instagram->value]]);
        CampaignContent::factory()->for($campaign)->for($owner, 'creator')->approved()->create(['channel' => CampaignChannel::Website]);

        $this->actingAs($owner)->patch(route('campaigns.status', $campaign), ['status' => CampaignStatus::Scheduled->value])->assertSessionHasErrors('status');
        $this->assertSame(CampaignStatus::Draft, $campaign->fresh()->status);
    }

    public function test_owner_approves_all_channel_content_and_advances_valid_campaign_lifecycle(): void
    {
        $agency = Agency::factory()->create();
        $owner = User::factory()->agencyOwner()->for($agency)->create();
        $campaign = Campaign::factory()->for($agency)->for($owner, 'owner')->create(['channels' => [CampaignChannel::Website->value, CampaignChannel::Instagram->value]]);
        $web = CampaignContent::factory()->for($campaign)->for($owner, 'creator')->create(['channel' => CampaignChannel::Website]);
        $instagram = CampaignContent::factory()->for($campaign)->for($owner, 'creator')->create(['channel' => CampaignChannel::Instagram]);

        foreach ([$web, $instagram] as $content) {
            $this->actingAs($owner)->patch(route('campaign-contents.status', [$campaign, $content]), ['status' => CampaignContentStatus::Approved->value])->assertRedirect();
        }
        $this->actingAs($owner)->patch(route('campaigns.status', $campaign), ['status' => CampaignStatus::Scheduled->value])->assertRedirect();
        $this->actingAs($owner)->patch(route('campaigns.status', $campaign), ['status' => CampaignStatus::Active->value])->assertRedirect();
        $this->assertSame(CampaignStatus::Active, $campaign->fresh()->status);
        $this->assertNotNull($web->fresh()->approved_at);
    }

    public function test_content_cannot_be_published_before_campaign_is_active(): void
    {
        $agency = Agency::factory()->create();
        $owner = User::factory()->agencyOwner()->for($agency)->create();
        $campaign = Campaign::factory()->for($agency)->for($owner, 'owner')->create();
        $content = CampaignContent::factory()->for($campaign)->for($owner, 'creator')->approved()->create();

        $this->actingAs($owner)->patch(route('campaign-contents.status', [$campaign, $content]), ['status' => CampaignContentStatus::Published->value])->assertSessionHasErrors('status');
        $this->assertSame(CampaignContentStatus::Approved, $content->fresh()->status);
    }

    public function test_nested_content_route_hides_content_from_another_campaign(): void
    {
        $agency = Agency::factory()->create();
        $editor = User::factory()->editor()->for($agency)->create();
        $campaign = Campaign::factory()->for($agency)->for($editor, 'owner')->create();
        $otherCampaign = Campaign::factory()->for($agency)->for($editor, 'owner')->create();
        $otherContent = CampaignContent::factory()->for($otherCampaign)->for($editor, 'creator')->create();

        $this->actingAs($editor)->delete(route('campaign-contents.destroy', [$campaign, $otherContent]))->assertNotFound();
        $this->assertModelExists($otherContent);
    }

    public function test_editing_approved_content_resets_it_to_draft_for_reapproval(): void
    {
        $agency = Agency::factory()->create();
        $editor = User::factory()->editor()->for($agency)->create();
        $campaign = Campaign::factory()->for($agency)->for($editor, 'owner')->create();
        $content = CampaignContent::factory()->for($campaign)->for($editor, 'creator')->approved()->create();

        $this->actingAs($editor)->put(route('campaign-contents.update', [$campaign, $content]), ['channel' => CampaignChannel::Website->value, 'title' => 'Güncel başlık', 'body' => 'Güncel kampanya metni'])->assertRedirect();

        $this->assertSame('Güncel başlık', $content->fresh()->title);
        $this->assertSame(CampaignContentStatus::Draft, $content->fresh()->status);
        $this->assertNull($content->fresh()->approved_at);
    }

    /** @param array<string, mixed> $overrides @return array<string, mixed> */
    private function payload(int $agencyId, array $overrides = []): array
    {
        return array_merge(['agency_id' => $agencyId, 'name' => 'Yaz Lansmanı', 'objective' => 'Yeni okur kazanmak', 'target_audience' => '18-35 yaş şehirli okurlar', 'channels' => [CampaignChannel::Website->value, CampaignChannel::Instagram->value, CampaignChannel::Website->value], 'brief' => 'Güven veren ve enerjik dil.', 'budget' => '12500.50', 'starts_at' => '2026-09-01 10:00', 'ends_at' => '2026-09-10 18:00', 'kpi_reach' => 10000, 'kpi_clicks' => 500, 'kpi_conversions' => null], $overrides);
    }
}
