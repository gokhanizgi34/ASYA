<?php

namespace Tests\Feature;

use App\IntegrationAuthType;
use App\Models\ApiIntegration;
use App\Models\LearnedRoute;
use App\Services\ApiIntegrationTester;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ApiIntegrationTesterTest extends TestCase
{
    use RefreshDatabase;

    public function test_successful_bearer_connection_updates_health_and_learns_route_without_secret(): void
    {
        $integration = ApiIntegration::factory()->create([
            'base_url' => 'https://93.184.216.34/api/health?probe=1',
            'auth_type' => IntegrationAuthType::Bearer,
            'credential' => 'very-secret-token',
        ]);
        Http::preventStrayRequests();
        Http::fake(['https://93.184.216.34/*' => Http::response(['ok' => true], 200)]);

        $successful = app(ApiIntegrationTester::class)->test($integration);

        $this->assertTrue($successful);
        $integration->refresh();
        $this->assertSame(200, $integration->last_status_code);
        $this->assertNull($integration->last_error);
        $this->assertNotNull($integration->last_tested_at);
        Http::assertSent(fn (Request $request): bool => $request->hasHeader('Authorization', 'Bearer very-secret-token'));
        $route = LearnedRoute::query()->sole();
        $this->assertSame('/api/health', $route->path_pattern);
        $this->assertStringNotContainsString('probe', $route->path_pattern);
        $this->assertStringNotContainsString('very-secret-token', $route->toJson());
    }

    public function test_http_failure_is_persisted_and_counted_as_failed_observation(): void
    {
        $integration = ApiIntegration::factory()->create([
            'base_url' => 'https://93.184.216.34/status',
            'auth_type' => IntegrationAuthType::None,
            'credential' => null,
        ]);
        Http::fake(['*' => Http::response(['error' => true], 503)]);

        $this->assertFalse(app(ApiIntegrationTester::class)->test($integration));

        $integration->refresh();
        $this->assertSame(503, $integration->last_status_code);
        $this->assertStringContainsString('503', $integration->last_error);
        $this->assertDatabaseHas('learned_routes', ['agency_id' => $integration->agency_id, 'failed_count' => 1, 'last_status_code' => 503]);
    }

    public function test_private_network_target_is_rejected_without_http_request(): void
    {
        $integration = ApiIntegration::factory()->create(['base_url' => 'http://127.0.0.1/admin']);
        Http::preventStrayRequests();

        $this->assertFalse(app(ApiIntegrationTester::class)->test($integration));
        $this->assertStringContainsString('Özel veya ayrılmış', (string) $integration->fresh()->last_error);
        Http::assertNothingSent();
        $this->assertDatabaseCount('learned_routes', 0);
    }
}
