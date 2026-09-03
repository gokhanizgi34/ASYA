<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('system_notifications', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('agency_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('recipient_user_id')->constrained('users')->cascadeOnDelete();
            $table->string('type', 80);
            $table->string('severity', 20);
            $table->string('title', 180);
            $table->text('message');
            $table->string('action_route', 150)->nullable();
            $table->json('action_parameters')->nullable();
            $table->string('fingerprint', 64);
            $table->unsignedInteger('occurrences')->default(1);
            $table->timestamp('first_occurred_at');
            $table->timestamp('last_occurred_at');
            $table->timestamp('read_at')->nullable();
            $table->timestamps();

            $table->unique(['recipient_user_id', 'fingerprint']);
            $table->index(['recipient_user_id', 'read_at', 'last_occurred_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('system_notifications');
    }
};
