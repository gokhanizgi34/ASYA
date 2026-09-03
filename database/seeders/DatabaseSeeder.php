<?php

namespace Database\Seeders;

use App\Models\User;
use App\UserRole;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        $this->call([
            AiPromptSeeder::class,
            InitialAgencySeeder::class,
        ]);

        $name = config('asya.initial_admin.name');
        $email = config('asya.initial_admin.email');
        $password = config('asya.initial_admin.password');

        if (! is_string($name) || ! is_string($email) || ! is_string($password)) {
            return;
        }

        User::query()->updateOrCreate(
            ['email' => $email],
            [
                'name' => $name,
                'password' => $password,
                'role' => UserRole::SystemAdministrator,
                'is_active' => true,
                'email_verified_at' => now(),
            ],
        );
    }
}
