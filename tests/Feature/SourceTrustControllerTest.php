<?php

namespace Tests\Feature;

use App\Jobs\ImportNewsSource;
use App\Models\Agency;
use App\Models\NewsSource;
use App\Models\SourceTrustAssessment;
use App\Models\User;
use App\SourceTrustBand;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class SourceTrustControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_editor_registers_source_for_own_agency_with_100_score_and_automatic_import(): void
    {
        Queue::fake([ImportNewsSource::class]);
        $agency = Agency::factory()->create();
        $otherAgency = Agency::factory()->create();
        $editor = User::factory()->editor()->for($agency)->create();

        $this->actingAs($editor)->post(route('source-trust.sources.store'), [
            'agency_id' => $otherAgency->id,
            'name' => 'Örnek Haber',
            'feed_url' => 'https://WWW.Ornek.COM/gundem',
            'feed_format' => 'auto',
            'source_type' => 'news_site',
            'notes' => 'İlk editoryal sicil notu.',
            'is_active' => '1',
        ])->assertRedirect()
            ->assertSessionHas('success');

        $source = NewsSource::query()->sole();
        $this->assertSame($agency->id, $source->agency_id);
        $this->assertSame('ornek.com', $source->domain);
        $this->assertSame(100.0, $source->latest_score);
        $this->assertSame(SourceTrustBand::High, $source->latest_band);
        $this->assertDatabaseHas('source_trust_assessments', [
            'news_source_id' => $source->id,
            'weighted_score' => 100,
            'notes' => null,
        ]);
        Queue::assertPushedOn('news-ingestion', ImportNewsSource::class);
        Queue::assertPushed(fn (ImportNewsSource $job): bool => $job->newsSourceId === $source->id);
    }

    public function test_same_host_allows_multiple_feed_urls_but_rejects_the_same_feed_url(): void
    {
        Queue::fake([ImportNewsSource::class]);
        $agency = Agency::factory()->create();
        $editor = User::factory()->editor()->for($agency)->create();

        $this->actingAs($editor)->post(route('source-trust.sources.store'), [
            'name' => 'RSS Bir',
            'feed_url' => 'https://rss.app/feeds/one.xml',
            'feed_format' => 'auto',
            'source_type' => 'agency',
            'is_active' => '1',
            'daily_item_limit' => 10,
        ])->assertRedirect();
        $this->actingAs($editor)->post(route('source-trust.sources.store'), [
            'name' => 'RSS İki',
            'feed_url' => 'https://rss.app/feeds/two.xml',
            'feed_format' => 'auto',
            'source_type' => 'agency',
            'is_active' => '1',
            'daily_item_limit' => 10,
        ])->assertRedirect();

        $this->assertDatabaseCount('news_sources', 2);
        $this->actingAs($editor)->post(route('source-trust.sources.store'), [
            'name' => 'RSS Tekrar',
            'feed_url' => 'https://rss.app/feeds/one.xml',
            'feed_format' => 'auto',
            'source_type' => 'agency',
            'is_active' => '1',
            'daily_item_limit' => 10,
        ])->assertSessionHasErrors('feed_url');
    }

    public function test_single_selected_score_updates_source_and_preserves_history(): void
    {
        $agency = Agency::factory()->create();
        $editor = User::factory()->editor()->for($agency)->create();
        $source = NewsSource::factory()->for($agency)->create();

        $this->actingAs($editor)->post(route('source-trust.assessments.store', $source), [
            'trust_score' => 70,
        ])->assertRedirect();

        $assessment = SourceTrustAssessment::query()->sole();
        $this->assertSame(70.0, $assessment->weighted_score);
        $this->assertSame(SourceTrustBand::Medium, $assessment->trust_band);
        $this->assertNull($assessment->notes);
        $this->assertSame(70.0, $source->fresh()->latest_score);

        $this->actingAs($editor)->post(route('source-trust.assessments.store', $source), [
            'trust_score' => 90,
        ])->assertRedirect();

        $this->assertDatabaseCount('source_trust_assessments', 2);
        $this->assertSame(SourceTrustBand::High, $source->fresh()->latest_band);
    }

    public function test_foreign_source_assessment_is_forbidden_and_score_must_be_an_allowed_choice(): void
    {
        $agency = Agency::factory()->create();
        $otherAgency = Agency::factory()->create();
        $editor = User::factory()->editor()->for($agency)->create();
        $otherEditor = User::factory()->editor()->for($otherAgency)->create();
        $source = NewsSource::factory()->for($agency)->create();

        $this->actingAs($otherEditor)->post(route('source-trust.assessments.store', $source), [
            'trust_score' => 70,
        ])->assertForbidden();

        $this->actingAs($editor)->post(route('source-trust.assessments.store', $source), [
            'trust_score' => 80,
        ])->assertSessionHasErrors([
            'trust_score' => 'Güven puanı yalnızca 10, 30, 50, 70, 90 veya 100 olabilir.',
        ]);

        $this->assertDatabaseCount('source_trust_assessments', 0);
    }

    public function test_source_output_is_tenant_isolated_and_escaped(): void
    {
        $agency = Agency::factory()->create();
        $otherAgency = Agency::factory()->create();
        $editor = User::factory()->editor()->for($agency)->create();
        NewsSource::factory()->for($agency)->create(['name' => '<script>alert(1)</script>']);
        NewsSource::factory()->for($otherAgency)->create(['name' => 'Yabancı Kaynak']);

        $this->actingAs($editor)->get(route('source-trust.index'))
            ->assertOk()
            ->assertSee('&lt;script&gt;alert(1)&lt;/script&gt;', false)
            ->assertDontSee('<script>alert(1)</script>', false)
            ->assertDontSee('Yabancı Kaynak')
            ->assertSee('Kaynağı güncelle')
            ->assertDontSee('Kaynağı sil')
            ->assertSee('Güven puanı')
            ->assertDontSee('Değerlendirme kanıtı');
    }

    public function test_editor_updates_own_source_and_changed_url_restarts_import(): void
    {
        Queue::fake([ImportNewsSource::class]);
        $agency = Agency::factory()->create();
        $editor = User::factory()->editor()->for($agency)->create();
        $source = NewsSource::factory()->for($agency)->create([
            'feed_url' => 'https://ornek.com/eski',
            'domain' => 'ornek.com',
        ]);

        $this->actingAs($editor)->put(route('source-trust.sources.update', $source), [
            'name' => 'Güncel Kaynak',
            'feed_url' => 'https://www.yeni-ornek.com/haberler',
            'feed_format' => 'auto',
            'source_type' => 'official',
            'notes' => 'Güncel not',
            'is_active' => '1',
        ])->assertRedirect()->assertSessionHas('success');

        $source->refresh();
        $this->assertSame('Güncel Kaynak', $source->name);
        $this->assertSame('yeni-ornek.com', $source->domain);
        $this->assertSame('https://www.yeni-ornek.com/haberler', $source->feed_url);
        Queue::assertPushed(fn (ImportNewsSource $job): bool => $job->newsSourceId === $source->id);
    }

    public function test_editor_cannot_update_foreign_source(): void
    {
        $agency = Agency::factory()->create();
        $otherAgency = Agency::factory()->create();
        $editor = User::factory()->editor()->for($agency)->create();
        $source = NewsSource::factory()->for($otherAgency)->create();

        $this->actingAs($editor)->put(route('source-trust.sources.update', $source), [
            'name' => 'Yetkisiz değişiklik',
            'feed_url' => 'https://ornek.com/haberler',
            'feed_format' => 'auto',
            'source_type' => 'news_site',
            'is_active' => '1',
        ])->assertForbidden();

        $this->assertNotSame('Yetkisiz değişiklik', $source->fresh()->name);
    }

    public function test_agency_owner_deletes_own_source_with_assessment_history(): void
    {
        $agency = Agency::factory()->create();
        $owner = User::factory()->agencyOwner()->for($agency)->create();
        $source = NewsSource::factory()->for($agency)->create();
        SourceTrustAssessment::factory()->create([
            'agency_id' => $agency->id,
            'news_source_id' => $source->id,
        ]);

        $this->actingAs($owner)->get(route('source-trust.index'))->assertOk()->assertSee('Kaynağı sil');

        $this->actingAs($owner)->delete(route('source-trust.sources.destroy', $source))
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertDatabaseMissing('news_sources', ['id' => $source->id]);
        $this->assertDatabaseMissing('source_trust_assessments', ['news_source_id' => $source->id]);
    }

    public function test_editor_cannot_delete_source(): void
    {
        $agency = Agency::factory()->create();
        $editor = User::factory()->editor()->for($agency)->create();
        $source = NewsSource::factory()->for($agency)->create();

        $this->actingAs($editor)->delete(route('source-trust.sources.destroy', $source))->assertForbidden();

        $this->assertDatabaseHas('news_sources', ['id' => $source->id]);
    }
}
