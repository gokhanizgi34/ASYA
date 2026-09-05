<?php

namespace Tests\Feature;

use App\ArticleStatus;
use App\Models\Agency;
use App\Models\Article;
use App\Models\User;
use App\SourceTrustStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ArticleControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_is_redirected_from_news_center(): void
    {
        $this->get(route('articles.index'))->assertRedirect(route('login'));
    }

    public function test_system_administrator_sees_articles_from_all_agencies_safely(): void
    {
        $administrator = User::factory()->systemAdministrator()->create();
        $firstArticle = Article::factory()->create(['title' => '<script>alert(1)</script>']);
        $secondArticle = Article::factory()->create();

        $this->actingAs($administrator)->get(route('articles.index'))
            ->assertOk()
            ->assertSee('&lt;script&gt;alert(1)&lt;/script&gt;', false)
            ->assertDontSee('<script>alert(1)</script>', false)
            ->assertSee($secondArticle->title);

        $this->actingAs($administrator)->get(route('articles.show', $firstArticle))->assertOk();
    }

    public function test_agency_users_see_only_their_own_articles(): void
    {
        $ownAgency = Agency::factory()->create();
        $otherAgency = Agency::factory()->create();
        $owner = User::factory()->agencyOwner()->for($ownAgency)->create();
        $ownArticle = Article::factory()->for($ownAgency)->create(['title' => 'Kendi Ajans Haberi']);
        $otherArticle = Article::factory()->for($otherAgency)->create(['title' => 'Diğer Ajans Haberi']);

        $this->actingAs($owner)->get(route('articles.index'))
            ->assertOk()
            ->assertSee($ownArticle->title)
            ->assertDontSee($otherArticle->title);
        $this->actingAs($owner)->get(route('articles.show', $otherArticle))->assertForbidden();
        $this->actingAs($owner)->get(route('articles.edit', $otherArticle))->assertForbidden();
    }

    public function test_editor_can_create_draft_only_for_own_agency(): void
    {
        $ownAgency = Agency::factory()->create();
        $otherAgency = Agency::factory()->create();
        $editor = User::factory()->editor()->for($ownAgency)->create();

        $response = $this->actingAs($editor)->post(route('articles.store'), $this->payload($otherAgency, [
            'title' => 'Yeni Yerel Haber',
            'status' => ArticleStatus::Draft->value,
        ]));

        $article = Article::query()->where('title', 'Yeni Yerel Haber')->firstOrFail();
        $response->assertRedirect(route('articles.show', $article));
        $this->assertSame($ownAgency->id, $article->agency_id);
        $this->assertSame($editor->id, $article->author_id);
        $this->assertSame(ArticleStatus::Draft, $article->status);
    }

    public function test_editor_cannot_publish_article(): void
    {
        $agency = Agency::factory()->create();
        $editor = User::factory()->editor()->for($agency)->create();

        $this->actingAs($editor)->post(route('articles.store'), $this->payload($agency, [
            'status' => ArticleStatus::Published->value,
            'source_trust_status' => SourceTrustStatus::Verified->value,
        ]))->assertSessionHasErrors('status');
    }

    public function test_unverified_article_cannot_be_published(): void
    {
        $agency = Agency::factory()->create();
        $owner = User::factory()->agencyOwner()->for($agency)->create();

        $this->actingAs($owner)->post(route('articles.store'), $this->payload($agency, [
            'status' => ArticleStatus::Published->value,
            'source_trust_status' => SourceTrustStatus::Unverified->value,
        ]))->assertSessionHasErrors('source_trust_status');
    }

    public function test_agency_owner_can_publish_verified_article(): void
    {
        $agency = Agency::factory()->create();
        $owner = User::factory()->agencyOwner()->for($agency)->create();

        $this->actingAs($owner)->post(route('articles.store'), $this->payload($agency, [
            'title' => 'Doğrulanmış Haber',
            'status' => ArticleStatus::Published->value,
            'source_trust_status' => SourceTrustStatus::Verified->value,
        ]));

        $article = Article::query()->where('title', 'Doğrulanmış Haber')->firstOrFail();
        $this->assertSame(ArticleStatus::Published, $article->status);
        $this->assertNotNull($article->published_at);
    }

    public function test_editor_cannot_delete_published_article_but_owner_can_soft_delete_it(): void
    {
        $agency = Agency::factory()->create();
        $editor = User::factory()->editor()->for($agency)->create();
        $owner = User::factory()->agencyOwner()->for($agency)->create();
        $article = Article::factory()->for($agency)->published()->create();

        $this->actingAs($editor)->delete(route('articles.destroy', $article))->assertForbidden();
        $this->actingAs($owner)->delete(route('articles.destroy', $article))->assertRedirect(route('articles.index'));
        $this->assertSoftDeleted($article);
    }

    public function test_news_center_can_filter_by_status_and_search_term(): void
    {
        $agency = Agency::factory()->create();
        $owner = User::factory()->agencyOwner()->for($agency)->create();
        $draft = Article::factory()->for($agency)->create(['title' => 'Ekonomi Büyüme Haberi']);
        $published = Article::factory()->for($agency)->published()->create(['title' => 'Spor Gündemi']);

        $this->actingAs($owner)->get(route('articles.index', ['status' => ArticleStatus::Draft->value, 'q' => 'Ekonomi']))
            ->assertOk()
            ->assertSee($draft->title)
            ->assertDontSee($published->title);
    }

    public function test_owner_sees_direct_ai_generation_entry_but_editor_does_not(): void
    {
        $agency = Agency::factory()->create();
        $owner = User::factory()->agencyOwner()->for($agency)->create();
        $editor = User::factory()->editor()->for($agency)->create();

        $this->actingAs($owner)->get(route('articles.index'))
            ->assertOk()
            ->assertSee('AI ile haber üret')
            ->assertSee(route('articles.generate-topic-form'), false);
        $this->actingAs($editor)->get(route('articles.index'))
            ->assertOk()
            ->assertDontSee('AI ile haber üret');
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function payload(Agency $agency, array $overrides = []): array
    {
        return array_merge([
            'agency_id' => $agency->id,
            'title' => 'Örnek Haber Başlığı',
            'summary' => 'Haberin kısa ve açıklayıcı özeti.',
            'body' => 'Bu haber metni doğrulama sınırını aşacak kadar uzun ve açıklayıcıdır.',
            'status' => ArticleStatus::Draft->value,
            'source_trust_status' => SourceTrustStatus::Unverified->value,
            'source_name' => 'Resmî Kaynak',
            'source_url' => 'https://example.com/haber',
            'failure_message' => null,
        ], $overrides);
    }
}
