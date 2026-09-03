<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('taxonomy_mappings', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('agency_id')->constrained()->cascadeOnDelete();
            $table->foreignId('publishing_target_id')->constrained()->cascadeOnDelete();
            $table->string('type', 20);
            $table->string('source_term', 150);
            $table->string('source_key', 150);
            $table->unsignedBigInteger('remote_id');
            $table->string('remote_name', 150);
            $table->unsignedTinyInteger('priority')->default(50);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['publishing_target_id', 'type', 'source_key']);
            $table->index(['agency_id', 'type', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('taxonomy_mappings');
    }
};
