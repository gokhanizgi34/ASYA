<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreDatabaseBackupRequest;
use App\Models\DatabaseBackup;
use App\Models\User;
use App\Services\DatabaseBackupManager;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;
use Throwable;

class DatabaseBackupController extends Controller
{
    public function index(): View
    {
        Gate::authorize('viewAny', DatabaseBackup::class);

        return view('database-backups.index', [
            'backups' => DatabaseBackup::query()->with('creator')->latest()->paginate(30),
        ]);
    }

    public function store(StoreDatabaseBackupRequest $request, DatabaseBackupManager $manager): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user instanceof User, 401);

        try {
            $backup = $manager->create($user, $request->validated('label'));
        } catch (Throwable $exception) {
            report($exception);

            return redirect()->route('database-backups.index')->with('error', 'Veritabanı yedeği oluşturulamadı. Hata kaydı oluşturuldu.');
        }

        return redirect()->route('database-backups.index')->with('success', "{$backup->original_filename} güvenli biçimde oluşturuldu.");
    }

    public function destroy(DatabaseBackup $databaseBackup, DatabaseBackupManager $manager): RedirectResponse
    {
        Gate::authorize('delete', $databaseBackup);
        $manager->delete($databaseBackup);

        return redirect()->route('database-backups.index')->with('success', 'Yedek dosyası ve kaydı silindi.');
    }
}
