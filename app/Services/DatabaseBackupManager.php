<?php

namespace App\Services;

use App\DatabaseBackupStatus;
use App\Models\DatabaseBackup;
use App\Models\User;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

class DatabaseBackupManager
{
    public function create(User $user, ?string $label = null): DatabaseBackup
    {
        $driver = DB::connection()->getDriverName();

        if ($driver !== 'sqlite') {
            throw new RuntimeException('Bu sürümde yalnızca SQLite veritabanı yedeklenebilir.');
        }

        $identifier = now()->format('Ymd-His').'-'.Str::uuid();
        $originalFilename = 'asya-'.$identifier.'.sqlite';
        $path = 'backups/database/'.$originalFilename.'.enc';
        $temporaryPath = storage_path('app/backup-temp/'.$originalFilename);

        $backup = DatabaseBackup::query()->create([
            'created_by' => $user->id,
            'label' => $label,
            'driver' => $driver,
            'disk' => 'local',
            'path' => $path,
            'original_filename' => $originalFilename,
            'status' => DatabaseBackupStatus::Pending,
        ]);

        try {
            File::ensureDirectoryExists(dirname($temporaryPath));
            File::delete($temporaryPath);
            DB::statement('VACUUM INTO ?', [$temporaryPath]);

            $encryptedContents = Crypt::encryptString(File::get($temporaryPath));
            throw_unless(Storage::disk('local')->put($path, $encryptedContents), RuntimeException::class, 'Yedek dosyası kaydedilemedi.');

            $storedContents = Storage::disk('local')->get($path);
            $backup->update([
                'status' => DatabaseBackupStatus::Completed,
                'size_bytes' => strlen($storedContents),
                'checksum' => hash('sha256', $storedContents),
                'completed_at' => now(),
                'failure_message' => null,
            ]);
        } catch (Throwable $exception) {
            Storage::disk('local')->delete($path);
            $backup->update([
                'status' => DatabaseBackupStatus::Failed,
                'failure_message' => Str::limit($exception->getMessage(), 500, ''),
            ]);

            throw $exception;
        } finally {
            File::delete($temporaryPath);
        }

        return $backup->refresh();
    }

    public function verify(DatabaseBackup $backup): bool
    {
        if ($backup->status !== DatabaseBackupStatus::Completed || ! Storage::disk($backup->disk)->exists($backup->path)) {
            $backup->update(['status' => DatabaseBackupStatus::Missing, 'verified_at' => null]);

            return false;
        }

        $contents = Storage::disk($backup->disk)->get($backup->path);
        $isValid = hash_equals((string) $backup->checksum, hash('sha256', $contents));

        if ($isValid) {
            Crypt::decryptString($contents);
            $backup->update(['verified_at' => now(), 'failure_message' => null]);

            return true;
        }

        $backup->update(['status' => DatabaseBackupStatus::Failed, 'verified_at' => null, 'failure_message' => 'Yedek bütünlük doğrulamasını geçemedi.']);

        return false;
    }

    public function decryptedContents(DatabaseBackup $backup): string
    {
        throw_unless($this->verify($backup), RuntimeException::class, 'Yedek dosyası doğrulanamadı.');

        return Crypt::decryptString(Storage::disk($backup->disk)->get($backup->path));
    }

    public function delete(DatabaseBackup $backup): void
    {
        Storage::disk($backup->disk)->delete($backup->path);
        $backup->delete();
    }
}
