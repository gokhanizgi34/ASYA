<?php

namespace Tests\Feature;

use App\Jobs\PublishArticleToWordPress;
use App\Models\Agency;
use App\Models\Article;
use App\Models\Publication;
use App\Models\PublishingTarget;
use App\Models\User;
use App\PublicationStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AdminNewsDistributionControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_only_system_administrator_can_open_distribution_screen(): void
    {
        $agency = Agency::factory()->create();
        $owner = User::factory()->agencyOwner()->for($agency)->create();
        $administrator = User::factory()->systemAdministrator()->create();

        $this->actingAs($owner)->get(route('admin-news-distributions.index'))->assertForbidden();
        $this->actingAs($administrator)->get(route('admin-news-distributions.index'))->assertOk();
    }

    public function test_administrator_distributes_exact_content_to_all_active_targets(): void
    {
        Storage::fake('public');
        Queue::fake([PublishArticleToWordPress::class]);
        $administrator = User::factory()->systemAdministrator()->create();
        $istanbulAgency = Agency::factory()->create(['province' => 'İstanbul']);
        $ankaraAgency = Agency::factory()->create(['province' => 'Ankara']);
        PublishingTarget::factory()->for($istanbulAgency)->create(['base_url' => 'https://one.example.com']);
        PublishingTarget::factory()->for($istanbulAgency)->create(['base_url' => 'https://two.example.com']);

        $body = "İlk paragraf aynen kalmalıdır.\n\n## Yerel gelişmenin ayrıntıları\n\nİkinci paragraf da hiçbir yapay zekâ değişikliği olmadan yayımlanmalıdır.";
        $response = $this->actingAs($administrator)->post(route('admin-news-distributions.store'), [
            'title' => 'Yönetimden ortak duyuru',
            'summary' => 'Ortak duyurunun kısa ve açıklayıcı özeti.',
            'body' => $body,
            'image' => UploadedFile::fake()->image('haber.jpg', 1200, 675),
            'selection_mode' => 'all',
        ]);

        $response->assertRedirect(route('admin-news-distributions.index'));
        $this->assertDatabaseCount('admin_news_distributions', 1);
        $this->assertDatabaseCount('admin_news_distribution_items', 3);
        $this->assertDatabaseCount('articles', 2);
        $this->assertDatabaseCount('publications', 2);
        $this->assertSame([$body], Article::query()->pluck('body')->unique()->values()->all());
        $this->assertDatabaseHas('admin_news_distribution_items', [
            'agency_id' => $ankaraAgency->id,
            'failure_message' => 'Ajansın aktif WordPress hedefi bulunmuyor.',
        ]);
        Publication::query()->each(function (Publication $publication): void {
            $this->assertTrue((bool) data_get($publication->payload, 'preserve_content'));
            $this->assertTrue((bool) data_get($publication->payload, 'skip_duplicate_check'));
        });
        Queue::assertPushed(PublishArticleToWordPress::class, 2);
    }

    public function test_province_filter_only_creates_articles_for_matching_agencies(): void
    {
        Storage::fake('public');
        Queue::fake([PublishArticleToWordPress::class]);
        $administrator = User::factory()->systemAdministrator()->create();
        $istanbulAgency = Agency::factory()->create(['province' => 'İstanbul']);
        Agency::factory()->create(['province' => 'Ankara']);
        PublishingTarget::factory()->for($istanbulAgency)->create();

        $this->actingAs($administrator)->post(route('admin-news-distributions.store'), [
            'title' => 'İstanbul ortak haberi',
            'summary' => 'İstanbul ajansları için ortak haber özeti.',
            'body' => 'İstanbul ajansları için girilen haber metni değiştirilmeden sitelere gönderilir.',
            'image' => UploadedFile::fake()->image('istanbul.jpg', 1200, 675),
            'selection_mode' => 'province',
            'province' => 'İstanbul',
        ])->assertRedirect(route('admin-news-distributions.index'));

        $this->assertDatabaseCount('articles', 1);
        $this->assertDatabaseHas('articles', ['agency_id' => $istanbulAgency->id]);
    }

    public function test_administrator_can_download_date_filtered_excel_report(): void
    {
        Storage::fake('public');
        Queue::fake([PublishArticleToWordPress::class]);
        $administrator = User::factory()->systemAdministrator()->create();
        $agency = Agency::factory()->create(['province' => 'İstanbul']);
        PublishingTarget::factory()->for($agency)->create();

        $this->actingAs($administrator)->post(route('admin-news-distributions.store'), [
            'title' => 'Raporlanacak haber',
            'summary' => 'Raporlanacak haber için kısa özet.',
            'body' => 'Raporlanacak haberin ayrıntılı içerik metni burada yer almaktadır.',
            'image' => UploadedFile::fake()->image('rapor.jpg', 1200, 675),
            'selection_mode' => 'selected',
            'agency_ids' => [$agency->id],
        ]);
        Publication::query()->firstOrFail()->update([
            'status' => PublicationStatus::Published,
            'remote_url' => 'https://news.example.com/raporlanacak-haber',
            'published_at' => now(),
        ]);

        $response = $this->actingAs($administrator)->get(route('admin-news-distributions.export', [
            'from' => today()->toDateString(),
            'to' => today()->toDateString(),
        ]));

        $response->assertOk();
        $response->assertHeader('content-type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        $this->assertStringContainsString('.xlsx', (string) $response->headers->get('content-disposition'));
    }
}
