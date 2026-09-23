<?php

namespace App\Services;

use App\Models\PayrollRun;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;
use RuntimeException;

// system-architecture.md §8.2 / NFR-5.4 / UC-07 / NFR-6.3.
// Scheduled database backup with a documented restore procedure.
// Triggered by System Clock actor via Scheduler or on-demand by Administrator.
class BackupService
{
    private string $backupDir;

    public function __construct(
        private readonly AuditService $auditService,
    ) {
        $this->backupDir = storage_path('app/backups');
        if (! File::exists($this->backupDir)) {
            File::makeDirectory($this->backupDir, 0755, true);
        }
    }

    /**
     * Create a complete logical SQL dump of the payroll database (NFR-5.4).
     *
     * @return array{
     *     filename: string,
     *     filepath: string,
     *     size_bytes: int,
     *     sha256: string,
     *     tables_count: int,
     *     total_rows: int,
     *     created_at: string
     * }
     */
    public function createBackup(?string $description = null, ?int $actorUserId = null): array
    {
        $database = config('database.connections.mysql.database');
        $timestamp = now()->format('Ymd_His');
        $baseName = "payroll_backup_{$timestamp}";
        $sqlPath = "{$this->backupDir}/{$baseName}.sql";
        $gzPath = "{$sqlPath}.gz";
        $metaPath = "{$this->backupDir}/{$baseName}.json";

        // Query all base tables in database
        $tables = DB::select('SELECT table_name FROM information_schema.tables WHERE table_schema = ? AND table_type = "BASE TABLE" ORDER BY table_name', [$database]);
        $tableNames = array_map(fn ($t) => $t->TABLE_NAME ?? $t->table_name, $tables);

        $handle = fopen($sqlPath, 'w');
        if (! $handle) {
            throw new RuntimeException("Unable to open backup file for writing at {$sqlPath}");
        }

        // Header
        fwrite($handle, "-- Municipal Payroll System Database Backup\n");
        fwrite($handle, '-- Generated: '.now()->toIso8601String()."\n");
        fwrite($handle, "-- Database: {$database}\n\n");
        fwrite($handle, "SET FOREIGN_KEY_CHECKS=0;\n");
        fwrite($handle, "SET SQL_MODE='NO_AUTO_VALUE_ON_ZERO';\n");
        fwrite($handle, "SET AUTOCOMMIT=0;\n");
        fwrite($handle, "START TRANSACTION;\n\n");

        $totalRows = 0;

        foreach ($tableNames as $tbl) {
            // Write DROP & CREATE TABLE
            fwrite($handle, "-- Table structure for `{$tbl}`\n");
            fwrite($handle, "DROP TABLE IF EXISTS `{$tbl}`;\n");

            $createRes = DB::select("SHOW CREATE TABLE `{$tbl}`");
            $createSql = $createRes[0]->{'Create Table'} ?? $createRes[0]->{'create table'} ?? null;
            if ($createSql) {
                fwrite($handle, "{$createSql};\n\n");
            }

            // Dump rows in chunks
            $count = DB::table($tbl)->count();
            $totalRows += $count;

            if ($count > 0) {
                fwrite($handle, "-- Dumping data for `{$tbl}` ({$count} rows)\n");
                $colRecords = DB::select('
                    SELECT COLUMN_NAME 
                    FROM information_schema.COLUMNS 
                    WHERE TABLE_SCHEMA = ? 
                      AND TABLE_NAME = ? 
                      AND EXTRA NOT LIKE \'%GENERATED%\' 
                    ORDER BY ORDINAL_POSITION',
                    [$database, $tbl]
                );
                $columns = array_map(fn ($c) => $c->COLUMN_NAME ?? $c->column_name, $colRecords);
                $escapedCols = array_map(fn ($c) => "`{$c}`", $columns);
                $colList = implode(', ', $escapedCols);

                DB::table($tbl)->select($columns)->orderBy($columns[0])->chunk(200, function ($rows) use ($handle, $tbl, $colList) {
                    $insertVals = [];
                    foreach ($rows as $row) {
                        $rowVals = [];
                        foreach ((array) $row as $val) {
                            if ($val === null) {
                                $rowVals[] = 'NULL';
                            } elseif (is_numeric($val)) {
                                $rowVals[] = (string) $val;
                            } else {
                                $escaped = addslashes((string) $val);
                                $escaped = str_replace(["\r", "\n"], ['\\r', '\\n'], $escaped);
                                $rowVals[] = "'{$escaped}'";
                            }
                        }
                        $insertVals[] = '('.implode(', ', $rowVals).')';
                    }
                    if (! empty($insertVals)) {
                        fwrite($handle, "INSERT INTO `{$tbl}` ({$colList}) VALUES\n".implode(",\n", $insertVals).";\n");
                    }
                });
                fwrite($handle, "\n");
            }
        }

        // Dump Triggers (re-created after tables and rows so triggers do not interfere with bulk restore)
        $triggers = DB::select('SELECT TRIGGER_NAME FROM information_schema.TRIGGERS WHERE TRIGGER_SCHEMA = ?', [$database]);
        if (! empty($triggers)) {
            fwrite($handle, "-- Database Triggers\n");
            foreach ($triggers as $trig) {
                $trigName = $trig->TRIGGER_NAME ?? $trig->trigger_name;
                $createTrigRes = DB::select("SHOW CREATE TRIGGER `{$trigName}`");
                $createTrigSql = $createTrigRes[0]->{'SQL Original Statement'} ?? $createTrigRes[0]->{'sql original statement'} ?? null;
                if ($createTrigSql) {
                    fwrite($handle, "DROP TRIGGER IF EXISTS `{$trigName}`;\n");
                    fwrite($handle, "{$createTrigSql};\n\n");
                }
            }
        }

        // Footer
        fwrite($handle, "COMMIT;\n");
        fwrite($handle, "SET FOREIGN_KEY_CHECKS=1;\n");
        fclose($handle);

        // Gzip compression
        $sqlContent = file_get_contents($sqlPath);
        $gzContent = gzencode($sqlContent, 9);
        file_put_contents($gzPath, $gzContent);
        unlink($sqlPath); // retain compressed archive

        $size = filesize($gzPath);
        $sha256 = hash_file('sha256', $gzPath);
        $createdAt = now()->toIso8601String();

        $meta = [
            'filename' => "{$baseName}.sql.gz",
            'filepath' => $gzPath,
            'size_bytes' => $size,
            'sha256' => $sha256,
            'tables_count' => count($tableNames),
            'total_rows' => $totalRows,
            'description' => $description ?? 'Scheduled automated backup',
            'created_by' => $actorUserId,
            'created_at' => $createdAt,
        ];

        file_put_contents($metaPath, json_encode($meta, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

        // Audit backup event
        if ($actorUserId && ($user = User::find($actorUserId))) {
            $this->auditService->record(
                $user,
                'DATABASE_BACKUP',
                null,
                'EXPORT',
                null,
                [
                    'filename' => $meta['filename'],
                    'size_bytes' => $size,
                    'sha256' => $sha256,
                    'total_rows' => $totalRows,
                ]
            );
        }

        return $meta;
    }

    /**
     * List all available backups.
     *
     * @return array<int, array<string, mixed>>
     */
    public function listBackups(): array
    {
        $metaFiles = File::glob("{$this->backupDir}/*.json");
        $backups = [];

        foreach ($metaFiles as $mf) {
            $content = json_decode(file_get_contents($mf), true);
            if ($content && isset($content['filename'])) {
                $archiveExists = File::exists("{$this->backupDir}/{$content['filename']}");
                $content['file_exists'] = $archiveExists;
                $backups[] = $content;
            }
        }

        usort($backups, fn ($a, $b) => strcmp($b['created_at'] ?? '', $a['created_at'] ?? ''));

        return $backups;
    }

    /**
     * Verify backup integrity and checksum.
     */
    public function verifyBackup(string $filename): array
    {
        $baseName = pathinfo($filename, PATHINFO_FILENAME);
        if (str_ends_with($baseName, '.sql')) {
            $baseName = pathinfo($baseName, PATHINFO_FILENAME);
        }

        $gzPath = "{$this->backupDir}/{$filename}";
        $metaPath = "{$this->backupDir}/{$baseName}.json";

        if (! File::exists($gzPath)) {
            return ['valid' => false, 'error' => "Backup archive '{$filename}' not found."];
        }

        $currentSha256 = hash_file('sha256', $gzPath);

        if (File::exists($metaPath)) {
            $meta = json_decode(file_get_contents($metaPath), true);
            if (isset($meta['sha256']) && $meta['sha256'] !== $currentSha256) {
                return [
                    'valid' => false,
                    'error' => "Checksum mismatch! Recorded: {$meta['sha256']}, computed: {$currentSha256}.",
                ];
            }
        }

        // Test decompression
        $gz = gzopen($gzPath, 'rb');
        if (! $gz) {
            return ['valid' => false, 'error' => 'Unable to read compressed gzip archive.'];
        }
        $header = gzread($gz, 100);
        gzclose($gz);

        if (! str_contains($header, 'Municipal Payroll System')) {
            return ['valid' => false, 'error' => 'Archive header does not match expected signature.'];
        }

        return [
            'valid' => true,
            'sha256' => $currentSha256,
            'size_bytes' => filesize($gzPath),
        ];
    }

    /**
     * Restore database from backup archive (NFR-5.4, UC-07 A1, E2, NFR-6.3).
     *
     * @return array{
     *     success: bool,
     *     filename: string,
     *     restored_at: string
     * }
     */
    public function restoreBackup(string $filename, int $actorUserId, bool $force = false): array
    {
        // Precondition: check for open payroll runs in progress (UC-07 E2)
        $openRunsCount = PayrollRun::query()
            ->whereIn('run_status', ['DRAFT', 'FOR_REVIEW', 'APPROVED'])
            ->count();

        if ($openRunsCount > 0 && ! $force) {
            throw new InvalidArgumentException(
                "A payroll run is currently in progress ({$openRunsCount} active run(s)). Restoring now will discard uncommitted changes. Explicit force confirmation required (UC-07 E2)."
            );
        }

        $verification = $this->verifyBackup($filename);
        if (! $verification['valid']) {
            throw new RuntimeException("Backup verification failed: {$verification['error']}");
        }

        $gzPath = "{$this->backupDir}/{$filename}";
        $sql = gzdecode(file_get_contents($gzPath));
        if ($sql === false) {
            throw new RuntimeException("Failed to decompress backup archive {$filename}.");
        }

        // Execute logical restore stream
        DB::unprepared($sql);

        // Record restore in audit log
        if ($actorUserId && ($user = User::find($actorUserId))) {
            $this->auditService->record(
                $user,
                'DATABASE_BACKUP',
                null,
                'IMPORT',
                null,
                [
                    'restored_file' => $filename,
                    'sha256' => $verification['sha256'],
                    'force' => $force,
                ]
            );
        }

        return [
            'success' => true,
            'filename' => $filename,
            'restored_at' => now()->toIso8601String(),
        ];
    }
}
