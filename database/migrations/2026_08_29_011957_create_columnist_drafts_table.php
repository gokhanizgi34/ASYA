<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('columnist_drafts', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('agency_id')->constrained()->cascadeOnDelete();
            $table->foreignId('ai_columnist_id')->constrained()->restrictOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('topic', 250);
            $table->longText('source_notes');
            $table->string('headline', 250);
            $table->longText('body');
            $table->json('prompt_snapshot');
            $table->string('status', 20)->default('draft');
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();
            $table->index(['agency_id', 'status', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('columnist_drafts');
    }
};
