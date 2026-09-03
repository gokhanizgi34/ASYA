<?php

namespace Tests\Feature;

use App\IntegrationProvider;
use App\Models\ApiIntegration;
use App\Models\LearnedRoute;
use App\Services\ApiIntegrationTester;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class AiProviderConnectionTest extends TestCase
{
    use RefreshDatabase;

    public function test_anthropic_connection_uses_api_key_and_version_headers(): void
    {
        $integration = ApiIntegration::factory()->ai(IntegrationProvider::Anthropic)->create([
            'base_url' => 'https://93.184.216.34/v1/models',
            'credential' => 'anthropic-secret',
        ]);
        Http::preventStrayRequests();
        Http::fake(['https://93.184.216.34/*' => Http::response(['data' => []])]);

        $successful = app(ApiIntegrationTester::class)->test($integration);

        $this->assertTrue($successful);
        Http::assertSent(fn (Request $request): bool => $request->hasHeader('x-api-key', 'anthropic-secret')
            && $request->hasHeader('anthropic-version', '2023-06-01'));
    }

    public function test_gemini_connection_uses_api_key_query_parameter_without_leaking_it_to_learned_route(): void
    {
        $integration = ApiIntegration::factory()->ai(IntegrationProvider::GoogleGemini)->create([
            'base_url' => 'https://93.184.216.34/v1beta/models',
            'credential' => 'gemini-secret',
        ]);
        Http::preventStrayRequests();
        Http::fake(['https://93.184.216.34/*' => Http::response(['models' => []])]);

        $successful = app(ApiIntegrationTester::class)->test($integration);

        $this->assertTrue($successful);
        Http::assertSent(fn (Request $request): bool => $request->url() === 'https://93.184.216.34/v1beta/models?key=gemini-secret');
        $this->assertStringNotContainsString('gemini-secret', (string) LearnedRoute::query()->first()?->toJson());
    }
}
