<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('publications', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('agency_id')->constrained()->restrictOnDelete();
            $table->foreignId('article_id')->constrained()->restrictOnDelete();
            $table->foreignId('publishing_target_id')->constrained()->restrictOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('status')->default('queued')->index();
            $table->string('remote_status')->default('publish');
            $table->string('remote_post_id')->nullable();
            $table->unsignedBigInteger('remote_media_id')->nullable();
            $table->text('remote_url')->nullable();
            $table->json('payload');
            $table->json('response_meta')->nullable();
            $table->unsignedSmallInteger('attempt_count')->default(0);
            $table->text('failure_message')->nullable();
            $table->timestamp('queued_at')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('published_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
            $table->unique(['article_id', 'publishing_target_id']);
            $table->index(['agency_id', 'status', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('publications');
    }
};
