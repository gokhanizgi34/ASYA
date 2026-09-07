<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('social_publishing_accounts', function (Blueprint $table): void {
            $table->json('mention_rules')->nullable()->after('publish_mode');
        });
    }

    public function down(): void
    {
        Schema::table('social_publishing_accounts', function (Blueprint $table): void {
            $table->dropColumn('mention_rules');
        });
    }
};
