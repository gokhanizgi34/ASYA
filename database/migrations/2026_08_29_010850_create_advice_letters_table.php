<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('advice_letters', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('agency_id')->constrained()->cascadeOnDelete();
            $table->foreignId('submitted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('answered_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('pseudonym', 80);
            $table->string('category', 40);
            $table->longText('question');
            $table->string('status', 30)->default('pending');
            $table->string('risk_level', 20)->default('low');
            $table->json('risk_flags')->nullable();
            $table->boolean('publication_consent')->default(false);
            $table->string('response_title', 180)->nullable();
            $table->longText('response_body')->nullable();
            $table->timestamp('answered_at')->nullable();
            $table->timestamp('published_at')->nullable();
            $table->softDeletes();
            $table->timestamps();

            $table->index(['agency_id', 'status', 'created_at']);
            $table->index(['agency_id', 'risk_level']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('advice_letters');
    }
};
