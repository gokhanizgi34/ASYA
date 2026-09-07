<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('social_publishing_accounts', function (Blueprint $table): void {
            $table->text('api_key')->nullable()->after('access_token');
            $table->text('api_secret')->nullable()->after('api_key');
            $table->text('access_token_secret')->nullable()->after('api_secret');
        });
    }

    public function down(): void
    {
        Schema::table('social_publishing_accounts', function (Blueprint $table): void {
            $table->dropColumn(['api_key', 'api_secret', 'access_token_secret']);
        });
    }
};
