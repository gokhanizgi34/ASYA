<?php

namespace Database\Seeders;

use App\Models\AiPrompt;
use App\PromptTone;
use Illuminate\Database\Seeder;

class AiPromptSeeder extends Seeder
{
    public function run(): void
    {
        AiPrompt::query()->firstOrCreate(
            ['scope_key' => 'global', 'name' => 'Standart Haber Yazımı'],
            [
                'agency_id' => null,
                'category' => 'haber',
                'tone' => PromptTone::Neutral,
                'language' => 'tr',
                'target_length' => 600,
                'temperature' => 0.70,
                'system_prompt' => 'Doğrulanmış bilgilere dayanan, tarafsız ve akıcı Türkçe haberler üret.',
                'user_prompt_template' => "Aşağıdaki kaynak içeriği özgün bir haber metnine dönüştür:\n\n{content}",
                'is_active' => true,
                'version' => 1,
            ],
        );
    }
}
