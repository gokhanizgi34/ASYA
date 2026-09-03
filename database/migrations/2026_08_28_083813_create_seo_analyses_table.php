<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('seo_analyses', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('agency_id')->constrained()->cascadeOnDelete();
            $table->foreignId('article_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('focus_keyword')->nullable();
            $table->string('meta_title');
            $table->string('meta_description', 320);
            $table->json('keywords');
            $table->json('hashtags');
            $table->unsignedTinyInteger('score');
            $table->unsignedTinyInteger('readability_score');
            $table->unsignedInteger('word_count');
            $table->decimal('keyword_density', 5, 2)->default(0);
            $table->json('issues');
            $table->json('recommendations');
            $table->timestamp('analyzed_at')->index();
            $table->timestamps();

            $table->index(['agency_id', 'score']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('seo_analyses');
    }
};
