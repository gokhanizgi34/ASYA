<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('admin_news_distribution_items', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('admin_news_distribution_id');
            $table->foreignId('agency_id')->constrained()->restrictOnDelete();
            $table->foreignId('publishing_target_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('article_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('publication_id')->nullable()->constrained()->nullOnDelete();
            $table->text('failure_message')->nullable();
            $table->timestamps();

            $table->foreign('admin_news_distribution_id', 'admin_distribution_items_distribution_fk')->references('id')->on('admin_news_distributions')->cascadeOnDelete();
            $table->unique(['admin_news_distribution_id', 'publishing_target_id'], 'admin_distribution_target_unique');
            $table->index(['admin_news_distribution_id', 'agency_id'], 'admin_distribution_agency_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('admin_news_distribution_items');
    }
};
