<?php

namespace Tests\Feature;

use App\Models\Agency;
use App\Models\Article;
use App\Models\SeoAnalysis;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SeoAnalysisControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_agency_user_can_view_and_analyze_own_article(): void
    {
        $agency = Agency::factory()->create();
        $owner = User::factory()->agencyOwner()->for($agency)->create();
        $article = Article::factory()->for($agency)->create([
            'title' => 'Ekonomi Alanında Yeni Gelişmeler Kamuoyuna Açıklandı',
            'body' => str_repeat('Ekonomi alanındaki gelişmeler uzmanlar tarafından ayrıntılı olarak değerlendirildi. ', 45),
        ]);

        $this->actingAs($owner)->get(route('seo.show', $article))
            ->assertOk()
            ->assertSee('Bu haber henüz analiz edilmedi.');

        $this->actingAs($owner)->post(route('seo.analyze', $article), [
            'focus_keyword' => 'ekonomi',
        ])->assertRedirect(route('seo.show', $article));

        $analysis = SeoAnalysis::query()->whereBelongsTo($article)->firstOrFail();
        $this->assertSame($agency->id, $analysis->agency_id);
        $this->assertSame('ekonomi', $analysis->focus_keyword);
        $this->assertLessThanOrEqual(60, mb_strlen($analysis->meta_title));
        $this->assertNotEmpty($analysis->keywords);
    }

    public function test_reanalysis_updates_existing_record_instead_of_creating_duplicate(): void
    {
        $agency = Agency::factory()->create();
        $editor = User::factory()->editor()->for($agency)->create();
        $article = Article::factory()->for($agency)->create();

        $this->actingAs($editor)->post(route('seo.analyze', $article), ['focus_keyword' => 'ilk kelime']);
        $this->actingAs($editor)->post(route('seo.analyze', $article), ['focus_keyword' => 'ikinci kelime']);

        $this->assertDatabaseCount('seo_analyses', 1);
        $this->assertDatabaseHas('seo_analyses', ['article_id' => $article->id, 'focus_keyword' => 'ikinci kelime']);
    }

    public function test_user_cannot_view_or_analyze_another_agencys_article(): void
    {
        $ownAgency = Agency::factory()->create();
        $otherAgency = Agency::factory()->create();
        $owner = User::factory()->agencyOwner()->for($ownAgency)->create();
        $article = Article::factory()->for($otherAgency)->create();

        $this->actingAs($owner)->get(route('seo.show', $article))->assertForbidden();
        $this->actingAs($owner)->post(route('seo.analyze', $article), ['focus_keyword' => 'yasak'])->assertForbidden();
        $this->assertDatabaseEmpty('seo_analyses');
    }

    public function test_seo_output_is_escaped_in_view(): void
    {
        $administrator = User::factory()->systemAdministrator()->create();
        $article = Article::factory()->create(['title' => '<script>alert(1)</script> Güvenli Haber']);
        SeoAnalysis::factory()->for($article)->create(['agency_id' => $article->agency_id, 'meta_title' => '<script>alert(2)</script>']);

        $this->actingAs($administrator)->get(route('seo.show', $article))
            ->assertOk()
            ->assertSee('&lt;script&gt;alert(2)&lt;/script&gt;', false)
            ->assertDontSee('<script>alert(2)</script>', false);
    }
}
