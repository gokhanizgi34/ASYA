<?php

namespace Tests\Feature;

use App\IntegrationAuthType;
use App\IntegrationProvider;
use App\Jobs\PublishArticleToWordPress;
use App\Models\Agency;
use App\Models\ApiIntegration;
use App\Models\Article;
use App\Models\Publication;
use App\Models\PublishingTarget;
use App\Models\Recipe;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class RecipeControllerTest extends TestCase
{
    use DatabaseMigrations;

    public function test_owner_can_create_view_and_update_recipe(): void
    {
        $agency = Agency::factory()->create();
        $owner = User::factory()->agencyOwner()->for($agency)->create();

        $this->actingAs($owner)->post(route('recipes.store'), [
            'category' => 'main',
            'title' => 'Mercimek Çorbası',
            'ingredients' => 'Mercimek, soğan, havuç ve baharatlar',
            'instructions' => 'Malzemeleri tencereye alın ve yumuşayana kadar pişirin.',
            'is_active' => '1',
        ])->assertRedirect(route('recipes.index'));

        $recipe = Recipe::query()->firstOrFail();
        $this->actingAs($owner)->get(route('recipes.show', $recipe))->assertOk()->assertSee('Mercimek Çorbası');
        $this->actingAs($owner)->put(route('recipes.update', $recipe), [
            'category' => 'cold',
            'title' => 'Soğuk Mercimek',
            'ingredients' => 'Mercimek, yoğurt, limon ve baharatlar',
            'instructions' => 'Malzemeleri karıştırıp soğuk servis edin.',
            'is_active' => '1',
        ])->assertRedirect(route('recipes.index'));

        $this->assertSame('cold', $recipe->fresh()->category);
    }

    public function test_recipe_can_be_sent_to_publication_center_with_seo(): void
    {
        $agency = Agency::factory()->create();
        $owner = User::factory()->agencyOwner()->for($agency)->create();
        $recipe = Recipe::factory()->create();

        $this->actingAs($owner)->post(route('recipes.publish', $recipe))->assertRedirect();

        $article = Article::query()->firstOrFail();
        $this->assertSame('recipe', data_get($article->editorial_metadata, 'content_type'));
        $this->assertNotNull($article->seoAnalysis);
        $this->assertSame('ASYA Tarif Havuzu', $article->source_name);
    }

    public function test_owner_can_generate_gemini_recipes_and_automatically_send_them_to_publication_center(): void
    {
        $recipes = collect(['main', 'cold', 'salad', 'dessert'])->map(fn (string $category): array => $category === 'main'
            ? [
                'category' => $category,
                'title' => 'Visneli Yaprak Sarma',
                'ingredients' => ['300 gram asma yapragi', '1 su bardagi pirinc', '2 adet sogan'],
                'instructions' => ['Yapragi sicak suda haşlayin.', 'Soganlari dograyip zeytinyaginda soteleyin.', 'Baharatlari ekleyin ve sarmalarin uzerine su gezdirin.', 'Kısık ateste pisirin.'],
            ]
            : [
                'category' => $category,
                'title' => $category.' tarifi',
                'ingredients' => ['2 adet sebze', '1 çay kaşığı baharat', '1 yemek kaşığı yağ'],
                'instructions' => ['Malzemeleri hazırlayın.', 'Uygun sürede pişirin.', 'Dinlendirip servis edin.'],
            ])->all();
        Http::preventStrayRequests();
        Http::fake([
            'https://93.184.216.34/v1/models/gemini-test:generateContent*' => Http::response([
                'candidates' => [['content' => ['parts' => [
                    ['text' => "Yanıt:\n"],
                    ['text' => json_encode(['recipes' => $recipes], JSON_UNESCAPED_UNICODE)],
                    ['text' => "\nBitti"],
                ]]]],
            ]),
        ]);
        $agency = Agency::factory()->create(['recipe_daily_quota' => 4]);
        $owner = User::factory()->agencyOwner()->for($agency)->create();
        ApiIntegration::factory()->for($agency)->create([
            'provider' => IntegrationProvider::GoogleGemini,
            'base_url' => 'https://93.184.216.34/v1/models',
            'model' => 'gemini-test',
            'auth_type' => IntegrationAuthType::ApiKeyHeader,
            'credential' => 'recipe-key',
            'is_active' => true,
        ]);
        PublishingTarget::factory()->for($agency)->create(['is_active' => true]);
        Queue::fake([PublishArticleToWordPress::class]);

        $this->actingAs($owner)->post(route('recipes.generate'))->assertRedirect()->assertSessionHas('success');

        $this->assertDatabaseCount('recipes', 4);
        $correctedRecipe = Recipe::query()->where('category', 'main')->firstOrFail();
        $this->assertSame('Vişneli Yaprak Sarma', $correctedRecipe->title);
        $this->assertSame('300 gram asma yaprağı 1 su bardağı pirinç 2 adet soğan', $correctedRecipe->ingredients);
        $this->assertSame('Yaprağı sıcak suda haşlayın. Soğanları doğrayıp zeytinyağında soteleyin. Baharatları ekleyin ve sarmaların üzerine su gezdirin. Kısık ateşte pişirin.', $correctedRecipe->instructions);
        $this->assertSame(4, Article::query()->where('agency_id', $agency->id)->where('editorial_metadata->content_type', 'recipe')->count());
        $this->assertStringContainsString('Yaprağı sıcak suda haşlayın.', Article::query()->where('title', 'like', 'Vişneli Yaprak Sarma%')->firstOrFail()->body);
        $this->assertSame(4, Publication::query()->where('agency_id', $agency->id)->count());
        Queue::assertPushed(PublishArticleToWordPress::class, 4);
        Http::assertSent(fn (Request $request): bool => data_get($request->data(), 'generationConfig.responseJsonSchema.properties.recipes.maxItems') === 4
            && data_get($request->data(), 'generationConfig.maxOutputTokens') === 2400);

        $this->actingAs($owner)->post(route('recipes.generate'))->assertRedirect()->assertSessionHasErrors('recipe_generation');
        Http::assertSentCount(1);
    }
}
