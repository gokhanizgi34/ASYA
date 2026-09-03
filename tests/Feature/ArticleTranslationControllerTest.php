<?php

namespace Tests\Feature;

use App\Models\Agency;
use App\Models\Article;
use App\Models\ArticleTranslation;
use App\Models\User;
use App\Services\LocalTranslationDraftBuilder;
use App\TranslationStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ArticleTranslationControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_editor_creates_local_translation_draft_only_for_own_article(): void
    {
        $agency = Agency::factory()->create();
        $otherAgency = Agency::factory()->create();
        $editor = User::factory()->editor()->for($agency)->create();
        $article = Article::factory()->for($agency)->create([
            'title' => 'Yerel gündem haberi',
            'body' => str_repeat('Kaynak haber metni ve doğrulanmış bilgiler. ', 4),
        ]);
        $foreignArticle = Article::factory()->for($otherAgency)->create();

        $this->actingAs($editor)->post(route('translations.store'), [
            'article_id' => $foreignArticle->id,
            'target_locale' => 'en',
        ])->assertSessionHasErrors('article_id');

        $this->actingAs($editor)->post(route('translations.store'), [
            'article_id' => $article->id,
            'target_locale' => 'en',
        ])->assertRedirect();

        $translation = ArticleTranslation::query()->sole();
        $this->assertSame($agency->id, $translation->agency_id);
        $this->assertSame(TranslationStatus::Draft, $translation->status);
        $this->assertStringContainsString('English editoryal çeviri taslağı', $translation->title);
        $this->assertSame((new LocalTranslationDraftBuilder)->checksum($article), $translation->source_checksum);
    }

    public function test_duplicate_target_language_is_rejected(): void
    {
        $agency = Agency::factory()->create();
        $editor = User::factory()->editor()->for($agency)->create();
        $article = Article::factory()->for($agency)->create();
        $builder = new LocalTranslationDraftBuilder;
        ArticleTranslation::factory()->for($agency)->for($article)->create([
            'target_locale' => 'de',
            'source_checksum' => $builder->checksum($article),
        ]);

        $this->actingAs($editor)->post(route('translations.store'), [
            'article_id' => $article->id,
            'target_locale' => 'de',
        ])->assertSessionHasErrors('target_locale');

        $this->assertDatabaseCount('article_translations', 1);
    }

    public function test_editor_cannot_approve_but_owner_can_approve_current_translation(): void
    {
        $agency = Agency::factory()->create();
        $editor = User::factory()->editor()->for($agency)->create();
        $owner = User::factory()->agencyOwner()->for($agency)->create();
        $article = Article::factory()->for($agency)->create();
        $builder = new LocalTranslationDraftBuilder;
        $translation = ArticleTranslation::factory()->for($agency)->for($article)->create([
            'source_checksum' => $builder->checksum($article),
        ]);
        $payload = $this->translationPayload($translation, ['status' => TranslationStatus::Approved->value]);

        $this->actingAs($editor)->put(route('translations.update', $translation), $payload)
            ->assertSessionHasErrors('status');

        $this->actingAs($owner)->put(route('translations.update', $translation), $payload)
            ->assertRedirect();

        $translation->refresh();
        $this->assertSame(TranslationStatus::Approved, $translation->status);
        $this->assertSame($owner->id, $translation->reviewed_by);
        $this->assertNotNull($translation->reviewed_at);
    }

    public function test_stale_translation_cannot_be_approved_and_can_be_refreshed(): void
    {
        $agency = Agency::factory()->create();
        $owner = User::factory()->agencyOwner()->for($agency)->create();
        $article = Article::factory()->for($agency)->create();
        $builder = new LocalTranslationDraftBuilder;
        $translation = ArticleTranslation::factory()->for($agency)->for($article)->create([
            'source_checksum' => $builder->checksum($article),
            'status' => TranslationStatus::Review,
        ]);
        $article->update(['body' => str_repeat('Kaynak haber sonradan değiştirildi ve yeni bilgiler eklendi. ', 4)]);

        $this->assertTrue($translation->fresh()->isSourceStale());

        $this->actingAs($owner)->put(route('translations.update', $translation), $this->translationPayload($translation, [
            'status' => TranslationStatus::Approved->value,
        ]))->assertSessionHasErrors('status');

        $this->actingAs($owner)->post(route('translations.refresh', $translation))->assertRedirect();

        $translation->refresh();
        $this->assertFalse($translation->isSourceStale());
        $this->assertSame(TranslationStatus::Draft, $translation->status);
    }

    public function test_translation_is_tenant_isolated_and_output_is_escaped(): void
    {
        $agency = Agency::factory()->create();
        $otherAgency = Agency::factory()->create();
        $editor = User::factory()->editor()->for($agency)->create();
        $otherEditor = User::factory()->editor()->for($otherAgency)->create();
        $article = Article::factory()->for($agency)->create();
        $translation = ArticleTranslation::factory()->for($agency)->for($article)->create([
            'title' => '<script>alert(1)</script>',
            'source_checksum' => (new LocalTranslationDraftBuilder)->checksum($article),
        ]);

        $this->actingAs($otherEditor)->get(route('translations.show', $translation))->assertForbidden();

        $this->actingAs($editor)->get(route('translations.show', $translation))
            ->assertOk()
            ->assertSee('&lt;script&gt;alert(1)&lt;/script&gt;', false)
            ->assertDontSee('<script>alert(1)</script>', false);
    }

    /** @param array<string, mixed> $overrides
     * @return array<string, mixed>
     */
    private function translationPayload(ArticleTranslation $translation, array $overrides = []): array
    {
        return array_merge([
            'title' => $translation->title,
            'summary' => $translation->summary,
            'body' => str_repeat('Human-reviewed translated article content with verified facts and terminology. ', 3),
            'glossary' => "belediye=municipality\nhaber=news",
            'status' => TranslationStatus::Review->value,
        ], $overrides);
    }
}
