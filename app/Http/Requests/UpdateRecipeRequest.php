<?php

namespace App\Http\Requests;

use App\Models\Recipe;

class UpdateRecipeRequest extends StoreRecipeRequest
{
    public function authorize(): bool
    {
        $recipe = $this->route('recipe');

        return $recipe instanceof Recipe && ($this->user()?->can('update', $recipe) ?? false);
    }
}
