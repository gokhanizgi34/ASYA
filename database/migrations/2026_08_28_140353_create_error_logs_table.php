<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('error_logs', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('agency_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('resolved_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('scope_key', 64);
            $table->char('fingerprint', 64);
            $table->string('severity', 20);
            $table->string('status', 20)->default('open');
            $table->string('exception_class');
            $table->text('message');
            $table->string('file')->nullable();
            $table->unsignedInteger('line')->nullable();
            $table->string('http_method', 10)->nullable();
            $table->string('path', 2048)->nullable();
            $table->string('route_name')->nullable();
            $table->unsignedInteger('occurrences')->default(1);
            $table->json('context')->nullable();
            $table->text('resolution_note')->nullable();
            $table->timestamp('first_seen_at');
            $table->timestamp('last_seen_at');
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();

            $table->unique(['scope_key', 'fingerprint']);
            $table->index(['agency_id', 'status', 'last_seen_at']);
            $table->index(['severity', 'last_seen_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('error_logs');
    }
};
