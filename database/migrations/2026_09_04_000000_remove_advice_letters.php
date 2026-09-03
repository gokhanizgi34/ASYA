<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('advice_letters');
    }

    public function down(): void
    {
        // The removed module is intentionally not recreated on rollback.
    }
};