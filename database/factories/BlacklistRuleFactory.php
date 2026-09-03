<?php

namespace Database\Factories;

use App\BlacklistAction;
use App\BlacklistRuleType;
use App\Models\Agency;
use App\Models\BlacklistRule;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/** @extends Factory<BlacklistRule> */
class BlacklistRuleFactory extends Factory
{
    public function definition(): array
    {
        $pattern = fake()->unique()->words(2, true);

        return [
            'agency_id' => Agency::factory(),
            'created_by' => null,
            'type' => BlacklistRuleType::Keyword,
            'pattern' => $pattern,
            'normalized_pattern' => Str::lower(Str::squish($pattern)),
            'action' => BlacklistAction::Block,
            'reason' => fake()->sentence(),
            'hit_count' => 0,
            'last_hit_at' => null,
            'is_active' => true,
        ];
    }

    public function review(): static
    {
        return $this->state(fn (): array => ['action' => BlacklistAction::Review]);
    }

    public function inactive(): static
    {
        return $this->state(fn (): array => ['is_active' => false]);
    }
}
