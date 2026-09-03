<?php

namespace Tests\Feature;

use App\IntegrationAuthType;
use App\IntegrationProvider;
use App\Models\Agency;
use App\Models\ApiIntegration;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ApiIntegrationControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_is_redirected_and_editor_is_forbidden(): void
    {
        $agency = Agency::factory()->create();
        $editor = User::factory()->editor()->for($agency)->create();

        $this->get(route('api-integrations.index'))->assertRedirect(route('login'));
        $this->actingAs($editor)->get(route('api-integrations.index'))->assertForbidden();
    }

    public function test_owner_can_create_ai_integration_with_only_provider_and_api_key(): void
    {
        $agency = Agency::factory()->create();
        $owner = User::factory()->agencyOwner()->for($agency)->create();

        $this->actingAs($owner)->get(route('api-integrations.create', ['provider' => IntegrationProvider::OpenAi->value]))
            ->assertOk()
            ->assertSee('name="provider"', false)
            ->assertSee('name="credential"', false)
            ->assertDontSee('name="base_url"', false)
            ->assertDontSee('name="model"', false)
            ->assertDontSee('name="timeout_seconds"', false);

        $this->actingAs($owner)->post(route('api-integrations.store'), [
            'provider' => IntegrationProvider::OpenAi->value,
        ])->assertSessionHasErrors(['credential' => 'API anahtarı zorunludur.']);

        $this->assertDatabaseCount('api_integrations', 0);

        $this->actingAs($owner)->post(route('api-integrations.store'), [
            'provider' => IntegrationProvider::OpenAi->value,
            'credential' => 'k',
        ])->assertRedirect(route('api-integrations.index'));

        $integration = ApiIntegration::query()->sole();
        $this->assertSame($agency->id, $integration->agency_id);
        $this->assertSame('OpenAI', $integration->name);
        $this->assertSame('gpt-5', $integration->model);
        $this->assertSame('https://api.openai.com/v1/models', $integration->base_url);
        $this->assertSame(IntegrationAuthType::Bearer, $integration->auth_type);
        $this->assertSame(15, $integration->timeout_seconds);
        $this->assertTrue($integration->is_active);
        $this->assertTrue($integration->is_default);
        $this->assertSame('k', $integration->credential);
        $this->assertNotSame('k', DB::table('api_integrations')->value('credential'));
    }

    public function test_owner_can_create_x_trends_integration_with_only_api_token(): void
    {
        $agency = Agency::factory()->create();
        $owner = User::factory()->agencyOwner()->for($agency)->create();

        $this->actingAs($owner)->get(route('api-integrations.create', ['provider' => IntegrationProvider::XTrends->value]))
            ->assertOk()
            ->assertSee('X Gündem (Trends API)');

        $this->actingAs($owner)->post(route('api-integrations.store'), [
            'provider' => IntegrationProvider::XTrends->value,
            'credential' => 'x-bearer-token',
        ])->assertRedirect(route('api-integrations.index'));

        $integration = ApiIntegration::query()->sole();
        $this->assertSame(IntegrationProvider::XTrends, $integration->provider);
        $this->assertSame('X Gündem (Trends API)', $integration->name);
        $this->assertSame('https://api.x.com/2/trends/by/woeid', $integration->base_url);
        $this->assertSame(IntegrationAuthType::Bearer, $integration->auth_type);
        $this->assertSame('x-bearer-token', $integration->credential);
        $this->assertTrue($integration->is_active);
    }

    public function test_owner_creates_tenant_scoped_integration_with_encrypted_credential(): void
    {
        $agency = Agency::factory()->create();
        $otherAgency = Agency::factory()->create();
        $owner = User::factory()->agencyOwner()->for($agency)->create();

        $this->actingAs($owner)->post(route('api-integrations.store'), $this->validPayload([
            'agency_id' => $otherAgency->id,
            'name' => 'Haber API',
            'credential' => 'plain-secret-token',
        ]))->assertRedirect(route('api-integrations.index'));

        $integration = ApiIntegration::query()->sole();
        $this->assertSame($agency->id, $integration->agency_id);
        $this->assertSame('plain-secret-token', $integration->credential);
        $this->assertNotSame('plain-secret-token', DB::table('api_integrations')->value('credential'));
    }

    public function test_owner_sees_only_own_integrations_and_cannot_edit_foreign_one(): void
    {
        $agency = Agency::factory()->create();
        $otherAgency = Agency::factory()->create();
        $owner = User::factory()->agencyOwner()->for($agency)->create();
        ApiIntegration::factory()->for($agency)->create(['name' => 'OWN-INTEGRATION']);
        $foreign = ApiIntegration::factory()->for($otherAgency)->create(['name' => 'FOREIGN-INTEGRATION']);

        $this->actingAs($owner)->get(route('api-integrations.index'))->assertOk()->assertSee('OWN-INTEGRATION')->assertDontSee('FOREIGN-INTEGRATION');
        $this->actingAs($owner)->get(route('api-integrations.edit', $foreign))->assertForbidden();
    }

    public function test_private_url_and_unsafe_header_are_rejected(): void
    {
        $agency = Agency::factory()->create();
        $owner = User::factory()->agencyOwner()->for($agency)->create();

        $this->actingAs($owner)->post(route('api-integrations.store'), $this->validPayload([
            'base_url' => 'http://127.0.0.1/private',
            'auth_type' => IntegrationAuthType::ApiKeyHeader->value,
            'api_key_header' => 'Host',
        ]))->assertSessionHasErrors(['base_url', 'api_key_header']);

        $this->assertDatabaseCount('api_integrations', 0);
    }

    public function test_blank_credential_on_update_preserves_existing_secret(): void
    {
        $agency = Agency::factory()->create();
        $owner = User::factory()->agencyOwner()->for($agency)->create();
        $integration = ApiIntegration::factory()->for($agency)->create(['credential' => 'existing-secret']);

        $this->actingAs($owner)->put(route('api-integrations.update', $integration), $this->validPayload([
            'name' => 'Updated API',
            'credential' => '',
        ]))->assertRedirect(route('api-integrations.index'));

        $this->assertSame('existing-secret', $integration->refresh()->credential);
        $this->assertSame('Updated API', $integration->name);
    }

    public function test_owner_can_test_and_soft_delete_own_integration(): void
    {
        $agency = Agency::factory()->create();
        $owner = User::factory()->agencyOwner()->for($agency)->create();
        $integration = ApiIntegration::factory()->for($agency)->create([
            'base_url' => 'https://93.184.216.34/health',
            'auth_type' => IntegrationAuthType::None,
            'credential' => null,
        ]);
        Http::fake(['*' => Http::response([], 204)]);

        $this->actingAs($owner)->post(route('api-integrations.test', $integration))->assertRedirect()->assertSessionHas('success');
        $this->actingAs($owner)->delete(route('api-integrations.destroy', $integration))->assertRedirect(route('api-integrations.index'));
        $this->assertSoftDeleted('api_integrations', ['id' => $integration->id]);
    }

    /** @param array<string, mixed> $overrides */
    private function validPayload(array $overrides = []): array
    {
        return array_replace([
            'agency_id' => null,
            'name' => 'Test API',
            'provider' => IntegrationProvider::GenericRest->value,
            'base_url' => 'https://api.example.com/health',
            'auth_type' => IntegrationAuthType::Bearer->value,
            'username' => null,
            'api_key_header' => null,
            'credential' => 'secret-token-value',
            'timeout_seconds' => 15,
            'is_active' => true,
        ], $overrides);
    }
}
