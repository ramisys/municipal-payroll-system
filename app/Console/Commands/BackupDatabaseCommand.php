<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Services\BackupService;
use Illuminate\Console\Command;

// NFR-5.4 / UC-07. Scheduled database backup.
// System Clock actor triggers this command via the Laravel scheduler.
class BackupDatabaseCommand extends Command
{
    protected $signature = 'payroll:backup {--description= : Optional backup description}';

    protected $description = 'Perform scheduled logical database backup of the municipal payroll system (NFR-5.4)';

    public function handle(BackupService $backupService): int
    {
        $this->info('Starting payroll database backup...');

        $admin = User::query()->where('role_id', 1)->first(); // Admin actor
        $desc = $this->option('description') ?? 'Automated scheduler backup';

        try {
            $meta = $backupService->createBackup($desc, $admin?->user_id);

            $this->info("Backup created successfully: {$meta['filename']}");
            $this->table(
                ['Property', 'Value'],
                [
                    ['File', $meta['filename']],
                    ['Size', number_format($meta['size_bytes'] / 1024, 2).' KB'],
                    ['Tables', $meta['tables_count']],
                    ['Total Rows', number_format($meta['total_rows'])],
                    ['SHA-256', $meta['sha256']],
                    ['Created At', $meta['created_at']],
                ]
            );

            return Command::SUCCESS;
        } catch (\Throwable $e) {
            $this->error("Backup failed: {$e->getMessage()}");

            return Command::FAILURE;
        }
    }
}
