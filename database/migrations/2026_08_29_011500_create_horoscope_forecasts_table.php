<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('horoscope_forecasts', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('agency_id')->constrained()->cascadeOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->date('forecast_date');
            $table->string('sign', 30);
            $table->string('status', 20)->default('draft');
            $table->text('general')->nullable();
            $table->text('love')->nullable();
            $table->text('career')->nullable();
            $table->text('money')->nullable();
            $table->text('health')->nullable();
            $table->string('lucky_color', 50)->nullable();
            $table->unsignedTinyInteger('lucky_number')->nullable();
            $table->timestamp('published_at')->nullable();
            $table->timestamps();

            $table->unique(['agency_id', 'forecast_date', 'sign']);
            $table->index(['agency_id', 'status', 'forecast_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('horoscope_forecasts');
    }
};
