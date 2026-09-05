<?php

namespace Tests\Feature;

use App\Models\Agency;
use App\Models\EditorialStyleProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EditorialStyleProfileControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_agency_owner_can_view_and_save_style_memory_with_optional_fields_omitted(): void
    {
        $agency = Agency::factory()->create();
        $user = User::factory()->agencyOwner()->for($agency)->create();

        $this->actingAs($user)
            ->get(route('editorial-style-profiles.index'))
            ->assertOk()
            ->assertSee('Yazım Dili');

        $this->actingAs($user)
            ->put(route('editorial-style-profiles.update'), [
                'agency_id' => $agency->id,
                'name' => 'İLÇE HABER dili',
                'daily_quota' => 25,
                'destination' => 'draft',
                'is_active' => '1',
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $profile = EditorialStyleProfile::query()->firstOrFail();
        $this->assertSame($agency->id, $profile->agency_id);
        $this->assertSame('İLÇE HABER dili', $profile->name);
        $this->assertSame([], $profile->replacements);
        $this->assertSame([], $profile->forbidden_terms);
        $this->assertSame(25, $profile->daily_quota);
        $this->assertSame('draft', $profile->destination);
    }
}
