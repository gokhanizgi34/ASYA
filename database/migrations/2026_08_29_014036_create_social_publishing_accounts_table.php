<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('social_publishing_accounts', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('agency_id')->constrained()->cascadeOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('name', 120);
            $table->string('platform', 30);
            $table->string('account_handle', 120);
            $table->text('access_token');
            $table->string('publish_mode', 30)->default('local_sandbox');
            $table->boolean('is_active')->default(true);
            $table->timestamp('last_published_at')->nullable();
            $table->timestamps();

            $table->unique(['agency_id', 'platform', 'account_handle']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('social_publishing_accounts');
    }
};
