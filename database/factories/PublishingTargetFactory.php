<?php

namespace Database\Factories;

use App\Models\Agency;
use App\Models\PublishingTarget;
use App\PublishingProtocol;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<PublishingTarget> */
class PublishingTargetFactory extends Factory
{
    public function definition(): array
    {
        return [
            'agency_id' => Agency::factory(),
            'name' => fake()->unique()->company().' WordPress',
            'base_url' => 'https://'.fake()->unique()->domainName(),
            'protocol' => PublishingProtocol::WordPressRest,
            'username' => fake()->userName(),
            'credential' => 'test-application-password',
            'default_author_id' => 1,
            'default_category_ids' => [1],
            'default_tag_ids' => [],
            'is_active' => true,
            'last_connected_at' => null,
            'last_error' => null,
        ];
    }
}
