<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('news_sources', function (Blueprint $table): void {
            $table->unsignedSmallInteger('daily_item_limit')->default(10)->after('is_active');
        });

        Schema::table('raw_news_items', function (Blueprint $table): void {
            $table->foreignId('news_source_id')->nullable()->after('agency_id')->constrained()->nullOnDelete();
            $table->index(['news_source_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::table('raw_news_items', function (Blueprint $table): void {
            $table->dropIndex(['news_source_id', 'created_at']);
            $table->dropConstrainedForeignId('news_source_id');
        });

        Schema::table('news_sources', function (Blueprint $table): void {
            $table->dropColumn('daily_item_limit');
        });
    }
};
