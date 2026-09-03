<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('article_translations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('agency_id')->constrained()->cascadeOnDelete();
            $table->foreignId('article_id')->constrained()->cascadeOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('source_locale', 10)->default('tr');
            $table->string('target_locale', 10);
            $table->string('source_checksum', 64);
            $table->string('title', 255);
            $table->text('summary')->nullable();
            $table->longText('body');
            $table->json('glossary')->nullable();
            $table->string('status', 20)->default('draft');
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();

            $table->unique(['article_id', 'target_locale']);
            $table->index(['agency_id', 'status', 'target_locale']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('article_translations');
    }
};
