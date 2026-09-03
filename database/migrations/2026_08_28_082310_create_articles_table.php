<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('articles', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('agency_id')->constrained()->restrictOnDelete();
            $table->foreignId('author_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('title');
            $table->string('slug');
            $table->text('summary')->nullable();
            $table->longText('body');
            $table->string('status')->default('draft')->index();
            $table->string('source_trust_status')->default('unverified')->index();
            $table->string('source_name')->nullable();
            $table->text('source_url')->nullable();
            $table->timestamp('published_at')->nullable()->index();
            $table->text('failure_message')->nullable();
            $table->softDeletes();
            $table->timestamps();

            $table->unique(['agency_id', 'slug']);
            $table->index(['agency_id', 'status', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('articles');
    }
};
