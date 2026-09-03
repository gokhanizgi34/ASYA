<?php

namespace Tests\Feature;

use App\IntegrationProvider;
use App\Models\Agency;
use App\Models\User;
use App\Services\AiIntegrationRegistry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MultiAiIntegrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_agency_can_store_multiple_ai_providers_and_registry_automatically_selects_the_first_as_default(): void
    {
        $agency = Agency::factory()->create();
        $owner = User::factory()->agencyOwner()->for($agency)->create();

        $this->actingAs($owner)->post(route('api-integrations.store'), $this->payload(IntegrationProvider::OpenAi))->assertRedirect();
        $this->actingAs($owner)->post(route('api-integrations.store'), $this->payload(IntegrationProvider::Anthropic))->assertRedirect();

        $integrations = app(AiIntegrationRegistry::class)->forAgency($agency->id);

        $this->assertCount(2, $integrations);
        $this->assertSame(IntegrationProvider::OpenAi, $integrations->first()->provider);
        $this->assertTrue($integrations->first()->is_default);
        $this->assertSame('gpt-5', $integrations->first()->model);
        $this->assertFalse($integrations->last()->is_default);
        $this->assertSame('claude-sonnet-4-5', $integrations->last()->model);
    }

    /** @return array<string, string> */
    private function payload(IntegrationProvider $provider): array
    {
        return [
            'provider' => $provider->value,
            'credential' => 'secret-api-key',
        ];
    }
}
