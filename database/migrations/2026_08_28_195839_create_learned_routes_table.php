<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('learned_routes', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('agency_id')->constrained()->cascadeOnDelete();
            $table->foreignId('publishing_target_id')->nullable()->constrained()->nullOnDelete();
            $table->string('host', 191);
            $table->string('path_pattern', 500);
            $table->string('method', 10);
            $table->string('purpose')->nullable();
            $table->unsignedInteger('successful_count')->default(0);
            $table->unsignedInteger('failed_count')->default(0);
            $table->decimal('confidence', 5, 2)->default(0);
            $table->unsignedSmallInteger('last_status_code')->nullable();
            $table->boolean('is_enabled')->default(true);
            $table->timestamp('first_observed_at');
            $table->timestamp('last_observed_at');
            $table->timestamp('last_success_at')->nullable();
            $table->timestamps();

            $table->unique(['agency_id', 'host', 'path_pattern', 'method']);
            $table->index(['agency_id', 'is_enabled', 'confidence']);
            $table->index(['publishing_target_id', 'last_observed_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('learned_routes');
    }
};
