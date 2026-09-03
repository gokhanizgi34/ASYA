<?php

namespace App\Http\Requests;

use App\Models\AiPrompt;

class UpdateAiPromptRequest extends StoreAiPromptRequest
{
    public function authorize(): bool
    {
        $prompt = $this->route('aiPrompt');

        return $prompt instanceof AiPrompt && ($this->user()?->can('update', $prompt) ?? false);
    }

    protected function promptForUniqueRule(): ?AiPrompt
    {
        $prompt = $this->route('aiPrompt');

        return $prompt instanceof AiPrompt ? $prompt : null;
    }
}
