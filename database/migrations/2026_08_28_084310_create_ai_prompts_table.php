<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ai_prompts', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('agency_id')->nullable()->constrained()->restrictOnDelete();
            $table->string('scope_key');
            $table->string('name');
            $table->string('category')->index();
            $table->string('tone')->default('neutral');
            $table->string('language', 10)->default('tr');
            $table->unsignedSmallInteger('target_length')->default(600);
            $table->decimal('temperature', 3, 2)->default(0.70);
            $table->text('system_prompt');
            $table->longText('user_prompt_template');
            $table->boolean('is_active')->default(true)->index();
            $table->unsignedInteger('version')->default(1);
            $table->timestamp('last_tested_at')->nullable();
            $table->softDeletes();
            $table->timestamps();

            $table->unique(['scope_key', 'name']);
            $table->index(['agency_id', 'category', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_prompts');
    }
};
