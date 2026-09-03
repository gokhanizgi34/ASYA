<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('news_sources', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('agency_id')->constrained()->cascadeOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('name', 160);
            $table->string('domain', 190);
            $table->string('source_type', 40);
            $table->text('notes')->nullable();
            $table->boolean('is_active')->default(true);
            $table->decimal('latest_score', 5, 2)->nullable();
            $table->string('latest_band', 20)->nullable();
            $table->timestamp('last_assessed_at')->nullable();
            $table->timestamps();

            $table->unique(['agency_id', 'domain']);
            $table->index(['agency_id', 'latest_band']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('news_sources');
    }
};
