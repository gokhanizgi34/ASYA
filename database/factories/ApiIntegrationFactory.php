<?php

namespace Database\Factories;

use App\IntegrationAuthType;
use App\IntegrationProvider;
use App\Models\Agency;
use App\Models\ApiIntegration;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<ApiIntegration> */
class ApiIntegrationFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'agency_id' => Agency::factory(),
            'name' => fake()->unique()->company().' API',
            'provider' => IntegrationProvider::GenericRest,
            'model' => null,
            'priority' => 50,
            'is_default' => false,
            'visual_enabled' => false,
            'base_url' => 'https://'.fake()->domainName().'/api/health',
            'auth_type' => IntegrationAuthType::Bearer,
            'username' => null,
            'api_key_header' => null,
            'credential' => fake()->sha256(),
            'timeout_seconds' => 15,
            'is_active' => true,
        ];
    }

    public function ai(IntegrationProvider $provider = IntegrationProvider::OpenAi): static
    {
        return $this->state(fn (): array => [
            'provider' => $provider,
            'model' => $provider->suggestedModels()[0] ?? 'default-model',
            'base_url' => $provider->defaultBaseUrl(),
            'auth_type' => $provider->defaultAuthType(),
            'api_key_header' => $provider->defaultApiKeyHeader(),
        ]);
    }
}
