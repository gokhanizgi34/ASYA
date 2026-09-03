<?php

namespace App\Http\Controllers;

use App\Models\DatabaseBackup;
use App\Services\DatabaseBackupManager;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DatabaseBackupDownloadController extends Controller
{
    public function __invoke(DatabaseBackup $databaseBackup, DatabaseBackupManager $manager): StreamedResponse
    {
        Gate::authorize('view', $databaseBackup);
        $contents = $manager->decryptedContents($databaseBackup);

        return response()->streamDownload(
            static function () use ($contents): void {
                echo $contents;
            },
            $databaseBackup->original_filename,
            ['Content-Type' => 'application/vnd.sqlite3', 'Cache-Control' => 'no-store, private'],
        );
    }
}
