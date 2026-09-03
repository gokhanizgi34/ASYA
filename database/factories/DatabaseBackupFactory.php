<?php

namespace Database\Factories;

use App\DatabaseBackupStatus;
use App\Models\DatabaseBackup;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/** @extends Factory<DatabaseBackup> */
class DatabaseBackupFactory extends Factory
{
    public function definition(): array
    {
        $id = Str::uuid()->toString();

        return [
            'created_by' => null,
            'label' => fake()->words(2, true),
            'driver' => 'sqlite',
            'disk' => 'local',
            'path' => 'backups/database/'.$id.'.sqlite.enc',
            'original_filename' => 'asya-'.$id.'.sqlite',
            'status' => DatabaseBackupStatus::Completed,
            'size_bytes' => 1024,
            'checksum' => hash('sha256', $id),
            'completed_at' => now(),
            'verified_at' => null,
            'failure_message' => null,
        ];
    }
}
