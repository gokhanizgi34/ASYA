<?php

namespace Tests\Feature;

use App\BlacklistAction;
use App\BlacklistRuleType;
use App\Models\Agency;
use App\Models\BlacklistRule;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BlacklistRuleControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_is_redirected_and_editor_is_forbidden(): void
    {
        $this->get(route('blacklist-rules.index'))->assertRedirect(route('login'));

        $editor = User::factory()->editor()->create();
        $this->actingAs($editor)->get(route('blacklist-rules.index'))->assertForbidden();
    }

    public function test_owner_sees_only_own_agency_rules_and_output_is_escaped(): void
    {
        $agency = Agency::factory()->create();
        $otherAgency = Agency::factory()->create();
        $owner = User::factory()->agencyOwner()->for($agency)->create();
        $own = BlacklistRule::factory()->for($agency)->create(['pattern' => '<script>alert(1)</script>', 'normalized_pattern' => 'unsafe']);
        $other = BlacklistRule::factory()->for($otherAgency)->create();

        $this->actingAs($owner)->get(route('blacklist-rules.index'))
            ->assertOk()
            ->assertSee('&lt;script&gt;alert(1)&lt;/script&gt;', false)
            ->assertDontSee('<script>alert(1)</script>', false)
            ->assertDontSee($other->pattern);
        $this->actingAs($owner)->get(route('blacklist-rules.edit', $other))->assertForbidden();
        $this->actingAs($owner)->get(route('blacklist-rules.edit', $own))->assertOk();
    }

    public function test_owner_creates_normalized_rule_only_for_own_agency(): void
    {
        $agency = Agency::factory()->create();
        $otherAgency = Agency::factory()->create();
        $owner = User::factory()->agencyOwner()->for($agency)->create();

        $this->actingAs($owner)->post(route('blacklist-rules.store'), [
            'agency_id' => $otherAgency->id,
            'type' => BlacklistRuleType::Domain->value,
            'pattern' => 'HTTPS://Example.COM/news',
            'action' => BlacklistAction::Block->value,
            'reason' => '  Güvenilmeyen kaynak  ',
            'is_active' => '1',
        ])->assertRedirect(route('blacklist-rules.index'));

        $this->assertDatabaseHas('blacklist_rules', [
            'agency_id' => $agency->id,
            'normalized_pattern' => 'example.com',
            'reason' => 'Güvenilmeyen kaynak',
        ]);
    }

    public function test_duplicate_normalized_pattern_is_rejected_per_agency_and_type(): void
    {
        $agency = Agency::factory()->create();
        $owner = User::factory()->agencyOwner()->for($agency)->create();
        BlacklistRule::factory()->for($agency)->create([
            'type' => BlacklistRuleType::Keyword,
            'pattern' => 'Yasak Kelime',
            'normalized_pattern' => 'yasak kelime',
        ]);

        $this->actingAs($owner)->post(route('blacklist-rules.store'), [
            'agency_id' => $agency->id,
            'type' => BlacklistRuleType::Keyword->value,
            'pattern' => '  YASAK   KELİME ',
            'action' => BlacklistAction::Review->value,
            'is_active' => '1',
        ])->assertSessionHasErrors('normalized_pattern');

        $this->assertDatabaseCount('blacklist_rules', 1);
    }

    public function test_owner_cannot_update_or_delete_another_agencys_rule(): void
    {
        $owner = User::factory()->agencyOwner()->create();
        $rule = BlacklistRule::factory()->create();

        $this->actingAs($owner)->put(route('blacklist-rules.update', $rule), [
            'type' => BlacklistRuleType::Keyword->value,
            'pattern' => 'değiştir',
            'action' => BlacklistAction::Block->value,
            'is_active' => '1',
        ])->assertForbidden();
        $this->actingAs($owner)->delete(route('blacklist-rules.destroy', $rule))->assertForbidden();
    }
}
