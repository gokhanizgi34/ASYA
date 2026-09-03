<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Services\DatabaseBackupManager;
use App\UserRole;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Throwable;

#[Signature('database:backup')]
#[Description('SQLite veritabanının şifreli ve doğrulanabilir yedeğini oluşturur')]
class BackupDatabase extends Command
{
    public function handle(DatabaseBackupManager $manager): int
    {
        $user = User::query()
            ->where('role', UserRole::SystemAdministrator)
            ->where('is_active', true)
            ->orderBy('id')
            ->first();

        if (! $user) {
            $this->error('Yedek sahibi olacak aktif bir sistem yöneticisi bulunamadı.');

            return self::FAILURE;
        }

        try {
            $backup = $manager->create($user, 'Otomatik günlük yedek');
        } catch (Throwable $exception) {
            report($exception);
            $this->error('Veritabanı yedeği oluşturulamadı: '.$exception->getMessage());

            return self::FAILURE;
        }

        $this->info($backup->original_filename.' başarıyla oluşturuldu.');

        return self::SUCCESS;
    }
}
