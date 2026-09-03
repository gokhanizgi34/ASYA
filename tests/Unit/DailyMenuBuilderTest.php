<?php

namespace Tests\Unit;

use App\DailyMenuBuilder;
use App\Models\Recipe;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Tests\TestCase;

class DailyMenuBuilderTest extends TestCase
{
    use DatabaseMigrations;

    public function test_build_selects_one_recipe_per_category_and_marks_them(): void
    {
        foreach (['main', 'cold', 'salad', 'dessert'] as $category) {
            Recipe::factory()->create(['category' => $category]);
        }

        $menu = app(DailyMenuBuilder::class)->build();

        $this->assertCount(4, $menu);
        $this->assertSame(4, Recipe::query()->whereNotNull('last_selected_at')->count());
        $this->assertSame(0, app(DailyMenuBuilder::class)->build()->count());
    }
}
