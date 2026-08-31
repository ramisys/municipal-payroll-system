<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Services\AuditService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

// UC-01 A1 (first sign-in) and FR-0.2 behavior 5 (voluntary change) share
// this one form: both require re-entering the current password (AC-0.2.4
// for the former, FR-0.2 behavior 5 for the latter) before a new one is
// accepted. BR-30 — the audit entry records that a change happened, never
// the password values themselves.
class PasswordChangeController extends Controller
{
    public function edit(): View
    {
        return view('auth.password-change');
    }

    public function update(Request $request, AuditService $auditService): RedirectResponse
    {
        $data = $request->validate([
            'current_password' => ['required', 'string'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        $user = Auth::user();

        if (! $user->verifyPassword($data['current_password'])) {
            return back()->withErrors(['current_password' => 'The current password is incorrect.']);
        }

        $user->setPassword($data['password']);
        $user->must_change_password = false;
        $user->save();

        $auditService->record(
            user: $user,
            entityName: 'USER',
            entityId: $user->user_id,
            action: 'UPDATE',
            previousValues: ['must_change_password' => true],
            newValues: ['must_change_password' => false],
        );

        return redirect()->route('dashboard')->with('status', 'Password changed.');
    }
}
