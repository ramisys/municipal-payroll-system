<?php

namespace App\Http\Controllers;

use App\Models\PayrollRun;
use App\Services\AuthorizationService;
use App\Services\BackupService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

// UC-07 · Back up and restore database — NFR-5.4, NFR-6.3.
// Primary actor: Administrator ('backup.run_restore')
class BackupController extends Controller
{
    public function __construct(
        private readonly AuthorizationService $authorizationService,
        private readonly BackupService $backupService,
    ) {}

    /**
     * List available database backups.
     */
    public function index(Request $request): View
    {
        $this->authorizationService->authorize($request->user(), 'backup.run_restore');

        $backups = $this->backupService->listBackups();
        $openRunsCount = PayrollRun::query()
            ->whereIn('run_status', ['DRAFT', 'FOR_REVIEW', 'APPROVED'])
            ->count();

        return view('backups.index', [
            'backups' => $backups,
            'openRunsCount' => $openRunsCount,
        ]);
    }

    /**
     * Trigger manual on-demand backup.
     */
    public function store(Request $request): RedirectResponse
    {
        $this->authorizationService->authorize($request->user(), 'backup.run_restore');

        try {
            $meta = $this->backupService->createBackup(
                description: 'Manual backup triggered via Web UI',
                actorUserId: $request->user()->user_id
            );

            return redirect()->route('backups.index')
                ->with('success', "Database backup created successfully: {$meta['filename']} ({$meta['total_rows']} rows, {$meta['tables_count']} tables).");
        } catch (\Throwable $e) {
            return redirect()->route('backups.index')
                ->with('error', "Backup failed: {$e->getMessage()}");
        }
    }

    /**
     * Download backup archive.
     */
    public function download(Request $request, string $filename): BinaryFileResponse|RedirectResponse
    {
        $this->authorizationService->authorize($request->user(), 'backup.run_restore');

        $path = storage_path("app/backups/{$filename}");
        if (! file_exists($path)) {
            return redirect()->route('backups.index')
                ->with('error', "Backup file '{$filename}' not found on disk.");
        }

        return response()->download($path, $filename, [
            'Content-Type' => 'application/gzip',
        ]);
    }

    /**
     * Restore database from backup archive with confirmation (NFR-6.3, UC-07 A1, E2).
     */
    public function restore(Request $request): RedirectResponse
    {
        $this->authorizationService->authorize($request->user(), 'backup.run_restore');

        $validated = $request->validate([
            'filename' => ['required', 'string'],
            'confirmation_text' => ['required', 'string'],
            'force' => ['nullable', 'boolean'],
        ]);

        if (trim($validated['confirmation_text']) !== trim($validated['filename'])) {
            return redirect()->route('backups.index')
                ->with('error', "Confirmation failed. You must type the exact filename '{$validated['filename']}' to confirm restore (NFR-6.3).");
        }

        $force = (bool) ($validated['force'] ?? false);

        try {
            $result = $this->backupService->restoreBackup(
                filename: $validated['filename'],
                actorUserId: $request->user()->user_id,
                force: $force
            );

            return redirect()->route('backups.index')
                ->with('success', "Database restored successfully from backup '{$result['filename']}' at {$result['restored_at']}.");
        } catch (\InvalidArgumentException $e) {
            return redirect()->route('backups.index')
                ->with('error', $e->getMessage());
        } catch (\Throwable $e) {
            return redirect()->route('backups.index')
                ->with('error', "Restore failed: {$e->getMessage()}");
        }
    }
}
