<?php

namespace App\Services;

use App\Models\AiColumnist;
use Illuminate\Support\Str;

class ColumnistDraftComposer
{
    /** @return array{headline:string,body:string,prompt_snapshot:array<string,mixed>} */
    public function compose(AiColumnist $columnist, string $topic, string $sourceNotes): array
    {
        $columnist->loadMissing('aiPrompt');
        $headline = Str::limit($topic.' — '.$columnist->pen_name, 250, '');
        $body = $columnist->disclosure."\n\n".$topic."\n\n".$sourceNotes."\n\n[Editoryal kontrol: Kaynak notlarını doğrulayın, görüş ile olguyu ayırın ve yayımdan önce metni özgünleştirin.]";

        return ['headline' => $headline, 'body' => $body, 'prompt_snapshot' => [
            'mode' => 'local_editorial_preview', 'columnist_id' => $columnist->id, 'voice_guide' => $columnist->voice_guide,
            'expertise' => $columnist->expertise, 'system_prompt' => $columnist->aiPrompt?->system_prompt,
            'user_prompt_template' => $columnist->aiPrompt?->user_prompt_template, 'topic' => $topic,
        ]];
    }
}
