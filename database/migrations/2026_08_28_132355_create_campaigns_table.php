<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('campaigns', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('agency_id')->constrained()->cascadeOnDelete();
            $table->foreignId('owner_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('name', 180);
            $table->string('status', 30);
            $table->text('objective');
            $table->text('target_audience');
            $table->json('channels');
            $table->text('brief')->nullable();
            $table->json('kpis')->nullable();
            $table->decimal('budget', 14, 2)->nullable();
            $table->dateTime('starts_at')->nullable();
            $table->dateTime('ends_at')->nullable();
            $table->softDeletes();
            $table->timestamps();
            $table->unique(['agency_id', 'name']);
            $table->index(['agency_id', 'status', 'starts_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('campaigns');
    }
};
