<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('admin_news_distributions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('title');
            $table->text('summary');
            $table->longText('body');
            $table->string('image_disk')->default('public');
            $table->text('image_path');
            $table->string('image_original_name')->nullable();
            $table->string('image_mime_type')->nullable();
            $table->string('selection_mode', 30)->index();
            $table->string('province', 100)->nullable()->index();
            $table->unsignedInteger('recipient_agency_count')->default(0);
            $table->unsignedInteger('publication_count')->default(0);
            $table->timestamps();

            $table->index(['created_at', 'created_by']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('admin_news_distributions');
    }
};
