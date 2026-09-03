<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('api_integrations', function (Blueprint $table): void {
            $table->boolean('visual_enabled')->default(false)->after('is_default')->index();
        });
    }

    public function down(): void
    {
        Schema::table('api_integrations', function (Blueprint $table): void {
            $table->dropIndex(['visual_enabled']);
            $table->dropColumn('visual_enabled');
        });
    }
};
