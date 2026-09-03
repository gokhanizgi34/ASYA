<?php

namespace App\Http\Controllers;

use App\Models\DatabaseBackup;
use App\Services\DatabaseBackupManager;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;

class DatabaseBackupVerifyController extends Controller
{
    public function __invoke(DatabaseBackup $databaseBackup, DatabaseBackupManager $manager): RedirectResponse
    {
        Gate::authorize('view', $databaseBackup);
        $isValid = $manager->verify($databaseBackup);

        return redirect()->route('database-backups.index')->with(
            $isValid ? 'success' : 'error',
            $isValid ? 'Yedek bütünlüğü ve şifrelemesi doğrulandı.' : 'Yedek doğrulanamadı; dosya kayıp veya değiştirilmiş.',
        );
    }
}
