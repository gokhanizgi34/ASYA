<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('agencies', function (Blueprint $table): void {
            $table->date('subscription_starts_at')->nullable()->after('phone')->index();
            $table->date('trial_ends_at')->nullable()->after('subscription_ends_at')->index();
            $table->string('province', 100)->nullable()->after('trial_ends_at');
            $table->string('district', 100)->nullable()->after('province');
            $table->string('category_name', 150)->nullable()->after('district');
        });

        Schema::table('raw_news_items', function (Blueprint $table): void {
            $table->timestamp('expires_at')->nullable()->after('discovered_at')->index();
        });

        Schema::table('publishing_targets', function (Blueprint $table): void {
            $table->unique('base_url');
        });
    }

    public function down(): void
    {
        Schema::table('publishing_targets', function (Blueprint $table): void {
            $table->dropUnique(['base_url']);
        });

        Schema::table('raw_news_items', function (Blueprint $table): void {
            $table->dropIndex(['expires_at']);
            $table->dropColumn('expires_at');
        });

        Schema::table('agencies', function (Blueprint $table): void {
            $table->dropIndex(['subscription_starts_at']);
            $table->dropIndex(['trial_ends_at']);
            $table->dropColumn(['subscription_starts_at', 'trial_ends_at', 'province', 'district', 'category_name']);
        });
    }
};
