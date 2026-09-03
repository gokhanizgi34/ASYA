<?php

namespace Tests\Feature\Http\Controllers;

use App\Models\Agency;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserStatusControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_system_administrator_can_deactivate_and_reactivate_user(): void
    {
        $administrator = User::factory()->systemAdministrator()->create();
        $user = User::factory()->create();

        $this->actingAs($administrator)->patch(route('users.status.update', $user), ['is_active' => '0'])->assertRedirect(route('users.index'));
        $this->assertFalse($user->fresh()->is_active);
        $this->actingAs($administrator)->patch(route('users.status.update', $user), ['is_active' => '1'])->assertRedirect(route('users.index'));
        $this->assertTrue($user->fresh()->is_active);
    }

    public function test_agency_owner_can_change_only_own_editor_status(): void
    {
        $ownAgency = Agency::factory()->create();
        $otherAgency = Agency::factory()->create();
        $owner = User::factory()->agencyOwner()->for($ownAgency)->create();
        $ownEditor = User::factory()->editor()->for($ownAgency)->create();
        $otherEditor = User::factory()->editor()->for($otherAgency)->create();

        $this->actingAs($owner)->patch(route('users.status.update', $ownEditor), ['is_active' => '0'])->assertRedirect(route('users.index'));
        $this->assertFalse($ownEditor->fresh()->is_active);
        $this->actingAs($owner)->patch(route('users.status.update', $otherEditor), ['is_active' => '0'])->assertForbidden();
        $this->assertTrue($otherEditor->fresh()->is_active);
    }

    public function test_editor_cannot_change_user_status(): void
    {
        $editor = User::factory()->editor()->create();
        $user = User::factory()->create();

        $this->actingAs($editor)->patch(route('users.status.update', $user), ['is_active' => '0'])->assertForbidden();
    }

    public function test_administrator_cannot_deactivate_own_account(): void
    {
        $administrator = User::factory()->systemAdministrator()->create();

        $this->actingAs($administrator)->patch(route('users.status.update', $administrator), ['is_active' => '0'])->assertSessionHasErrors('is_active');
        $this->assertTrue($administrator->fresh()->is_active);
    }
}
