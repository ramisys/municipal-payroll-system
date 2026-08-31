<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\User;
use App\Services\AuditService;
use App\Services\AuthorizationService;
use Illuminate\Http\Request;
use Illuminate\View\View;

// UC-06 · Review audit log — FR-6.1 behavior 3 ("browse and filter ... by
// user, date range, record type, and action") and AC-6.1.5 (the chain
// verifies unbroken). Approver, Administrator, and Viewer only — the FR-6.2
// matrix's "View audit log" row grants no one else, and this is read-only
// for all three: there is no update or delete action anywhere in this
// controller (AC-6.1.2).
class AuditLogController extends Controller
{
    public function __construct(private readonly AuthorizationService $authorizationService) {}

    public function index(Request $request, AuditService $auditService): View
    {
        $this->authorizationService->authorize($request->user(), 'audit_log.view');

        $query = AuditLog::query()->with('user')->orderByDesc('audit_log_id');

        if ($request->filled('user_id')) {
            $query->where('user_id', (int) $request->input('user_id'));
        }

        if ($request->filled('entity_name')) {
            $query->where('entity_name', $request->string('entity_name'));
        }

        if ($request->filled('entity_id')) {
            $query->where('entity_id', (int) $request->input('entity_id'));
        }

        if ($request->filled('action')) {
            $query->where('action', $request->string('action'));
        }

        if ($request->filled('date_from')) {
            $query->whereDate('occurred_at', '>=', $request->input('date_from'));
        }

        if ($request->filled('date_to')) {
            $query->whereDate('occurred_at', '<=', $request->input('date_to'));
        }

        $logs = $query->paginate(50)->withQueryString();

        $verification = $request->boolean('verify') ? $auditService->verifyChain() : null;

        return view('audit-log.index', [
            'logs' => $logs,
            'users' => User::query()->orderBy('username')->get(['user_id', 'username']),
            'actions' => ['CREATE', 'UPDATE', 'DELETE', 'IMPORT', 'EXPORT', 'APPROVE', 'FINALIZE', 'REVERSE', 'LOGIN'],
            'verification' => $verification,
        ]);
    }
}
