<?php

namespace App\Http\Controllers;

use App\Http\Requests\SimulatePromptRequest;
use App\Models\AiPrompt;
use App\Services\PromptSimulator;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class PromptSimulationController extends Controller
{
    public function show(Request $request, AiPrompt $aiPrompt, PromptSimulator $simulator): View
    {
        Gate::authorize('viewAny', AiPrompt::class);
        Gate::authorize('view', $aiPrompt);

        return $this->view($aiPrompt, $simulator, null, []);
    }

    public function simulate(SimulatePromptRequest $request, AiPrompt $aiPrompt, PromptSimulator $simulator): View
    {
        $variables = $request->validated('variables');
        $result = $simulator->render($aiPrompt, $variables);
        $aiPrompt->update(['last_tested_at' => now()]);

        return $this->view($aiPrompt->refresh(), $simulator, $result, $variables);
    }

    /** @param array{system_prompt: string, user_prompt: string, variables: array<int, string>, character_count: int}|null $result
     * @param  array<string, string>  $values
     */
    private function view(AiPrompt $prompt, PromptSimulator $simulator, ?array $result, array $values): View
    {
        return view('prompts.simulation', [
            'prompt' => $prompt->load('agency'),
            'variables' => $simulator->variables($prompt),
            'result' => $result,
            'values' => $values,
        ]);
    }
}
