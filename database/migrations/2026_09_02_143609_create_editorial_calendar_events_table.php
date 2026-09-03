<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('editorial_calendar_events', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('agency_id')->constrained()->cascadeOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->date('event_date');
            $table->date('content_due_at');
            $table->string('title', 180);
            $table->json('seo_topics');
            $table->string('status', 40)->default('planned');
            $table->string('ai_provider', 120)->nullable();
            $table->timestamps();
            $table->unique(['agency_id', 'event_date', 'title'], 'editorial_event_unique');
            $table->index(['agency_id', 'event_date', 'status'], 'editorial_event_calendar');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('editorial_calendar_events');
    }
};
