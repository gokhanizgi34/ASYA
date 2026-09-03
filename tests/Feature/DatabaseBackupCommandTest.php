<?php

namespace Tests\Feature;

use App\DatabaseBackupStatus;
use App\Models\DatabaseBackup;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class DatabaseBackupCommandTest extends TestCase
{
    use DatabaseMigrations;

    public function test_command_creates_encrypted_daily_database_backup(): void
    {
        Storage::fake('local');
        User::factory()->systemAdministrator()->create();

        $this->artisan('database:backup')->assertSuccessful();

        $backup = DatabaseBackup::query()->sole();
        $this->assertSame(DatabaseBackupStatus::Completed, $backup->status);
        $this->assertSame('Otomatik günlük yedek', $backup->label);
        Storage::disk('local')->assertExists($backup->path);
    }
}
