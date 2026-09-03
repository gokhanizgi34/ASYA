<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('social_listening_watches', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('agency_id')->constrained()->cascadeOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('name', 120);
            $table->json('keywords');
            $table->json('excluded_terms')->nullable();
            $table->json('platforms');
            $table->unsignedTinyInteger('alert_threshold')->default(70);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['agency_id', 'name']);
            $table->index(['agency_id', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('social_listening_watches');
    }
};
