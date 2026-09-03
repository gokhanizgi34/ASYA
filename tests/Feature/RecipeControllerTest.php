<?php

namespace Tests\Feature;

use App\Models\Agency;
use App\Models\Article;
use App\Models\Recipe;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseMigrations;
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
}
