<?php

namespace App\Services;

use App\Models\AiPrompt;
use Illuminate\Support\Str;

class PromptSimulator
{
    /** @return array<int, string> */
    public function variables(AiPrompt $prompt): array
    {
        preg_match_all(
            '/\{([a-z][a-z0-9_]*)\}/i',
            $prompt->system_prompt."\n".$prompt->user_prompt_template,
            $matches,
        );

        return collect($matches[1] ?? [])->map(fn (string $name): string => Str::lower($name))->unique()->sort()->values()->all();
    }

    /**
     * @param  array<string, string>  $variables
     * @return array{system_prompt: string, user_prompt: string, variables: array<int, string>, character_count: int}
     */
    public function render(AiPrompt $prompt, array $variables): array
    {
        $replacements = collect($variables)->mapWithKeys(fn (string $value, string $key): array => [
            '{'.$key.'}' => $value,
        ])->all();

        $systemPrompt = strtr($prompt->system_prompt, $replacements);
        $userPrompt = strtr($prompt->user_prompt_template, $replacements);

        return [
            'system_prompt' => $systemPrompt,
            'user_prompt' => $userPrompt,
            'variables' => $this->variables($prompt),
            'character_count' => mb_strlen($systemPrompt) + mb_strlen($userPrompt),
        ];
    }
}
