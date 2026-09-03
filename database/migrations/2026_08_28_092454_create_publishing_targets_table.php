<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('publishing_targets', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('agency_id')->constrained()->restrictOnDelete();
            $table->string('name');
            $table->string('base_url', 500);
            $table->string('protocol')->default('wordpress_rest')->index();
            $table->string('username');
            $table->text('credential');
            $table->unsignedBigInteger('default_author_id')->nullable();
            $table->json('default_category_ids')->nullable();
            $table->json('default_tag_ids')->nullable();
            $table->boolean('is_active')->default(true)->index();
            $table->timestamp('last_connected_at')->nullable();
            $table->text('last_error')->nullable();
            $table->softDeletes();
            $table->timestamps();
            $table->unique(['agency_id', 'name']);
            $table->index(['agency_id', 'is_active', 'protocol']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('publishing_targets');
    }
};
