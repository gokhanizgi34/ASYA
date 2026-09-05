<?php

namespace Database\Factories;

use App\Models\Agency;
use App\Models\EditorialStyleProfile;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<EditorialStyleProfile> */
class EditorialStyleProfileFactory extends Factory
{
    protected $model = EditorialStyleProfile::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'agency_id' => Agency::factory(), 'created_by' => User::factory(), 'name' => 'Ajans yazım dili',
            'sample_text' => fake()->paragraphs(4, true), 'learned_terms' => ['İstanbul', 'gündem', 'açıklama'],
            'replacements' => ['gerçekleştirildi' => 'düzenlendi'], 'forbidden_terms' => ['kaynağına göre'],
            'daily_quota' => 50, 'destination' => 'publish', 'is_active' => true,
        ];
    }
}
