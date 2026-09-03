<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('agency_mail_settings', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('agency_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('host', 255);
            $table->unsignedSmallInteger('port')->default(587);
            $table->string('scheme', 20)->default('smtp');
            $table->string('username')->nullable();
            $table->text('password')->nullable();
            $table->string('from_address', 255);
            $table->string('from_name', 150);
            $table->string('notification_email', 255);
            $table->boolean('is_active')->default(true);
            $table->timestamp('last_tested_at')->nullable();
            $table->text('last_error')->nullable();
            $table->timestamps();

            $table->unique('agency_id');
            $table->index(['is_active', 'agency_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('agency_mail_settings');
    }
};
