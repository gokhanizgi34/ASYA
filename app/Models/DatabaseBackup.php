<?php

namespace App\Models;

use App\DatabaseBackupStatus;
use Database\Factories\DatabaseBackupFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['created_by', 'label', 'driver', 'disk', 'path', 'original_filename', 'status', 'size_bytes', 'checksum', 'completed_at', 'verified_at', 'failure_message'])]
class DatabaseBackup extends Model
{
    /** @use HasFactory<DatabaseBackupFactory> */
    use HasFactory;

    /** @return BelongsTo<User, $this> */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'status' => DatabaseBackupStatus::class,
            'completed_at' => 'datetime',
            'verified_at' => 'datetime',
            'size_bytes' => 'integer',
        ];
    }
}
