<?php

namespace App;

use App\Models\Recipe;
use Illuminate\Support\Collection;

class DailyMenuBuilder
{
    /** @return Collection<int, Recipe> */
    public function build(): Collection
    {
        $categories = ['main', 'cold', 'salad', 'dessert'];

        return collect($categories)->mapWithKeys(function (string $category): array {
            $recipe = Recipe::query()
                ->where('category', $category)
                ->where('is_active', true)
                ->where(function ($query): void {
                    $query->whereNull('last_selected_at')->orWhereDate('last_selected_at', '<', today());
                })
                ->orderBy('last_selected_at')
                ->orderBy('id')
                ->lockForUpdate()
                ->first();

            if (! $recipe) {
                return [$category => null];
            }

            $recipe->update(['last_selected_at' => now()]);

            return [$category => $recipe];
        })->filter();
    }
}
