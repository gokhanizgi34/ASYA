<?php

namespace Tests\Feature;

use App\HttpMethod;
use App\Models\Agency;
use App\Models\LearnedRoute;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LearnedRouteControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_is_redirected_and_editor_is_forbidden(): void
    {
        $agency = Agency::factory()->create();
        $editor = User::factory()->editor()->for($agency)->create();

        $this->get(route('learned-routes.index'))->assertRedirect(route('login'));
        $this->actingAs($editor)->get(route('learned-routes.index'))->assertForbidden();
    }

    public function test_owner_sees_only_own_learned_routes(): void
    {
        $agency = Agency::factory()->create();
        $otherAgency = Agency::factory()->create();
        $owner = User::factory()->agencyOwner()->for($agency)->create();
        LearnedRoute::factory()->for($agency)->create(['host' => 'own.example.com']);
        LearnedRoute::factory()->for($otherAgency)->create(['host' => 'other.example.com']);

        $this->actingAs($owner)
            ->get(route('learned-routes.index'))
            ->assertOk()
            ->assertSee('own.example.com')
            ->assertDontSee('other.example.com');
    }

    public function test_system_administrator_can_see_all_routes(): void
    {
        $administrator = User::factory()->systemAdministrator()->create();
        LearnedRoute::factory()->create(['host' => 'tenant.example.com']);
        LearnedRoute::factory()->create(['host' => 'second.example.com']);

        $this->actingAs($administrator)
            ->get(route('learned-routes.index'))
            ->assertOk()
            ->assertSee('tenant.example.com')
            ->assertSee('second.example.com');
    }

    public function test_owner_can_toggle_own_route_but_not_another_tenants_route(): void
    {
        $agency = Agency::factory()->create();
        $otherAgency = Agency::factory()->create();
        $owner = User::factory()->agencyOwner()->for($agency)->create();
        $ownRoute = LearnedRoute::factory()->for($agency)->create();
        $foreignRoute = LearnedRoute::factory()->for($otherAgency)->create();

        $this->actingAs($owner)
            ->patch(route('learned-routes.status', $ownRoute), ['is_enabled' => false])
            ->assertRedirect();
        $this->assertFalse($ownRoute->refresh()->is_enabled);

        $this->actingAs($owner)
            ->patch(route('learned-routes.status', $foreignRoute), ['is_enabled' => false])
            ->assertForbidden();
        $this->assertTrue($foreignRoute->refresh()->is_enabled);
    }

    public function test_method_and_text_filters_are_applied(): void
    {
        $agency = Agency::factory()->create();
        $owner = User::factory()->agencyOwner()->for($agency)->create();
        LearnedRoute::factory()->for($agency)->create(['host' => 'api.example.com', 'method' => HttpMethod::Get]);
        LearnedRoute::factory()->for($agency)->create(['host' => 'write.example.com', 'method' => HttpMethod::Post]);

        $this->actingAs($owner)
            ->get(route('learned-routes.index', ['method' => 'GET', 'q' => 'api']))
            ->assertOk()
            ->assertSee('api.example.com')
            ->assertDontSee('write.example.com');
    }
}
