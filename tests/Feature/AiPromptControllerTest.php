<?php

namespace Tests\Feature;

use App\Models\Agency;
use App\Models\AiPrompt;
use App\Models\User;
use App\PromptTone;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AiPromptControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_editor_cannot_access_prompt_management(): void
    {
        $editor = User::factory()->editor()->create();

        $this->actingAs($editor)->get(route('prompts.index'))->assertForbidden();
        $this->actingAs($editor)->post(route('prompts.store'), $this->payload())->assertForbidden();
    }

    public function test_system_administrator_sees_global_and_all_agency_prompts_safely(): void
    {
        $administrator = User::factory()->systemAdministrator()->create();
        $globalPrompt = AiPrompt::factory()->global()->create(['name' => '<script>alert(1)</script>']);
        $agencyPrompt = AiPrompt::factory()->create(['name' => 'Ajans Haber Şablonu']);

        $this->actingAs($administrator)->get(route('prompts.index'))
            ->assertOk()
            ->assertSee('&lt;script&gt;alert(1)&lt;/script&gt;', false)
            ->assertDontSee('<script>alert(1)</script>', false)
            ->assertSee($agencyPrompt->name);

        $this->assertNull($globalPrompt->agency_id);
        $this->assertSame('global', $globalPrompt->scope_key);
    }

    public function test_agency_owner_sees_global_and_own_prompts_but_not_other_agency_prompts(): void
    {
        $ownAgency = Agency::factory()->create();
        $otherAgency = Agency::factory()->create();
        $owner = User::factory()->agencyOwner()->for($ownAgency)->create();
        $globalPrompt = AiPrompt::factory()->global()->create(['name' => 'Global Şablon']);
        $ownPrompt = AiPrompt::factory()->for($ownAgency)->create(['name' => 'Kendi Şablonu']);
        $otherPrompt = AiPrompt::factory()->for($otherAgency)->create(['name' => 'Başka Ajans Şablonu']);

        $this->actingAs($owner)->get(route('prompts.index'))
            ->assertOk()
            ->assertSee($globalPrompt->name)
            ->assertSee($ownPrompt->name)
            ->assertDontSee($otherPrompt->name);
    }

    public function test_agency_owner_cannot_change_global_or_other_agency_prompt(): void
    {
        $owner = User::factory()->agencyOwner()->create();
        $globalPrompt = AiPrompt::factory()->global()->create();
        $otherPrompt = AiPrompt::factory()->create();

        $this->actingAs($owner)->get(route('prompts.edit', $globalPrompt))->assertForbidden();
        $this->actingAs($owner)->put(route('prompts.update', $otherPrompt), $this->payload())->assertForbidden();
    }

    public function test_agency_owner_can_only_create_prompt_for_own_agency(): void
    {
        $ownAgency = Agency::factory()->create();
        $otherAgency = Agency::factory()->create();
        $owner = User::factory()->agencyOwner()->for($ownAgency)->create();

        $this->actingAs($owner)->post(route('prompts.store'), $this->payload([
            'agency_id' => $otherAgency->id,
            'name' => 'Yerel Haber Şablonu',
        ]))->assertRedirect(route('prompts.index'));

        $prompt = AiPrompt::query()->where('name', 'Yerel Haber Şablonu')->firstOrFail();
        $this->assertSame($ownAgency->id, $prompt->agency_id);
        $this->assertSame('agency:'.$ownAgency->id, $prompt->scope_key);
    }

    public function test_prompt_template_requires_content_placeholder(): void
    {
        $administrator = User::factory()->systemAdministrator()->create();

        $this->actingAs($administrator)->post(route('prompts.store'), $this->payload([
            'user_prompt_template' => 'Bu şablonda gerekli yer tutucu bulunmuyor.',
        ]))->assertSessionHasErrors('user_prompt_template');
    }

    public function test_updating_prompt_increments_its_version(): void
    {
        $agency = Agency::factory()->create();
        $owner = User::factory()->agencyOwner()->for($agency)->create();
        $prompt = AiPrompt::factory()->for($agency)->create(['version' => 3]);

        $this->actingAs($owner)->put(route('prompts.update', $prompt), $this->payload([
            'agency_id' => $agency->id,
            'name' => $prompt->name,
        ]))->assertRedirect(route('prompts.index'));

        $this->assertSame(4, $prompt->fresh()->version);
    }

    public function test_prompt_name_is_unique_within_scope_but_can_repeat_in_another_scope(): void
    {
        $administrator = User::factory()->systemAdministrator()->create();
        $agency = Agency::factory()->create();
        AiPrompt::factory()->global()->create(['name' => 'Standart Haber']);

        $this->actingAs($administrator)->post(route('prompts.store'), $this->payload([
            'name' => 'Standart Haber',
            'agency_id' => null,
        ]))->assertSessionHasErrors('name');

        $this->actingAs($administrator)->post(route('prompts.store'), $this->payload([
            'name' => 'Standart Haber',
            'agency_id' => $agency->id,
        ]))->assertRedirect(route('prompts.index'));

        $this->assertDatabaseHas('ai_prompts', [
            'name' => 'Standart Haber',
            'agency_id' => $agency->id,
        ]);
    }

    public function test_authorized_owner_can_soft_delete_own_prompt(): void
    {
        $agency = Agency::factory()->create();
        $owner = User::factory()->agencyOwner()->for($agency)->create();
        $prompt = AiPrompt::factory()->for($agency)->create();

        $this->actingAs($owner)->delete(route('prompts.destroy', $prompt))
            ->assertRedirect(route('prompts.index'));

        $this->assertSoftDeleted($prompt);
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function payload(array $overrides = []): array
    {
        return array_merge([
            'agency_id' => null,
            'name' => 'Gündem Haber Şablonu',
            'category' => 'haber',
            'tone' => PromptTone::Neutral->value,
            'language' => 'tr',
            'target_length' => 600,
            'temperature' => 0.7,
            'system_prompt' => 'Deneyimli ve tarafsız bir Türkçe haber editörü gibi davran.',
            'user_prompt_template' => 'Aşağıdaki kaynak içeriği özgün bir habere dönüştür: {content}',
            'is_active' => true,
        ], $overrides);
    }
}
