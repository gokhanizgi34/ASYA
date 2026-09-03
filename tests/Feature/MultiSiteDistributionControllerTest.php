<?php

namespace Tests\Feature;

use App\Jobs\PublishArticleToWordPress;
use App\Models\Agency;
use App\Models\Article;
use App\Models\Publication;
use App\Models\PublishingTarget;
use App\Models\ScheduleEntry;
use App\Models\SeoAnalysis;
use App\Models\User;
use App\Models\VisualAsset;
use App\RemotePublicationStatus;
use App\SourceTrustStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class MultiSiteDistributionControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_editor_cannot_access_multi_site_distribution(): void
    {
        $editor = User::factory()->editor()->create();

        $this->actingAs($editor)->get(route('publications.multi-site'))->assertForbidden();
        $this->actingAs($editor)->post(route('publications.multi-site.store'))->assertForbidden();
    }

    public function test_owner_can_queue_one_article_for_multiple_targets_atomically(): void
    {
        Storage::fake('public');
        Queue::fake([PublishArticleToWordPress::class]);
        [$agency, $owner, $article] = $this->eligibleArticle();
        $targets = PublishingTarget::factory()->count(3)->for($agency)->create();

        $this->actingAs($owner)->post(route('publications.multi-site.store'), $this->payload(
            $agency,
            $article,
            $targets->pluck('id')->all(),
        ))->assertRedirect(route('publications.index'))
            ->assertSessionHas('success', '3 site için yayın kuyruğa alındı.');

        $this->assertDatabaseCount('publications', 3);
        $this->assertEqualsCanonicalizing($targets->pluck('id')->all(), Publication::query()->pluck('publishing_target_id')->all());
        Queue::assertPushed(PublishArticleToWordPress::class, 3);
    }

    public function test_existing_target_rejects_whole_distribution_without_partial_writes(): void
    {
        Storage::fake('public');
        Queue::fake([PublishArticleToWordPress::class]);
        [$agency, $owner, $article] = $this->eligibleArticle();
        $targets = PublishingTarget::factory()->count(2)->for($agency)->create();
        Publication::factory()->for($agency)->for($article)->for($targets->first(), 'publishingTarget')->for($owner, 'creator')->create();

        $this->actingAs($owner)->post(route('publications.multi-site.store'), $this->payload(
            $agency,
            $article,
            $targets->pluck('id')->all(),
        ))->assertSessionHasErrors('publishing_target_ids');

        $this->assertDatabaseCount('publications', 1);
        Queue::assertNothingPushed();
    }

    public function test_owner_cannot_include_another_agencys_target(): void
    {
        Storage::fake('public');
        [$agency, $owner, $article] = $this->eligibleArticle();
        $ownTarget = PublishingTarget::factory()->for($agency)->create();
        $otherTarget = PublishingTarget::factory()->create();

        $this->actingAs($owner)->post(route('publications.multi-site.store'), $this->payload(
            $agency,
            $article,
            [$ownTarget->id, $otherTarget->id],
        ))->assertSessionHasErrors('publishing_target_ids');

        $this->assertDatabaseCount('publications', 0);
    }

    public function test_future_multi_site_distribution_creates_schedule_per_target(): void
    {
        $this->travelTo('2026-08-28 12:00:00');
        Storage::fake('public');
        Queue::fake([PublishArticleToWordPress::class]);
        [$agency, $owner, $article] = $this->eligibleArticle();
        $targets = PublishingTarget::factory()->count(2)->for($agency)->create();

        $this->actingAs($owner)->post(route('publications.multi-site.store'), [
            ...$this->payload($agency, $article, $targets->pluck('id')->all()),
            'scheduled_for' => '2026-08-28 15:30',
            'schedule_timezone' => 'UTC',
        ])->assertRedirect(route('publications.index'));

        $this->assertDatabaseCount('publications', 2);
        $this->assertDatabaseCount('schedule_entries', 2);
        $this->assertSame(2, ScheduleEntry::query()->distinct()->count('publication_id'));
        Queue::assertNothingPushed();
    }

    /** @return array{Agency, User, Article} */
    private function eligibleArticle(): array
    {
        $agency = Agency::factory()->create();
        $owner = User::factory()->agencyOwner()->for($agency)->create();
        $article = Article::factory()->for($agency)->published()->create(['source_trust_status' => SourceTrustStatus::Verified]);
        SeoAnalysis::factory()->for($article)->for($agency)->create();
        Storage::disk('public')->put('visuals/multi-cover.jpg', 'image');
        VisualAsset::factory()->for($agency)->for($article)->create([
            'storage_path' => 'visuals/multi-cover.jpg',
            'is_selected' => true,
        ]);

        return [$agency, $owner, $article->fresh()];
    }

    /** @param array<int, int> $targetIds
     * @return array<string, mixed>
     */
    private function payload(Agency $agency, Article $article, array $targetIds): array
    {
        return [
            'agency_id' => $agency->id,
            'article_id' => $article->id,
            'publishing_target_ids' => $targetIds,
            'remote_status' => RemotePublicationStatus::Draft->value,
            'remote_author_id' => null,
            'remote_category_ids' => '',
            'remote_tag_ids' => '',
            'schedule_timezone' => 'Europe/Istanbul',
        ];
    }
}
