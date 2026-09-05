<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('editorial_style_profiles', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('agency_id')->unique()->constrained()->cascadeOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('name')->default('Ajans yazım dili');
            $table->longText('sample_text')->nullable();
            $table->json('learned_terms')->nullable();
            $table->json('replacements')->nullable();
            $table->json('forbidden_terms')->nullable();
            $table->unsignedSmallInteger('daily_quota')->default(50);
            $table->string('destination')->default('publish');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->index(['is_active', 'destination']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('editorial_style_profiles');
    }
};
