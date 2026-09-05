<?php

namespace Tests\Feature;

use App\Models\Agency;
use App\Models\ContentBatch;
use App\Models\RawNewsItem;
use App\Models\User;
use App\RawNewsStatus;
use App\Services\AutomaticNewsPipelineStarter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery\MockInterface;
use Tests\TestCase;

class RawNewsItemControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_is_redirected_from_raw_news_pool(): void
    {
        $this->get(route('raw-news.index'))->assertRedirect(route('login'));
    }

    public function test_system_administrator_sees_all_raw_news_safely(): void
    {
        $administrator = User::factory()->systemAdministrator()->create();
        $unsafe = RawNewsItem::factory()->create(['original_title' => '<script>alert(1)</script>']);
        $other = RawNewsItem::factory()->create();

        $this->actingAs($administrator)->get(route('raw-news.index'))
            ->assertOk()
            ->assertSee('&lt;script&gt;alert(1)&lt;/script&gt;', false)
            ->assertDontSee('<script>alert(1)</script>', false)
            ->assertSee($other->original_title);
        $this->actingAs($administrator)->get(route('raw-news.show', $unsafe))->assertOk();
    }

    public function test_agency_user_sees_only_own_raw_news(): void
    {
        $ownAgency = Agency::factory()->create();
        $otherAgency = Agency::factory()->create();
        $owner = User::factory()->agencyOwner()->for($ownAgency)->create();
        $ownItem = RawNewsItem::factory()->for($ownAgency)->create(['original_title' => 'Kendi Ham Haberi']);
        $otherItem = RawNewsItem::factory()->for($otherAgency)->create(['original_title' => 'Diğer Ham Haber']);

        $this->actingAs($owner)->get(route('raw-news.index'))
            ->assertOk()
            ->assertSee($ownItem->original_title)
            ->assertDontSee($otherItem->original_title);
        $this->actingAs($owner)->get(route('raw-news.show', $otherItem))->assertForbidden();
    }

    public function test_editor_can_add_raw_news_only_to_own_agency(): void
    {
        $ownAgency = Agency::factory()->create();
        $otherAgency = Agency::factory()->create();
        $editor = User::factory()->editor()->for($ownAgency)->create();

        $response = $this->actingAs($editor)->post(route('raw-news.store'), $this->payload($otherAgency, [
            'original_title' => 'Yeni Ham Haber',
        ]));

        $item = RawNewsItem::query()->where('original_title', 'Yeni Ham Haber')->firstOrFail();
        $response->assertRedirect(route('raw-news.show', $item));
        $this->assertSame($ownAgency->id, $item->agency_id);
        $this->assertSame(RawNewsStatus::Pending, $item->status);
        $this->assertSame(64, strlen($item->checksum));
    }

    public function test_duplicate_source_and_title_is_rejected_within_same_agency(): void
    {
        $agency = Agency::factory()->create();
        $owner = User::factory()->agencyOwner()->for($agency)->create();
        $payload = $this->payload($agency);

        $this->actingAs($owner)->post(route('raw-news.store'), $payload);
        $this->actingAs($owner)->post(route('raw-news.store'), $payload)->assertSessionHasErrors('checksum');
        $this->assertDatabaseCount('raw_news_items', 1);
    }

    public function test_owner_can_soft_delete_own_raw_item_but_not_another_agencys_item(): void
    {
        $ownAgency = Agency::factory()->create();
        $otherAgency = Agency::factory()->create();
        $owner = User::factory()->agencyOwner()->for($ownAgency)->create();
        $ownItem = RawNewsItem::factory()->for($ownAgency)->create();
        $otherItem = RawNewsItem::factory()->for($otherAgency)->create();

        $this->actingAs($owner)->delete(route('raw-news.destroy', $otherItem))->assertForbidden();
        $this->actingAs($owner)->delete(route('raw-news.destroy', $ownItem))->assertRedirect(route('raw-news.index'));
        $this->assertSoftDeleted($ownItem);
    }

    public function test_pool_can_filter_by_status_language_and_search(): void
    {
        $agency = Agency::factory()->create();
        $owner = User::factory()->agencyOwner()->for($agency)->create();
        $matching = RawNewsItem::factory()->for($agency)->create(['original_title' => 'Ekonomi Verisi', 'language' => 'tr']);
        $other = RawNewsItem::factory()->for($agency)->failed()->create(['original_title' => 'Sports Data', 'language' => 'en']);

        $this->actingAs($owner)->get(route('raw-news.index', ['status' => RawNewsStatus::Pending->value, 'language' => 'tr', 'q' => 'Ekonomi']))
            ->assertOk()
            ->assertSee($matching->original_title)
            ->assertDontSee($other->original_title);
    }

    public function test_owner_can_send_one_pending_raw_news_item_directly_to_production(): void
    {
        $agency = Agency::factory()->create();
        $owner = User::factory()->agencyOwner()->for($agency)->create();
        $item = RawNewsItem::factory()->for($agency)->create(['status' => RawNewsStatus::Pending]);
        $this->mock(AutomaticNewsPipelineStarter::class, function (MockInterface $mock) use ($agency, $item): void {
            $mock->shouldReceive('startForAgency')->once()->withArgs(fn (int $agencyId, array $rawNewsItemIds): bool => $agencyId === $agency->id && $rawNewsItemIds === [$item->id])->andReturn(new ContentBatch);
        });

        $this->actingAs($owner)->post(route('raw-news.production', $item))->assertRedirect()->assertSessionHas('success');
        $this->assertNull($item->fresh()->failure_message);
        $this->assertSame(RawNewsStatus::Pending, $item->fresh()->status);
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function payload(Agency $agency, array $overrides = []): array
    {
        return array_merge([
            'agency_id' => $agency->id,
            'external_id' => 'source-1001',
            'source_name' => 'Örnek Ajans',
            'source_url' => 'https://example.com/orijinal-haber',
            'original_title' => 'Orijinal Haber Başlığı',
            'original_body' => 'Bu işlenmemiş ham haber metni doğrulama sınırını aşacak kadar uzundur.',
            'original_image_url' => 'https://example.com/image.jpg',
            'language' => 'tr',
            'priority' => 70,
        ], $overrides);
    }
}
