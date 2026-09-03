<?php

namespace Tests\Feature\Auth;

use App\Models\Agency;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthenticationTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_screen_can_be_rendered(): void
    {
        $this->get(route('login'))
            ->assertOk()
            ->assertSee('Tekrar hoş geldiniz')
            ->assertDontSee('Genel Bakış')
            ->assertDontSee('Çıkış');
    }

    public function test_active_user_can_authenticate(): void
    {
        $user = User::factory()->create(['password' => 'GuvenliParola123']);

        $response = $this->post(route('login.store'), [
            'email' => strtoupper($user->email),
            'password' => 'GuvenliParola123',
        ]);

        $response->assertRedirect(route('dashboard'));
        $this->assertAuthenticatedAs($user);
    }

    public function test_user_cannot_authenticate_with_invalid_password(): void
    {
        $user = User::factory()->create();

        $this->post(route('login.store'), [
            'email' => $user->email,
            'password' => 'yanlis-parola',
        ])->assertSessionHasErrors('email');

        $this->assertGuest();
    }

    public function test_inactive_user_cannot_authenticate(): void
    {
        $user = User::factory()->inactive()->create(['password' => 'GuvenliParola123']);

        $this->post(route('login.store'), [
            'email' => $user->email,
            'password' => 'GuvenliParola123',
        ])->assertSessionHasErrors('email');

        $this->assertGuest();
    }

    public function test_deactivated_authenticated_user_is_logged_out(): void
    {
        $user = User::factory()->inactive()->create();

        $this->actingAs($user)->get(route('dashboard'))
            ->assertRedirect(route('login'))
            ->assertSessionHasErrors('email');

        $this->assertGuest();
    }

    public function test_user_of_inactive_agency_cannot_authenticate(): void
    {
        $agency = Agency::factory()->inactive()->create();
        $user = User::factory()->editor()->for($agency)->create(['password' => 'GuvenliParola123']);

        $this->post(route('login.store'), [
            'email' => $user->email,
            'password' => 'GuvenliParola123',
        ])->assertSessionHasErrors('email');

        $this->assertGuest();
    }

    public function test_authenticated_user_is_logged_out_when_agency_is_deactivated(): void
    {
        $agency = Agency::factory()->inactive()->create();
        $user = User::factory()->editor()->for($agency)->create();

        $this->actingAs($user)->get(route('dashboard'))->assertRedirect(route('login'));
        $this->assertGuest();
    }

    public function test_user_can_logout(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->post(route('logout'))->assertRedirect(route('login'));

        $this->assertGuest();
    }

    public function test_root_is_an_authentication_gate_for_guests_and_authenticated_users(): void
    {
        $this->get(route('home'))->assertRedirect(route('login'));

        $user = User::factory()->create();

        $this->actingAs($user)->get(route('home'))->assertRedirect(route('dashboard'));
        $this->actingAs($user)->get(route('login'))->assertRedirect(route('dashboard'));
    }
}
