<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('news_sources', function (Blueprint $table): void {
            $table->text('feed_url')->nullable()->after('domain');
            $table->string('feed_format', 20)->default('auto')->after('feed_url');
            $table->timestamp('last_fetched_at')->nullable()->after('last_assessed_at');
            $table->unsignedSmallInteger('last_status_code')->nullable()->after('last_fetched_at');
            $table->unsignedSmallInteger('last_item_count')->default(0)->after('last_status_code');
            $table->text('last_fetch_error')->nullable()->after('last_item_count');
        });
    }

    public function down(): void
    {
        Schema::table('news_sources', function (Blueprint $table): void {
            $table->dropColumn(['feed_url', 'feed_format', 'last_fetched_at', 'last_status_code', 'last_item_count', 'last_fetch_error']);
        });
    }
};
