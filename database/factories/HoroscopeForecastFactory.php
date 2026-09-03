<?php

namespace Database\Factories;

use App\HoroscopeStatus;
use App\Models\Agency;
use App\Models\HoroscopeForecast;
use App\ZodiacSign;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<HoroscopeForecast> */
class HoroscopeForecastFactory extends Factory
{
    public function definition(): array
    {
        return [
            'agency_id' => Agency::factory(), 'created_by' => null, 'updated_by' => null,
            'forecast_date' => today(), 'sign' => fake()->randomElement(ZodiacSign::cases()),
            'status' => HoroscopeStatus::Draft, 'general' => fake()->paragraph(3),
            'love' => fake()->paragraph(), 'career' => fake()->paragraph(), 'money' => fake()->paragraph(),
            'health' => fake()->paragraph(), 'lucky_color' => fake()->safeColorName(), 'lucky_number' => fake()->numberBetween(1, 99),
            'published_at' => null,
        ];
    }
}
