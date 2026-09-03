<?php

namespace Tests\Feature;

use App\Models\Agency;
use App\Models\AiPrompt;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PromptSimulationControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_editor_cannot_access_prompt_simulation(): void
    {
        $editor = User::factory()->editor()->create();
        $prompt = AiPrompt::factory()->global()->create();

        $this->actingAs($editor)->get(route('prompts.simulation', $prompt))->assertForbidden();
    }

    public function test_owner_can_simulate_visible_prompt_without_external_call(): void
    {
        $agency = Agency::factory()->create();
        $owner = User::factory()->agencyOwner()->for($agency)->create();
        $prompt = AiPrompt::factory()->global()->create([
            'system_prompt' => '{language} dilinde tarafsız bir editör ol.',
            'user_prompt_template' => 'Başlık: {title}'.PHP_EOL.'İçerik: {content}',
            'last_tested_at' => null,
        ]);

        $this->actingAs($owner)->post(route('prompts.simulation.run', $prompt), [
            'variables' => [
                'language' => 'Türkçe',
                'title' => 'Ekonomi gündemi',
                'content' => 'Piyasalar bugün yükselişle açıldı.',
            ],
        ])->assertOk()
            ->assertSee('Türkçe dilinde tarafsız bir editör ol.')
            ->assertSee('Başlık: Ekonomi gündemi')
            ->assertSee('İçerik: Piyasalar bugün yükselişle açıldı.');

        $this->assertNotNull($prompt->fresh()->last_tested_at);
    }

    public function test_missing_or_unknown_variables_are_rejected(): void
    {
        $administrator = User::factory()->systemAdministrator()->create();
        $prompt = AiPrompt::factory()->global()->create([
            'user_prompt_template' => 'İçerik: {content}; Başlık: {title}',
        ]);

        $this->actingAs($administrator)->post(route('prompts.simulation.run', $prompt), [
            'variables' => ['content' => 'örnek', 'unknown' => 'değer'],
        ])->assertSessionHasErrors(['variables.title', 'variables']);
    }

    public function test_simulated_output_is_escaped(): void
    {
        $administrator = User::factory()->systemAdministrator()->create();
        $prompt = AiPrompt::factory()->global()->create([
            'user_prompt_template' => 'İçerik: {content}',
        ]);

        $this->actingAs($administrator)->post(route('prompts.simulation.run', $prompt), [
            'variables' => ['content' => '<script>alert(1)</script>'],
        ])->assertOk()
            ->assertSee('&lt;script&gt;alert(1)&lt;/script&gt;', false)
            ->assertDontSee('<script>alert(1)</script>', false);
    }
}
