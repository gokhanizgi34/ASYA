<?php

namespace Database\Factories;

use App\Models\Agency;
use App\Models\SupportTicket;
use App\Models\User;
use App\SupportTicketPriority;
use App\SupportTicketStatus;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<SupportTicket> */
class SupportTicketFactory extends Factory
{
    public function definition(): array
    {
        return [
            'agency_id' => Agency::factory(),
            'user_id' => function (array $attributes): int {
                return User::factory()->create(['agency_id' => $attributes['agency_id']])->id;
            },
            'handled_by' => null,
            'ticket_number' => null,
            'category' => 'technical',
            'priority' => SupportTicketPriority::Normal,
            'status' => SupportTicketStatus::Open,
            'subject' => fake()->sentence(),
            'message' => fake()->paragraphs(2, true),
            'admin_note' => null,
            'handled_at' => null,
        ];
    }
}
