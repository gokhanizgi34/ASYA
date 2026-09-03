<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('campaign_contents', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('campaign_id')->constrained()->cascadeOnDelete();
            $table->foreignId('article_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('channel', 30);
            $table->string('status', 30);
            $table->string('title', 220);
            $table->text('body');
            $table->string('call_to_action', 180)->nullable();
            $table->string('destination_url', 1000)->nullable();
            $table->json('metadata')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('published_at')->nullable();
            $table->timestamps();
            $table->index(['campaign_id', 'channel', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('campaign_contents');
    }
};
