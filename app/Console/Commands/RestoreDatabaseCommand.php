<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Services\BackupService;
use Illuminate\Console\Command;

// NFR-5.4 / UC-07 / NFR-6.3. Restore database from backup archive.
class RestoreDatabaseCommand extends Command
{
    protected $signature = 'payroll:restore {filename : The backup archive filename} {--force : Force restore even if an open payroll run is in progress}';

    protected $description = 'Restore the municipal payroll database from a verified backup archive (NFR-5.4)';

    public function handle(BackupService $backupService): int
    {
        $filename = $this->argument('filename');
        $force = (bool) $this->option('force');

        $this->warn("Database restore requested for archive: {$filename}");

        if (! $force && ! $this->confirm("Are you sure you want to restore the database from '{$filename}'? All current data will be replaced!")) {
            $this->info('Restore aborted.');

            return Command::SUCCESS;
        }

        $admin = User::query()->where('role_id', 1)->first();

        try {
            $result = $backupService->restoreBackup($filename, (int) ($admin?->user_id ?? 1), $force);

            $this->info("Database restored successfully from {$result['filename']} at {$result['restored_at']}");

            return Command::SUCCESS;
        } catch (\Throwable $e) {
            $this->error("Restore failed: {$e->getMessage()}");

            return Command::FAILURE;
        }
    }
}
