<?php

namespace Tests\Feature;

use App\Jobs\PublishArticleToWordPress;
use App\Models\Agency;
use App\Models\Article;
use App\Models\Publication;
use App\Models\PublishingTarget;
use App\Models\ScheduleEntry;
use App\Models\SeoAnalysis;
use App\Models\TaxonomyMapping;
use App\Models\User;
use App\Models\VisualAsset;
use App\PublicationStatus;
use App\RemotePublicationStatus;
use App\SourceTrustStatus;
use App\TaxonomyType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class PublicationControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_editor_can_view_own_publications_but_cannot_create_them(): void
    {
        $agency = Agency::factory()->create();
        $editor = User::factory()->editor()->for($agency)->create();
        $target = PublishingTarget::factory()->for($agency)->create();
        $article = Article::factory()->for($agency)->create();
        $publication = Publication::factory()->for($agency)->for($article)->for($target, 'publishingTarget')->for($editor, 'creator')->create();

        $this->actingAs($editor)->get(route('publications.index'))->assertOk();
        $this->actingAs($editor)->get(route('publications.show', $publication))->assertOk();
        $this->actingAs($editor)->get(route('publications.create'))->assertForbidden();
    }

    public function test_valid_publication_snapshots_content_and_dispatches_job(): void
    {
        Storage::fake('public');
        Queue::fake([PublishArticleToWordPress::class]);
        [$agency, $owner, $article, $target] = $this->eligibleArticleAndTarget();

        $response = $this->actingAs($owner)->post(route('publications.store'), [
            'agency_id' => $agency->id,
            'article_id' => $article->id,
            'publishing_target_id' => $target->id,
            'remote_status' => RemotePublicationStatus::Publish->value,
            'remote_author_id' => 7,
            'remote_category_ids' => '2,5',
            'remote_tag_ids' => '8,11',
        ]);

        $publication = Publication::query()->firstOrFail();
        $response->assertRedirect(route('publications.show', $publication));
        $this->assertSame(PublicationStatus::Queued, $publication->status);
        $this->assertSame($article->seoAnalysis->meta_title, data_get($publication->payload, 'title'));
        $this->assertSame([2, 5], data_get($publication->payload, 'categories'));
        $this->assertSame('visuals/cover.jpg', data_get($publication->payload, 'media.path'));
        Queue::assertPushedOn('publishing', PublishArticleToWordPress::class, fn (PublishArticleToWordPress $job): bool => $job->publicationId === $publication->id);
    }

    public function test_publication_uses_matching_taxonomy_when_manual_ids_are_absent(): void
    {
        Storage::fake('public');
        Queue::fake([PublishArticleToWordPress::class]);
        [$agency, $owner, $article, $target] = $this->eligibleArticleAndTarget();
        $term = $article->seoAnalysis->focus_keyword;
        TaxonomyMapping::factory()->for($agency)->for($target, 'publishingTarget')->create(['type' => TaxonomyType::Category, 'source_term' => $term, 'source_key' => Str::slug($term), 'remote_id' => 321]);
        TaxonomyMapping::factory()->for($agency)->for($target, 'publishingTarget')->create(['type' => TaxonomyType::Tag, 'source_term' => $term, 'source_key' => Str::slug($term), 'remote_id' => 654]);

        $this->actingAs($owner)->post(route('publications.store'), $this->payload($agency, $article, $target))->assertRedirect();

        $publication = Publication::query()->sole();
        $this->assertSame([321], data_get($publication->payload, 'categories'));
        $this->assertSame([654], data_get($publication->payload, 'tags'));
        $this->assertContains($term, data_get($publication->payload, 'meta.asya_taxonomy_matches'));
    }

    public function test_unverified_or_incomplete_article_is_rejected_without_queueing(): void
    {
        Queue::fake([PublishArticleToWordPress::class]);
        $agency = Agency::factory()->create();
        $owner = User::factory()->agencyOwner()->for($agency)->create();
        $article = Article::factory()->for($agency)->published()->create(['source_trust_status' => SourceTrustStatus::Unverified]);
        $target = PublishingTarget::factory()->for($agency)->create();

        $this->actingAs($owner)->post(route('publications.store'), $this->payload($agency, $article, $target))->assertSessionHasErrors('article_id');
        $this->assertDatabaseCount('publications', 0);
        Queue::assertNothingPushed();
    }

    public function test_other_agency_target_and_duplicate_publication_are_rejected(): void
    {
        Storage::fake('public');
        Queue::fake([PublishArticleToWordPress::class]);
        [$agency, $owner, $article, $target] = $this->eligibleArticleAndTarget();
        $otherTarget = PublishingTarget::factory()->create();

        $this->actingAs($owner)->post(route('publications.store'), $this->payload($agency, $article, $otherTarget))->assertSessionHasErrors('publishing_target_id');
        Publication::factory()->for($agency)->for($article)->for($target, 'publishingTarget')->for($owner, 'creator')->create();
        $this->actingAs($owner)->post(route('publications.store'), $this->payload($agency, $article, $target))->assertSessionHasErrors('publishing_target_id');
        $this->assertDatabaseCount('publications', 1);
    }

    public function test_failed_publication_can_be_manually_queued_again(): void
    {
        Queue::fake([PublishArticleToWordPress::class]);
        $agency = Agency::factory()->create();
        $owner = User::factory()->agencyOwner()->for($agency)->create();
        $target = PublishingTarget::factory()->for($agency)->create();
        $publication = Publication::factory()->for($agency)->for($target, 'publishingTarget')->for($owner, 'creator')->failed()->create();

        $this->actingAs($owner)->post(route('publications.dispatch', $publication))->assertRedirect();

        $this->assertSame(PublicationStatus::Queued, $publication->fresh()->status);
        $this->assertNull($publication->fresh()->failure_message);
        Queue::assertPushedOn('publishing', PublishArticleToWordPress::class);
    }

    public function test_future_publication_creates_schedule_without_dispatching_publisher_immediately(): void
    {
        $this->travelTo('2026-08-28 12:00:00');
        Storage::fake('public');
        Queue::fake([PublishArticleToWordPress::class]);
        [$agency, $owner, $article, $target] = $this->eligibleArticleAndTarget();

        $this->actingAs($owner)->post(route('publications.store'), [
            'agency_id' => $agency->id,
            'article_id' => $article->id,
            'publishing_target_id' => $target->id,
            'remote_status' => RemotePublicationStatus::Draft->value,
            'scheduled_for' => '2026-08-28 15:30',
            'schedule_timezone' => 'UTC',
        ])->assertRedirect();

        $publication = Publication::query()->firstOrFail();
        $entry = ScheduleEntry::query()->firstOrFail();
        $this->assertSame($publication->id, $entry->publication_id);
        $this->assertSame('2026-08-28 18:30:00', $entry->scheduled_for->format('Y-m-d H:i:s'));
        Queue::assertNothingPushed();

        $this->actingAs($owner)->patch(route('schedules.status', $entry), ['operation' => 'cancel'])->assertRedirect();
        $this->assertSame(PublicationStatus::Failed, $publication->fresh()->status);
    }

    /** @return array{Agency, User, Article, PublishingTarget} */
    private function eligibleArticleAndTarget(): array
    {
        $agency = Agency::factory()->create();
        $owner = User::factory()->agencyOwner()->for($agency)->create();
        $article = Article::factory()->for($agency)->published()->create(['source_trust_status' => SourceTrustStatus::Verified]);
        SeoAnalysis::factory()->for($article)->for($agency)->create();
        Storage::disk('public')->put('visuals/cover.jpg', 'image-content');
        VisualAsset::factory()->for($agency)->for($article)->create(['storage_path' => 'visuals/cover.jpg', 'is_selected' => true]);
        $target = PublishingTarget::factory()->for($agency)->create();

        return [$agency, $owner, $article->fresh(), $target];
    }

    /** @return array<string, mixed> */
    private function payload(Agency $agency, Article $article, PublishingTarget $target): array
    {
        return ['agency_id' => $agency->id, 'article_id' => $article->id, 'publishing_target_id' => $target->id, 'remote_status' => RemotePublicationStatus::Draft->value];
    }
}
