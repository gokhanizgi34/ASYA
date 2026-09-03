<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('api_integrations', function (Blueprint $table): void {
            $table->string('model', 150)->nullable()->after('provider');
            $table->unsignedTinyInteger('priority')->default(50)->after('model');
            $table->boolean('is_default')->default(false)->after('priority');
            $table->index(['agency_id', 'is_active', 'is_default', 'priority'], 'api_integrations_ai_selection_index');
        });
    }

    public function down(): void
    {
        Schema::table('api_integrations', function (Blueprint $table): void {
            $table->dropIndex('api_integrations_ai_selection_index');
            $table->dropColumn(['model', 'priority', 'is_default']);
        });
    }
};
