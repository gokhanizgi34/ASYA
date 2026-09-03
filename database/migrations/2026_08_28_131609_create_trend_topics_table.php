<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('trend_topics', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('agency_id')->constrained()->cascadeOnDelete();
            $table->string('name', 160);
            $table->string('normalized_name', 160);
            $table->string('status', 30);
            $table->unsignedInteger('mention_count')->default(0);
            $table->unsignedInteger('source_count')->default(0);
            $table->decimal('score', 10, 2)->default(0);
            $table->decimal('velocity', 10, 2)->default(0);
            $table->json('context')->nullable();
            $table->timestamp('first_seen_at');
            $table->timestamp('last_seen_at');
            $table->timestamp('analyzed_at');
            $table->timestamps();
            $table->unique(['agency_id', 'normalized_name']);
            $table->index(['agency_id', 'status', 'score']);
            $table->index(['agency_id', 'last_seen_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('trend_topics');
    }
};
