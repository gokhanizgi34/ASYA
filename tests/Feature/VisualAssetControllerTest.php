<?php

namespace Tests\Feature;

use App\CopyrightStatus;
use App\Models\Agency;
use App\Models\Article;
use App\Models\User;
use App\Models\VisualAsset;
use App\VisualAssetStatus;
use App\VisualSourceType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class VisualAssetControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_is_redirected_from_visual_engine(): void
    {
        $this->get(route('visual-assets.index'))->assertRedirect(route('login'));
    }

    public function test_agency_user_sees_only_own_visual_assets(): void
    {
        $ownAgency = Agency::factory()->create();
        $otherAgency = Agency::factory()->create();
        $editor = User::factory()->editor()->for($ownAgency)->create();
        $ownAsset = VisualAsset::factory()->for($ownAgency)->create(['title' => 'Kendi Görseli']);
        $otherAsset = VisualAsset::factory()->for($otherAgency)->create(['title' => 'Başka Ajans Görseli']);

        $this->actingAs($editor)->get(route('visual-assets.index'))
            ->assertOk()
            ->assertSee($ownAsset->title)
            ->assertDontSee($otherAsset->title);
        $this->actingAs($editor)->get(route('visual-assets.show', $otherAsset))->assertForbidden();
    }

    public function test_system_administrator_sees_all_assets_with_escaped_titles(): void
    {
        $administrator = User::factory()->systemAdministrator()->create();
        VisualAsset::factory()->create(['title' => '<script>alert(1)</script>']);
        $otherAsset = VisualAsset::factory()->create(['title' => 'Diğer Ajans Görseli']);

        $this->actingAs($administrator)->get(route('visual-assets.index'))
            ->assertOk()
            ->assertSee('&lt;script&gt;alert(1)&lt;/script&gt;', false)
            ->assertDontSee('<script>alert(1)</script>', false)
            ->assertSee($otherAsset->title);
    }

    public function test_low_resolution_upload_is_stored_and_marked_for_replacement(): void
    {
        Storage::fake('public');
        $agency = Agency::factory()->create();
        $editor = User::factory()->editor()->for($agency)->create();

        $this->actingAs($editor)->post(route('visual-assets.store'), $this->payload([
            'agency_id' => $agency->id,
            'title' => 'Düşük Kaliteli Görsel',
            'source_type' => VisualSourceType::Upload->value,
            'copyright_status' => CopyrightStatus::Original->value,
            'image' => UploadedFile::fake()->image('cover.jpg', 500, 300),
            'source_url' => null,
        ]))->assertRedirect();

        $asset = VisualAsset::query()->where('title', 'Düşük Kaliteli Görsel')->firstOrFail();
        $this->assertSame(VisualAssetStatus::NeedsReplacement, $asset->status);
        $this->assertLessThan(70, $asset->quality_score);
        Storage::disk('public')->assertExists($asset->storage_path);
    }

    public function test_high_resolution_original_upload_is_approved(): void
    {
        Storage::fake('public');
        $agency = Agency::factory()->create();
        $editor = User::factory()->editor()->for($agency)->create();

        $this->actingAs($editor)->post(route('visual-assets.store'), $this->payload([
            'agency_id' => $agency->id,
            'title' => 'Yayın Kalitesinde Görsel',
            'source_type' => VisualSourceType::Upload->value,
            'copyright_status' => CopyrightStatus::Original->value,
            'image' => UploadedFile::fake()->image('cover.jpg', 1280, 720),
            'source_url' => null,
        ]));

        $asset = VisualAsset::query()->where('title', 'Yayın Kalitesinde Görsel')->firstOrFail();
        $this->assertSame(VisualAssetStatus::Approved, $asset->status);
        $this->assertSame(100, $asset->quality_score);
        $this->assertSame(1280, $asset->width);
        $this->assertSame(720, $asset->height);
    }

    public function test_restricted_remote_image_is_never_approved(): void
    {
        $administrator = User::factory()->systemAdministrator()->create();
        $agency = Agency::factory()->create();

        $this->actingAs($administrator)->post(route('visual-assets.store'), $this->payload([
            'agency_id' => $agency->id,
            'copyright_status' => CopyrightStatus::Restricted->value,
        ]));

        $asset = VisualAsset::query()->latest('id')->firstOrFail();
        $this->assertSame(VisualAssetStatus::NeedsReplacement, $asset->status);
        $this->assertSame(10, $asset->quality_score);
    }

    public function test_ai_generation_request_is_prepared_for_provider_queue(): void
    {
        $agency = Agency::factory()->create();
        $owner = User::factory()->agencyOwner()->for($agency)->create();

        $this->actingAs($owner)->post(route('visual-assets.store'), $this->payload([
            'agency_id' => $agency->id,
            'source_type' => VisualSourceType::AiGenerated->value,
            'copyright_status' => CopyrightStatus::Original->value,
            'source_url' => null,
            'width' => null,
            'height' => null,
            'generation_prompt' => 'Gece şehir meydanında fotogerçekçi yatay haber kapağı.',
        ]));

        $asset = VisualAsset::query()->latest('id')->firstOrFail();
        $this->assertSame(VisualAssetStatus::Generating, $asset->status);
        $this->assertStringContainsString('sağlayıcı kuyruğuna', $asset->evaluation_notes);
    }

    public function test_agency_user_cannot_spoof_agency_or_attach_other_agency_article(): void
    {
        $ownAgency = Agency::factory()->create();
        $otherAgency = Agency::factory()->create();
        $editor = User::factory()->editor()->for($ownAgency)->create();
        $otherArticle = Article::factory()->for($otherAgency)->create();

        $this->actingAs($editor)->post(route('visual-assets.store'), $this->payload([
            'agency_id' => $otherAgency->id,
            'article_id' => $otherArticle->id,
        ]))->assertSessionHasErrors('article_id');

        $this->assertDatabaseCount('visual_assets', 0);
    }

    public function test_selecting_approved_cover_deselects_previous_cover_atomically(): void
    {
        $agency = Agency::factory()->create();
        $owner = User::factory()->agencyOwner()->for($agency)->create();
        $article = Article::factory()->for($agency)->create();
        $oldCover = VisualAsset::factory()->for($agency)->for($article)->create(['is_selected' => true]);
        $newCover = VisualAsset::factory()->for($agency)->for($article)->create();

        $this->actingAs($owner)->patch(route('visual-assets.select', $newCover))->assertRedirect();

        $this->assertFalse($oldCover->fresh()->is_selected);
        $this->assertTrue($newCover->fresh()->is_selected);
        $this->assertSame($newCover->id, $article->fresh()->selectedVisualAsset->id);
    }

    public function test_risky_visual_cannot_be_selected_and_selected_cover_cannot_be_deleted(): void
    {
        $agency = Agency::factory()->create();
        $owner = User::factory()->agencyOwner()->for($agency)->create();
        $article = Article::factory()->for($agency)->create();
        $riskyAsset = VisualAsset::factory()->for($agency)->for($article)->needsReplacement()->create();
        $selectedAsset = VisualAsset::factory()->for($agency)->for($article)->create(['is_selected' => true]);

        $this->actingAs($owner)->patch(route('visual-assets.select', $riskyAsset))->assertStatus(422);
        $this->actingAs($owner)->delete(route('visual-assets.destroy', $selectedAsset))->assertForbidden();
    }

    public function test_stored_file_preview_is_tenant_protected(): void
    {
        Storage::fake('public');
        $agency = Agency::factory()->create();
        $otherAgency = Agency::factory()->create();
        $owner = User::factory()->agencyOwner()->for($agency)->create();
        $otherOwner = User::factory()->agencyOwner()->for($otherAgency)->create();
        Storage::disk('public')->put('visual-assets/test/cover.jpg', 'image-content');
        $asset = VisualAsset::factory()->for($agency)->create(['storage_path' => 'visual-assets/test/cover.jpg']);

        $this->actingAs($otherOwner)->get(route('visual-assets.file', $asset))->assertForbidden();
        $this->actingAs($owner)->get(route('visual-assets.file', $asset))->assertOk();
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function payload(array $overrides = []): array
    {
        return array_merge([
            'agency_id' => null,
            'article_id' => null,
            'title' => 'Gündem Kapak Görseli',
            'source_type' => VisualSourceType::Archive->value,
            'copyright_status' => CopyrightStatus::Licensed->value,
            'source_url' => 'https://example.com/cover.jpg',
            'width' => 1280,
            'height' => 720,
            'alt_text' => 'Gündem haberine ait kapak görseli',
            'headline_overlay' => 'Gündemde Bugün',
            'generation_prompt' => null,
        ], $overrides);
    }
}
