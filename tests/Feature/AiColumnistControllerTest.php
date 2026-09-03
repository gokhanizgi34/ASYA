<?php

namespace Tests\Feature;

use App\Models\Agency;
use App\Models\AiColumnist;
use App\Models\AiPrompt;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AiColumnistControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_editor_sees_only_own_agency_profiles_and_cannot_manage_them(): void
    {
        $agency = Agency::factory()->create();
        $otherAgency = Agency::factory()->create();
        $editor = User::factory()->editor()->for($agency)->create();
        $ownColumnist = AiColumnist::factory()->for($agency)->create(['pen_name' => 'Kendi Yazarımız']);
        $otherColumnist = AiColumnist::factory()->for($otherAgency)->create(['pen_name' => 'Başka Ajans Yazarı']);

        $this->actingAs($editor)->get(route('ai-columnists.index'))
            ->assertOk()
            ->assertSee($ownColumnist->pen_name)
            ->assertDontSee($otherColumnist->pen_name);

        $this->actingAs($editor)->get(route('ai-columnists.create'))->assertForbidden();
        $this->actingAs($editor)->get(route('ai-columnists.edit', $ownColumnist))->assertForbidden();
    }

    public function test_agency_owner_creates_profile_only_for_own_agency(): void
    {
        $agency = Agency::factory()->create();
        $otherAgency = Agency::factory()->create();
        $owner = User::factory()->agencyOwner()->for($agency)->create();

        $this->actingAs($owner)->post(route('ai-columnists.store'), $this->payload($otherAgency, [
            'name' => 'Toplum Yazarı',
            'pen_name' => 'Sokaktan Sesler',
        ]))->assertRedirect(route('ai-columnists.index'));

        $this->assertDatabaseHas('ai_columnists', [
            'agency_id' => $agency->id,
            'name' => 'Toplum Yazarı',
            'slug' => 'toplum-yazari',
        ]);
        $this->assertDatabaseMissing('ai_columnists', ['agency_id' => $otherAgency->id]);
    }

    public function test_profile_rejects_prompt_from_another_agency(): void
    {
        $agency = Agency::factory()->create();
        $otherAgency = Agency::factory()->create();
        $owner = User::factory()->agencyOwner()->for($agency)->create();
        $foreignPrompt = AiPrompt::factory()->for($otherAgency)->create();

        $this->actingAs($owner)->post(route('ai-columnists.store'), $this->payload($agency, [
            'ai_prompt_id' => $foreignPrompt->id,
        ]))->assertSessionHasErrors('ai_prompt_id');

        $this->assertDatabaseCount('ai_columnists', 0);
    }

    public function test_profile_accepts_global_prompt_and_escapes_output(): void
    {
        $agency = Agency::factory()->create();
        $owner = User::factory()->agencyOwner()->for($agency)->create();
        $globalPrompt = AiPrompt::factory()->global()->create();

        $this->actingAs($owner)->post(route('ai-columnists.store'), $this->payload($agency, [
            'ai_prompt_id' => $globalPrompt->id,
            'pen_name' => '<script>alert(1)</script>',
        ]))->assertRedirect();

        $this->actingAs($owner)->get(route('ai-columnists.index'))
            ->assertOk()
            ->assertSee('&lt;script&gt;alert(1)&lt;/script&gt;', false)
            ->assertDontSee('<script>alert(1)</script>', false);
    }

    /** @param array<string, mixed> $overrides
     * @return array<string, mixed>
     */
    private function payload(Agency $agency, array $overrides = []): array
    {
        return array_merge([
            'agency_id' => $agency->id,
            'ai_prompt_id' => null,
            'name' => 'Gündem Yazarı',
            'pen_name' => 'Gündemin Nabzı',
            'biography' => 'Toplumsal gelişmeleri ve gündemi deneyimli bir editoryal bakışla yorumlar.',
            'expertise' => 'gündem, toplum',
            'voice_guide' => 'Sakin, kanıta dayalı ve görüş ile olguyu açıkça ayıran bir anlatım kullan.',
            'disclosure' => 'Bu köşe yazısı yapay zekâ destekli hazırlanmış ve editoryal incelemeden geçirilmiştir.',
            'is_active' => '1',
        ], $overrides);
    }
}
