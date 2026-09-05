<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('news_sources', function (Blueprint $table): void {
            $table->char('feed_url_hash', 64)->nullable()->after('feed_url');
            $table->dropUnique(['agency_id', 'domain']);
            $table->unique(['agency_id', 'feed_url_hash'], 'news_sources_agency_feed_hash_unique');
        });

        DB::table('news_sources')->select(['id', 'feed_url'])->orderBy('id')->get()->each(function (object $source): void {
            DB::table('news_sources')->where('id', $source->id)->update([
                'feed_url_hash' => hash('sha256', mb_strtolower(trim((string) $source->feed_url))),
            ]);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('news_sources', function (Blueprint $table): void {
            $table->dropUnique('news_sources_agency_feed_hash_unique');
            $table->unique(['agency_id', 'domain']);
            $table->dropColumn('feed_url_hash');
        });
    }
};
