<?php

namespace Tests\Feature\Http\Controllers;

use App\Models\Agency;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AgencyStatusControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_system_administrator_can_change_agency_status(): void
    {
        $administrator = User::factory()->systemAdministrator()->create();
        $agency = Agency::factory()->create();

        $this->actingAs($administrator)->patch(route('agencies.status.update', $agency), ['is_active' => '0'])
            ->assertRedirect(route('agencies.index'));
        $this->assertFalse($agency->fresh()->is_active);

        $this->actingAs($administrator)->patch(route('agencies.status.update', $agency), ['is_active' => '1'])
            ->assertRedirect(route('agencies.index'));
        $this->assertTrue($agency->fresh()->is_active);
    }

    public function test_agency_owner_cannot_change_agency_status(): void
    {
        $agency = Agency::factory()->create();
        $owner = User::factory()->agencyOwner()->for($agency)->create();

        $this->actingAs($owner)->patch(route('agencies.status.update', $agency), ['is_active' => '0'])
            ->assertForbidden();

        $this->assertTrue($agency->fresh()->is_active);
    }
}
