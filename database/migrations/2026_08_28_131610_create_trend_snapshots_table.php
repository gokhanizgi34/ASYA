<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('trend_snapshots', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('trend_topic_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('mention_count');
            $table->unsignedInteger('source_count');
            $table->decimal('score', 10, 2);
            $table->decimal('velocity', 10, 2);
            $table->timestamp('period_start');
            $table->timestamp('period_end');
            $table->timestamps();
            $table->unique(['trend_topic_id', 'period_end']);
            $table->index(['trend_topic_id', 'period_start']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('trend_snapshots');
    }
};
