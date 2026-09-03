<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('horoscope_forecasts', function (Blueprint $table): void {
            $table->string('symbol', 10)->nullable()->after('sign');
            $table->string('seo_title', 255)->nullable()->after('health');
            $table->text('seo_description')->nullable()->after('seo_title');
            $table->json('seo_keywords')->nullable()->after('seo_description');
        });
    }

    public function down(): void
    {
        Schema::table('horoscope_forecasts', function (Blueprint $table): void {
            $table->dropColumn(['symbol', 'seo_title', 'seo_description', 'seo_keywords']);
        });
    }
};
