<?php

namespace Tests\Feature;

use App\Models\Agency;
use App\Models\PublishingTarget;
use App\Models\TaxonomyMapping;
use App\Models\User;
use App\TaxonomyType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TaxonomyMappingControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_is_redirected_and_editor_is_forbidden(): void
    {
        $agency = Agency::factory()->create();
        $editor = User::factory()->editor()->for($agency)->create();

        $this->get(route('taxonomy-mappings.index'))->assertRedirect(route('login'));
        $this->actingAs($editor)->get(route('taxonomy-mappings.index'))->assertForbidden();
    }

    public function test_owner_can_open_a_real_create_form_without_raw_blade_directives(): void
    {
        $agency = Agency::factory()->create();
        $owner = User::factory()->agencyOwner()->for($agency)->create();
        PublishingTarget::factory()->for($agency)->create(['name' => 'Ana Haber Sitesi']);

        $this->actingAs($owner)->get(route('taxonomy-mappings.create'))
            ->assertOk()
            ->assertSee('name="source_term"', false)
            ->assertSee('name="remote_id"', false)
            ->assertSee('Ana Haber Sitesi')
            ->assertDontSee('@include');
    }

    public function test_owner_creates_normalized_mapping_only_for_own_target(): void
    {
        $agency = Agency::factory()->create();
        $otherAgency = Agency::factory()->create();
        $owner = User::factory()->agencyOwner()->for($agency)->create();
        $target = PublishingTarget::factory()->for($agency)->create();
        $foreignTarget = PublishingTarget::factory()->for($otherAgency)->create();

        $this->actingAs($owner)->post(route('taxonomy-mappings.store'), $this->payload($foreignTarget, ['source_term' => '#Dünya Gündemi']))->assertSessionHasErrors('publishing_target_id');
        $this->actingAs($owner)->post(route('taxonomy-mappings.store'), $this->payload($target, ['agency_id' => $otherAgency->id, 'source_term' => '#Dünya Gündemi']))->assertRedirect(route('taxonomy-mappings.index'));

        $this->assertDatabaseHas('taxonomy_mappings', ['agency_id' => $agency->id, 'publishing_target_id' => $target->id, 'source_term' => 'Dünya Gündemi', 'source_key' => 'dunya-gundemi']);
    }

    public function test_owner_sees_and_mutates_only_own_mappings(): void
    {
        $agency = Agency::factory()->create();
        $otherAgency = Agency::factory()->create();
        $owner = User::factory()->agencyOwner()->for($agency)->create();
        $own = TaxonomyMapping::factory()->for($agency)->create(['source_term' => 'OWN-TERM']);
        $foreign = TaxonomyMapping::factory()->for($otherAgency)->create(['source_term' => 'FOREIGN-TERM']);

        $this->actingAs($owner)->get(route('taxonomy-mappings.index'))->assertOk()->assertSee('OWN-TERM')->assertDontSee('FOREIGN-TERM');
        $this->actingAs($owner)->get(route('taxonomy-mappings.edit', $foreign))->assertForbidden();
        $this->actingAs($owner)->delete(route('taxonomy-mappings.destroy', $own))->assertRedirect();
        $this->assertDatabaseMissing('taxonomy_mappings', ['id' => $own->id]);
    }

    public function test_duplicate_source_term_for_same_target_and_type_is_rejected(): void
    {
        $agency = Agency::factory()->create();
        $owner = User::factory()->agencyOwner()->for($agency)->create();
        $target = PublishingTarget::factory()->for($agency)->create();
        TaxonomyMapping::factory()->for($agency)->for($target, 'publishingTarget')->create(['source_term' => 'Spor', 'source_key' => 'spor']);

        $this->actingAs($owner)->post(route('taxonomy-mappings.store'), $this->payload($target, ['source_term' => '#Spor']))->assertSessionHasErrors('source_key');
        $this->assertDatabaseCount('taxonomy_mappings', 1);
    }

    /** @param array<string, mixed> $overrides */
    private function payload(PublishingTarget $target, array $overrides = []): array
    {
        return array_replace(['agency_id' => $target->agency_id, 'publishing_target_id' => $target->id, 'type' => TaxonomyType::Category->value, 'source_term' => 'Ekonomi', 'remote_id' => 15, 'remote_name' => 'Ekonomi', 'priority' => 50, 'is_active' => true], $overrides);
    }
}
