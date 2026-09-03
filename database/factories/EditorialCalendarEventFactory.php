<?php

namespace Database\Factories;

use App\Models\Agency;
use App\Models\EditorialCalendarEvent;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<EditorialCalendarEvent> */
class EditorialCalendarEventFactory extends Factory
{
    public function definition(): array
    {
        $eventDate = fake()->dateTimeBetween('+1 month', '+1 year');

        return ['agency_id' => Agency::factory(), 'created_by' => User::factory(), 'event_date' => $eventDate, 'content_due_at' => (clone $eventDate)->modify('-14 days'), 'title' => fake()->unique()->sentence(3), 'seo_topics' => [fake()->sentence(4), fake()->sentence(5)], 'status' => 'planned', 'ai_provider' => 'Test AI'];
    }
}
