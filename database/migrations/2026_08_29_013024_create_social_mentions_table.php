<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('social_mentions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('agency_id')->constrained()->cascadeOnDelete();
            $table->foreignId('social_listening_watch_id')->constrained()->cascadeOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('platform', 30);
            $table->string('external_id', 190)->nullable();
            $table->string('author_handle', 120)->nullable();
            $table->string('url', 2048)->nullable();
            $table->string('title', 250)->nullable();
            $table->text('content');
            $table->timestamp('published_at');
            $table->unsignedInteger('engagement_count')->default(0);
            $table->string('sentiment', 20);
            $table->decimal('sentiment_score', 4, 3)->default(0);
            $table->unsignedTinyInteger('urgency_score')->default(0);
            $table->json('matched_keywords');
            $table->string('status', 20)->default('new');
            $table->timestamps();

            $table->unique(['agency_id', 'platform', 'external_id']);
            $table->index(['agency_id', 'status', 'published_at']);
            $table->index(['agency_id', 'sentiment', 'urgency_score']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('social_mentions');
    }
};
