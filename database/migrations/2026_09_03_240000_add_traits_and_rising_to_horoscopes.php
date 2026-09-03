<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('horoscope_forecasts', function (Blueprint $table): void {
            $table->text('traits')->nullable()->after('general');
            $table->text('rising')->nullable()->after('traits');
        });
    }

    public function down(): void
    {
        Schema::table('horoscope_forecasts', function (Blueprint $table): void {
            $table->dropColumn(['traits', 'rising']);
        });
    }
};
