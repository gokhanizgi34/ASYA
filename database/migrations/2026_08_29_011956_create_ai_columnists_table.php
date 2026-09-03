<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ai_columnists', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('agency_id')->constrained()->cascadeOnDelete();
            $table->foreignId('ai_prompt_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('name', 120);
            $table->string('slug', 140);
            $table->string('pen_name', 120);
            $table->text('biography');
            $table->json('expertise');
            $table->text('voice_guide');
            $table->string('disclosure', 255)->default('Bu köşe yazısı yapay zekâ destekli hazırlanmış ve editoryal incelemeden geçirilmiştir.');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->unique(['agency_id', 'slug']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_columnists');
    }
};
