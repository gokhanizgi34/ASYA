<?php

namespace Tests\Feature;

use App\IntegrationAuthType;
use App\IntegrationProvider;
use App\Models\Agency;
use App\Models\ApiIntegration;
use App\Models\Article;
use App\Models\Recipe;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\Http;
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

    public function test_owner_can_manually_generate_four_text_recipes_and_quota_blocks_second_run(): void
    {
        Http::fake(['https://93.184.216.34/v1/chat/completions' => Http::response(['choices' => [['message' => ['content' => json_encode(['recipes' => collect(['main', 'cold', 'salad', 'dessert'])->map(fn (string $category): array => ['category' => $category, 'title' => $category.' tarifi', 'ingredients' => ['Sebze', 'baharat', 'yağ'], 'instructions' => ['Malzemeleri hazırlayın.', 'Pişirin ve servis edin.']])->all()], JSON_UNESCAPED_UNICODE)]]]])]);
        $agency = Agency::factory()->create(['recipe_daily_quota' => 4]);
        $owner = User::factory()->agencyOwner()->for($agency)->create();
        ApiIntegration::factory()->for($agency)->create(['provider' => IntegrationProvider::OpenAi, 'base_url' => 'https://93.184.216.34/v1/models', 'auth_type' => IntegrationAuthType::Bearer, 'credential' => 'recipe-key', 'is_active' => true]);

        $this->actingAs($owner)->post(route('recipes.generate'))->assertRedirect()->assertSessionHas('success');
        $this->assertDatabaseCount('recipes', 4);
        $this->actingAs($owner)->post(route('recipes.generate'))->assertRedirect()->assertSessionHasErrors('recipe_generation');
        Http::assertSentCount(1);
    }
}
