<?php

namespace Tests\Feature;

use App\DatabaseBackupStatus;
use App\Models\DatabaseBackup;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class DatabaseBackupControllerTest extends TestCase
{
    use DatabaseMigrations;

    public function test_only_system_administrator_can_access_database_backups(): void
    {
        $editor = User::factory()->editor()->create();
        $owner = User::factory()->agencyOwner()->create();

        $this->get(route('database-backups.index'))->assertRedirect(route('login'));
        $this->actingAs($editor)->get(route('database-backups.index'))->assertForbidden();
        $this->actingAs($owner)->post(route('database-backups.store'))->assertForbidden();
    }

    public function test_administrator_can_create_encrypted_backup_and_download_valid_sqlite_file(): void
    {
        Storage::fake('local');
        $administrator = User::factory()->systemAdministrator()->create();

        $this->actingAs($administrator)->post(route('database-backups.store'), [
            'label' => '  Yayın öncesi  ',
        ])->assertRedirect(route('database-backups.index'));

        $backup = DatabaseBackup::query()->firstOrFail();
        $this->assertSame(DatabaseBackupStatus::Completed, $backup->status);
        $this->assertSame('Yayın öncesi', $backup->label);
        $this->assertNotNull($backup->checksum);
        Storage::disk('local')->assertExists($backup->path);
        $this->assertStringNotContainsString('SQLite format 3', Storage::disk('local')->get($backup->path));

        $response = $this->actingAs($administrator)->get(route('database-backups.download', $backup));
        $response->assertOk();
        $this->assertStringStartsWith('SQLite format 3', $response->streamedContent());
        $this->assertNotNull($backup->fresh()->verified_at);
    }

    public function test_verification_detects_tampered_backup(): void
    {
        Storage::fake('local');
        $administrator = User::factory()->systemAdministrator()->create();
        $this->actingAs($administrator)->post(route('database-backups.store'));
        $backup = DatabaseBackup::query()->firstOrFail();
        Storage::disk('local')->put($backup->path, 'tampered');

        $this->actingAs($administrator)->post(route('database-backups.verify', $backup))
            ->assertSessionHas('error');

        $this->assertSame(DatabaseBackupStatus::Failed, $backup->fresh()->status);
    }

    public function test_deleting_backup_removes_private_file_and_audit_record(): void
    {
        Storage::fake('local');
        $administrator = User::factory()->systemAdministrator()->create();
        $this->actingAs($administrator)->post(route('database-backups.store'));
        $backup = DatabaseBackup::query()->firstOrFail();

        $this->actingAs($administrator)->delete(route('database-backups.destroy', $backup))
            ->assertRedirect(route('database-backups.index'));

        Storage::disk('local')->assertMissing($backup->path);
        $this->assertDatabaseMissing('database_backups', ['id' => $backup->id]);
    }

    public function test_backup_labels_are_escaped_in_index(): void
    {
        $administrator = User::factory()->systemAdministrator()->create();
        DatabaseBackup::factory()->create(['label' => '<script>alert(1)</script>']);

        $this->actingAs($administrator)->get(route('database-backups.index'))
            ->assertOk()
            ->assertSee('&lt;script&gt;alert(1)&lt;/script&gt;', false)
            ->assertDontSee('<script>alert(1)</script>', false);
    }
}
