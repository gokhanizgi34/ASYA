<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('news_sources', function (Blueprint $table): void {
            $table->string('last_ingestion_method', 40)->nullable()->after('last_fetch_error');
            $table->char('last_content_fingerprint', 64)->nullable()->after('last_ingestion_method');
            $table->timestamp('last_change_detected_at')->nullable()->after('last_content_fingerprint');
            $table->unsignedTinyInteger('last_crawled_pages')->default(0)->after('last_change_detected_at');
        });
    }

    public function down(): void
    {
        Schema::table('news_sources', function (Blueprint $table): void {
            $table->dropColumn([
                'last_ingestion_method',
                'last_content_fingerprint',
                'last_change_detected_at',
                'last_crawled_pages',
            ]);
        });
    }
};
