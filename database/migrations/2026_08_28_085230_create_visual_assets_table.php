<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('visual_assets', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('agency_id')->constrained()->restrictOnDelete();
            $table->foreignId('article_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('title');
            $table->string('source_type')->index();
            $table->string('status')->default('pending')->index();
            $table->string('copyright_status')->default('unknown')->index();
            $table->text('source_url')->nullable();
            $table->string('storage_disk')->default('public');
            $table->text('storage_path')->nullable();
            $table->string('mime_type')->nullable();
            $table->unsignedInteger('width')->nullable();
            $table->unsignedInteger('height')->nullable();
            $table->unsignedTinyInteger('quality_score')->default(0);
            $table->string('alt_text')->nullable();
            $table->string('headline_overlay')->nullable();
            $table->text('generation_prompt')->nullable();
            $table->text('evaluation_notes')->nullable();
            $table->text('failure_message')->nullable();
            $table->boolean('is_selected')->default(false)->index();
            $table->timestamp('generated_at')->nullable();
            $table->timestamp('evaluated_at')->nullable();
            $table->softDeletes();
            $table->timestamps();

            $table->index(['agency_id', 'article_id', 'status']);
            $table->index(['article_id', 'is_selected']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('visual_assets');
    }
};
