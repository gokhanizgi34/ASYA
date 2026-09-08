<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('social_publishing_accounts', function (Blueprint $table): void {
            $table->text('refresh_token')->nullable()->after('access_token_secret');
            $table->timestamp('token_expires_at')->nullable()->after('refresh_token');
            $table->string('x_user_id', 30)->nullable()->after('token_expires_at');
            $table->string('auth_type', 20)->nullable()->after('x_user_id');
            $table->unique(['agency_id', 'platform', 'x_user_id'], 'social_accounts_agency_platform_x_user_uq');
        });
    }

    public function down(): void
    {
        Schema::table('social_publishing_accounts', function (Blueprint $table): void {
            $table->dropUnique('social_accounts_agency_platform_x_user_uq');
            $table->dropColumn(['refresh_token', 'token_expires_at', 'x_user_id', 'auth_type']);
        });
    }
};
