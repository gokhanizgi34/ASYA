<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('content_batch_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('content_batch_id')->constrained()->cascadeOnDelete();
            $table->foreignId('raw_news_item_id')->constrained()->restrictOnDelete();
            $table->foreignId('article_id')->nullable()->constrained()->nullOnDelete();
            $table->string('status')->default('queued')->index();
            $table->text('failure_message')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
            $table->unique(['content_batch_id', 'raw_news_item_id']);
            $table->index(['content_batch_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('content_batch_items');
    }
};
