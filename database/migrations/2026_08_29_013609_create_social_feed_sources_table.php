<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('social_feed_sources', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('agency_id')->constrained()->cascadeOnDelete();
            $table->foreignId('social_listening_watch_id')->constrained()->cascadeOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('name', 120);
            $table->string('platform', 30);
            $table->string('source_type', 30)->default('json_manual');
            $table->string('endpoint_url', 2048)->nullable();
            $table->text('auth_secret')->nullable();
            $table->json('field_map');
            $table->boolean('is_active')->default(true);
            $table->timestamp('last_imported_at')->nullable();
            $table->timestamps();

            $table->unique(['agency_id', 'name']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('social_feed_sources');
    }
};
