<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('source_trust_assessments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('agency_id')->constrained()->cascadeOnDelete();
            $table->foreignId('news_source_id')->constrained()->cascadeOnDelete();
            $table->foreignId('assessed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->unsignedTinyInteger('identity_transparency');
            $table->unsignedTinyInteger('evidence_quality');
            $table->unsignedTinyInteger('correction_policy');
            $table->unsignedTinyInteger('historical_accuracy');
            $table->unsignedTinyInteger('editorial_independence');
            $table->decimal('weighted_score', 5, 2);
            $table->string('trust_band', 20);
            $table->text('notes');
            $table->timestamp('assessed_at');
            $table->timestamps();

            $table->index(['agency_id', 'assessed_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('source_trust_assessments');
    }
};
