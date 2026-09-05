<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('blacklist_rules', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('agency_id')->constrained()->cascadeOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('type', 30);
            $table->string('pattern', 500);
            $table->string('normalized_pattern', 500);
            $table->string('action', 20);
            $table->string('reason', 500)->nullable();
            $table->unsignedInteger('hit_count')->default(0);
            $table->timestamp('last_hit_at')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['agency_id', 'type', 'normalized_pattern']);
            $table->index(['agency_id', 'is_active', 'action']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('blacklist_rules');
    }
};
