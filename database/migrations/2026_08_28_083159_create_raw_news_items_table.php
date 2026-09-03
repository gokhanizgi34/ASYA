<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('raw_news_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('agency_id')->constrained()->restrictOnDelete();
            $table->string('external_id')->nullable();
            $table->string('source_name');
            $table->text('source_url')->nullable();
            $table->string('original_title');
            $table->longText('original_body');
            $table->text('original_image_url')->nullable();
            $table->string('language', 10)->default('tr')->index();
            $table->string('status')->default('pending')->index();
            $table->unsignedTinyInteger('priority')->default(50)->index();
            $table->string('checksum', 64);
            $table->timestamp('discovered_at')->nullable()->index();
            $table->timestamp('processed_at')->nullable()->index();
            $table->text('failure_message')->nullable();
            $table->softDeletes();
            $table->timestamps();

            $table->unique(['agency_id', 'checksum']);
            $table->index(['agency_id', 'status', 'priority']);
            $table->index(['agency_id', 'external_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('raw_news_items');
    }
};
