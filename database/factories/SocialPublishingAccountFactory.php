<?php

namespace Database\Factories;

use App\Models\Agency;
use App\Models\SocialPublishingAccount;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<SocialPublishingAccount> */
class SocialPublishingAccountFactory extends Factory
{
    public function definition(): array
    {
        return [
            'agency_id' => Agency::factory(),
            'created_by' => null,
            'name' => fake()->unique()->words(2, true),
            'platform' => 'x',
            'account_handle' => '@'.fake()->unique()->userName(),
            'access_token' => 'local-test-token',
            'publish_mode' => 'local_sandbox',
            'is_active' => true,
            'last_published_at' => null,
        ];
    }
}
