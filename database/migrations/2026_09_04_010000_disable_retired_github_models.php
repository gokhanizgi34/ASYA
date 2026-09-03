<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('api_integrations')
            ->where('provider', 'github_models')
            ->update([
                'is_active' => false,
                'last_error' => 'GitHub Models, 30.07.2026 tarihinde emekliye ayrıldı; bu entegrasyon artık kullanılamaz.',
            ]);
    }

    public function down(): void
    {
        // Emekli bir servisi otomatik olarak yeniden etkinleştirmemek için boş bırakıldı.
    }
};
