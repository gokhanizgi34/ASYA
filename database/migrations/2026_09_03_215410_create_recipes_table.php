<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('recipes', function (Blueprint $table): void {
            $table->id();
            $table->string('category', 30)->index();
            $table->string('title', 180);
            $table->text('ingredients');
            $table->longText('instructions');
            $table->boolean('is_active')->default(true)->index();
            $table->timestamp('last_selected_at')->nullable()->index();
            $table->timestamps();
            $table->unique(['category', 'title']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('recipes');
    }
};
