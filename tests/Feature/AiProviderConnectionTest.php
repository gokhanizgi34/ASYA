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
            'model' => 'gemini-3.5-flash-lite',
            'credential' => 'gemini-secret',
        ]);
        Http::preventStrayRequests();
        Http::fake([
            'https://93.184.216.34/*' => Http::response([
                'candidates' => [[
                    'content' => [
                        'parts' => [['text' => '{"ok":true}']],
                    ],
                ]],
            ]),
        ]);

        $successful = app(ApiIntegrationTester::class)->test($integration);

        $this->assertTrue($successful);
        Http::assertSent(fn (Request $request): bool => $request->url() === 'https://93.184.216.34/v1beta/models/gemini-3.5-flash-lite:generateContent?key=gemini-secret'
            && $request->method() === 'POST');
        $this->assertStringNotContainsString('gemini-secret', (string) LearnedRoute::query()->first()?->toJson());
    }

    public function test_retired_github_models_connection_is_disabled_without_network_call(): void
    {
        $integration = ApiIntegration::factory()->ai(IntegrationProvider::GitHubModels)->create([
            'base_url' => 'https://93.184.216.34/inference',
            'model' => 'openai/gpt-4.1-mini',
            'credential' => 'github-secret',
        ]);
        Http::preventStrayRequests();

        $this->assertFalse(app(ApiIntegrationTester::class)->test($integration));
        $integration->refresh();
        $this->assertFalse($integration->is_active);
        $this->assertSame(410, $integration->last_status_code);
        $this->assertStringContainsString('emekliye ayrıldı', $integration->last_error);
        Http::assertNothingSent();
    }
}
