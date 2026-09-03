<?php

namespace Tests\Feature;

use App\Models\Agency;
use App\Models\SocialListeningWatch;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SocialListeningControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_editor_sees_only_tenant_watches_and_can_create_for_own_agency(): void
    {
        $agency = Agency::factory()->create();
        $otherAgency = Agency::factory()->create();
        $editor = User::factory()->editor()->for($agency)->create();
        $ownWatch = SocialListeningWatch::factory()->for($agency)->create(['name' => 'Kendi Takibimiz']);
        $otherWatch = SocialListeningWatch::factory()->for($otherAgency)->create(['name' => 'Diğer Ajans Takibi']);

        $this->actingAs($editor)->get(route('social-listening.index'))
            ->assertOk()
            ->assertSee($ownWatch->name)
            ->assertDontSee($otherWatch->name);

        $this->actingAs($editor)->post(route('social-listening.store'), [
            'agency_id' => $otherAgency->id,
            'name' => 'Yerel Gündem',
            'keywords' => 'ASYA, belediye',
            'excluded_terms' => 'reklam, çekiliş',
            'platforms' => ['x', 'instagram'],
            'alert_threshold' => 75,
            'is_active' => '1',
        ])->assertRedirect();

        $this->assertDatabaseHas('social_listening_watches', [
            'agency_id' => $agency->id,
            'name' => 'Yerel Gündem',
        ]);
        $this->assertDatabaseMissing('social_listening_watches', [
            'agency_id' => $otherAgency->id,
            'name' => 'Yerel Gündem',
        ]);
    }

    public function test_watch_requires_supported_platform_and_unique_tenant_name(): void
    {
        $agency = Agency::factory()->create();
        $owner = User::factory()->agencyOwner()->for($agency)->create();
        SocialListeningWatch::factory()->for($agency)->create(['name' => 'Marka Takibi']);

        $this->actingAs($owner)->post(route('social-listening.store'), [
            'agency_id' => $agency->id,
            'name' => 'Marka Takibi',
            'keywords' => 'ASYA',
            'platforms' => ['bilinmeyen'],
            'alert_threshold' => 70,
            'is_active' => '1',
        ])->assertSessionHasErrors(['name', 'platforms.0']);
    }
}
