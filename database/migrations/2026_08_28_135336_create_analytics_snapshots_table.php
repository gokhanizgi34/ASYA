<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('analytics_snapshots', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('agency_id')->constrained()->cascadeOnDelete();
            $table->date('report_date');
            $table->unsignedInteger('raw_news_count')->default(0);
            $table->unsignedInteger('articles_created_count')->default(0);
            $table->unsignedInteger('articles_published_count')->default(0);
            $table->unsignedInteger('publication_success_count')->default(0);
            $table->unsignedInteger('publication_failure_count')->default(0);
            $table->unsignedInteger('campaigns_created_count')->default(0);
            $table->unsignedInteger('campaign_contents_count')->default(0);
            $table->unsignedInteger('trend_topics_count')->default(0);
            $table->unsignedInteger('seo_word_count')->default(0);
            $table->decimal('average_seo_score', 8, 2)->nullable();
            $table->decimal('average_trend_score', 10, 2)->nullable();
            $table->json('details')->nullable();
            $table->timestamp('aggregated_at');
            $table->timestamps();
            $table->unique(['agency_id', 'report_date']);
            $table->index(['report_date', 'agency_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('analytics_snapshots');
    }
};
