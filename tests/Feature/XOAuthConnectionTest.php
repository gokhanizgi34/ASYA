<?php

namespace Tests\Feature;

use App\Models\Agency;
use App\Models\SocialPublishingAccount;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class XOAuthConnectionTest extends TestCase
{
    use RefreshDatabase;

    public function test_agency_owner_is_redirected_to_x_with_pkce_and_minimum_publish_scopes(): void
    {
        $this->configureX();
        $agency = Agency::factory()->create();
        $owner = User::factory()->agencyOwner()->for($agency)->create();

        $response = $this->actingAs($owner)->get(route('x-oauth.connect', ['agency_id' => $agency->id]));

        $response->assertRedirectContains('https://x.com/i/oauth2/authorize?');
        $response->assertSessionHas('x_oauth_connection');
        $location = urldecode((string) $response->headers->get('Location'));
        $this->assertStringContainsString('code_challenge_method=S256', $location);
        $this->assertStringContainsString('tweet.write', $location);
        $this->assertStringContainsString('offline.access', $location);
    }

    public function test_editor_cannot_start_x_account_connection_for_an_agency(): void
    {
        $this->configureX();
        $agency = Agency::factory()->create();
        $editor = User::factory()->editor()->for($agency)->create();

        $this->actingAs($editor)
            ->get(route('x-oauth.connect', ['agency_id' => $agency->id]))
            ->assertForbidden();
    }

    public function test_valid_x_callback_encrypts_tokens_and_connects_verified_account(): void
    {
        $this->configureX();
        Http::preventStrayRequests();
        Http::fake([
            'https://api.x.com/2/oauth2/token' => Http::response([
                'access_token' => 'oauth-access-token',
                'refresh_token' => 'oauth-refresh-token',
                'expires_in' => 7200,
                'token_type' => 'bearer',
            ]),
            'https://api.x.com/2/users/me' => Http::response([
                'data' => ['id' => '123456789', 'name' => 'Asya Haber', 'username' => 'asyahaber'],
            ]),
        ]);
        $agency = Agency::factory()->create();
        $owner = User::factory()->agencyOwner()->for($agency)->create();
        $session = [
            'state' => 'verified-state',
            'code_verifier' => str_repeat('v', 64),
            'agency_id' => $agency->id,
            'created_at' => now()->timestamp,
        ];

        $response = $this->actingAs($owner)
            ->withSession(['x_oauth_connection' => $session])
            ->get(route('x-oauth.callback', ['state' => 'verified-state', 'code' => 'authorization-code']));

        $response->assertRedirect(route('social-publishing.index'))->assertSessionHas('success');
        $account = SocialPublishingAccount::query()->sole();
        $this->assertSame('@asyahaber', $account->account_handle);
        $this->assertSame('123456789', $account->x_user_id);
        $this->assertSame('oauth2_pkce', $account->auth_type);
        $this->assertSame('oauth-access-token', $account->access_token);
        $this->assertSame('oauth-refresh-token', $account->refresh_token);
        $raw = DB::table('social_publishing_accounts')->first();
        $this->assertStringNotContainsString('oauth-access-token', (string) $raw->access_token);
        $this->assertStringNotContainsString('oauth-refresh-token', (string) $raw->refresh_token);
        Http::assertSentCount(2);
        Http::assertSent(fn (Request $request): bool => $request->url() === 'https://api.x.com/2/oauth2/token'
            && $request['grant_type'] === 'authorization_code'
            && $request['code_verifier'] === str_repeat('v', 64));
    }

    public function test_callback_rejects_invalid_state_without_contacting_x(): void
    {
        $this->configureX();
        Http::preventStrayRequests();
        $agency = Agency::factory()->create();
        $owner = User::factory()->agencyOwner()->for($agency)->create();

        $this->actingAs($owner)
            ->withSession(['x_oauth_connection' => [
                'state' => 'expected-state',
                'code_verifier' => str_repeat('v', 64),
                'agency_id' => $agency->id,
                'created_at' => now()->timestamp,
            ]])
            ->get(route('x-oauth.callback', ['state' => 'wrong-state', 'code' => 'authorization-code']))
            ->assertRedirect(route('social-publishing.index'))
            ->assertSessionHas('error');

        Http::assertNothingSent();
        $this->assertDatabaseCount('social_publishing_accounts', 0);
    }

    private function configureX(): void
    {
        config()->set([
            'services.x.client_id' => 'test-client-id',
            'services.x.client_secret' => 'test-client-secret',
            'services.x.redirect_uri' => 'https://asya.test/sosyal-yayinci/x/geri-donus',
        ]);
    }
}
