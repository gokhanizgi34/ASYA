<?php

namespace Database\Factories;

use App\Models\Agency;
use App\Models\Campaign;
use App\Models\ScheduleEntry;
use App\Models\User;
use App\ScheduleAction;
use App\ScheduleStatus;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<ScheduleEntry> */
class ScheduleEntryFactory extends Factory
{
    public function definition(): array
    {
        return ['agency_id' => Agency::factory(), 'created_by' => User::factory(), 'publication_id' => null, 'campaign_id' => Campaign::factory(), 'action' => ScheduleAction::ActivateCampaign, 'status' => ScheduleStatus::Pending, 'active_key' => 'campaign:'.fake()->unique()->randomNumber(7).':activate_campaign', 'title' => fake()->sentence(), 'scheduled_for' => now()->addHour(), 'timezone' => 'Europe/Istanbul', 'attempt_count' => 0, 'failure_message' => null, 'started_at' => null, 'completed_at' => null];
    }

    public function due(): static
    {
        return $this->state(fn (): array => ['scheduled_for' => now()->subMinute()]);
    }

    public function failed(): static
    {
        return $this->state(fn (): array => ['status' => ScheduleStatus::Failed, 'failure_message' => 'Plan yürütülemedi.', 'completed_at' => now()]);
    }
}
