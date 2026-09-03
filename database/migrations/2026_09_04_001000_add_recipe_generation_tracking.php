<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('agencies', function (Blueprint $table): void {
            $table->unsignedSmallInteger('recipe_daily_quota')->default(4)->after('logo_path');
        });

        Schema::table('recipes', function (Blueprint $table): void {
            $table->string('origin', 20)->default('manual')->after('instructions')->index();
            $table->foreignId('generated_for_agency_id')->nullable()->after('origin')->constrained('agencies')->nullOnDelete();
            $table->timestamp('generated_at')->nullable()->after('generated_for_agency_id')->index();
        });
    }

    public function down(): void
    {
        Schema::table('recipes', function (Blueprint $table): void {
            $table->dropIndex(['origin']);
            $table->dropIndex(['generated_at']);
            $table->dropForeign(['generated_for_agency_id']);
            $table->dropColumn(['origin', 'generated_for_agency_id', 'generated_at']);
        });

        Schema::table('agencies', function (Blueprint $table): void {
            $table->dropColumn('recipe_daily_quota');
        });
    }
};
