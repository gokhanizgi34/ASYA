<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('api_integrations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('agency_id')->constrained()->cascadeOnDelete();
            $table->string('name', 150);
            $table->string('provider', 30);
            $table->string('base_url', 1000);
            $table->string('auth_type', 30);
            $table->string('username')->nullable();
            $table->string('api_key_header', 100)->nullable();
            $table->text('credential')->nullable();
            $table->unsignedTinyInteger('timeout_seconds')->default(15);
            $table->boolean('is_active')->default(true);
            $table->timestamp('last_tested_at')->nullable();
            $table->unsignedSmallInteger('last_status_code')->nullable();
            $table->unsignedInteger('last_response_time_ms')->nullable();
            $table->text('last_error')->nullable();
            $table->softDeletes();
            $table->timestamps();

            $table->unique(['agency_id', 'name']);
            $table->index(['agency_id', 'is_active', 'provider']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('api_integrations');
    }
};
