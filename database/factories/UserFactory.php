<?php

namespace Database\Factories;

use App\Models\User;
use App\UserRole;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    protected static ?string $password;

    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
            'password' => static::$password ??= Hash::make('password'),
            'role' => UserRole::Editor,
            'is_active' => true,
            'remember_token' => Str::random(10),
        ];
    }

    public function unverified(): static
    {
        return $this->state(fn (array $attributes): array => [
            'email_verified_at' => null,
        ]);
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes): array => [
            'is_active' => false,
        ]);
    }

    public function systemAdministrator(): static
    {
        return $this->state(fn (array $attributes): array => [
            'role' => UserRole::SystemAdministrator,
        ]);
    }

    public function agencyOwner(): static
    {
        return $this->state(fn (array $attributes): array => [
            'role' => UserRole::AgencyOwner,
        ]);
    }

    public function editor(): static
    {
        return $this->state(fn (array $attributes): array => [
            'role' => UserRole::Editor,
        ]);
    }
}
