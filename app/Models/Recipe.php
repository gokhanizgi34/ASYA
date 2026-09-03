<?php

namespace App\Models;

use Database\Factories\RecipeFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['category', 'title', 'ingredients', 'instructions', 'origin', 'generated_for_agency_id', 'generated_at', 'is_active', 'last_selected_at'])]
class Recipe extends Model
{
    /** @use HasFactory<RecipeFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'last_selected_at' => 'datetime',
            'generated_at' => 'datetime',
            'is_active' => 'boolean',
        ];
    }
}
