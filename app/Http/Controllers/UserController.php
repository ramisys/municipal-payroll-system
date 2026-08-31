<?php

namespace App\Http\Controllers;

use App\Models\Role;
use App\Models\User;
use App\Services\AuditService;
use App\Services\AuthorizationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

// UC-02 · Manage user accounts — FR-0.2, BR-30, BR-33. Administrator only
// (FR-6.2 matrix row "Maintain users and roles"): AuthorizationService is
// consulted explicitly at the top of every action, matching the call-graph
// convention SessionController and PasswordChangeController already set
// (system-architecture.md §7.2) rather than framework Gate/Policy sugar.
//
// AC-0.2.2 — deletion is never offered here, only deactivate/reactivate;
// there is no destroy() action.
class UserController extends Controller
{
    public function __construct(
        private readonly AuthorizationService $authorizationService,
        private readonly AuditService $auditService,
    ) {}

    public function index(Request $request): View
    {
        $this->authorizationService->authorize($request->user(), 'users.manage');

        $users = User::query()->with('role')->orderBy('username')->get();

        return view('users.index', ['users' => $users]);
    }

    public function create(Request $request): View
    {
        $this->authorizationService->authorize($request->user(), 'users.manage');

        return view('users.create', ['roles' => $this->activeRoles()]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorizationService->authorize($request->user(), 'users.manage');

        // AC-0.2.1 — usernames are unique across all accounts, active and
        // inactive; the base `unique` rule already checks every row.
        $data = $request->validate([
            'username' => ['required', 'string', 'max:255', 'unique:users,username'],
            'role_id' => ['required', 'integer', 'exists:roles,role_id'],
            'password' => ['required', 'string', 'min:8'],
        ]);

        $user = new User([
            'role_id' => $data['role_id'],
            'username' => $data['username'],
            // AC-0.2.4 — an account created by an Administrator cannot be
            // used for payroll work until its initial password has been
            // changed (behavior 2). EnsurePasswordIsChanged enforces it.
            'must_change_password' => true,
            'is_active' => true,
        ]);
        $user->setPassword($data['password']);
        $user->save();

        $this->auditService->record(
            user: $request->user(),
            entityName: 'USER',
            entityId: $user->user_id,
            action: 'CREATE',
            newValues: ['username' => $user->username, 'role_id' => $user->role_id],
        );

        return redirect()->route('users.index')->with('status', "Account '{$user->username}' created.");
    }

    public function edit(Request $request, User $user): View
    {
        $this->authorizationService->authorize($request->user(), 'users.manage');

        return view('users.edit', ['targetUser' => $user, 'roles' => $this->activeRoles()]);
    }

    public function update(Request $request, User $user): RedirectResponse
    {
        $this->authorizationService->authorize($request->user(), 'users.manage');

        $data = $request->validate([
            'username' => ['required', 'string', 'max:255', Rule::unique('users', 'username')->ignore($user->user_id, 'user_id')],
            'role_id' => ['required', 'integer', 'exists:roles,role_id'],
        ]);

        $previousValues = ['username' => $user->username, 'role_id' => $user->role_id];

        $user->username = $data['username'];
        $user->role_id = $data['role_id'];
        $user->save();

        $this->auditService->record(
            user: $request->user(),
            entityName: 'USER',
            entityId: $user->user_id,
            action: 'UPDATE',
            previousValues: $previousValues,
            newValues: ['username' => $user->username, 'role_id' => $user->role_id],
        );

        return redirect()->route('users.index')->with('status', "Account '{$user->username}' updated.");
    }

    // FR-0.2 behavior 3 — deactivate; the account is never deleted.
    public function deactivate(Request $request, User $user): RedirectResponse
    {
        $this->authorizationService->authorize($request->user(), 'users.manage');

        $user->is_active = false;
        $user->save();

        $this->auditService->record($request->user(), 'USER', $user->user_id, 'UPDATE', ['is_active' => true], ['is_active' => false]);

        return redirect()->route('users.index')->with('status', "Account '{$user->username}' deactivated.");
    }

    public function reactivate(Request $request, User $user): RedirectResponse
    {
        $this->authorizationService->authorize($request->user(), 'users.manage');

        $user->is_active = true;
        $user->save();

        $this->auditService->record($request->user(), 'USER', $user->user_id, 'UPDATE', ['is_active' => false], ['is_active' => true]);

        return redirect()->route('users.index')->with('status', "Account '{$user->username}' reactivated.");
    }

    // FR-0.1 behavior 4, AC-0.1.3 — releases a lockout BR-31 imposed.
    // Distinct from reactivate(): a locked account is still active.
    public function unlock(Request $request, User $user): RedirectResponse
    {
        $this->authorizationService->authorize($request->user(), 'users.manage');

        $user->is_locked = false;
        $user->failed_attempt_count = 0;
        $user->save();

        $this->auditService->record($request->user(), 'USER', $user->user_id, 'UPDATE', ['is_locked' => true], ['is_locked' => false]);

        return redirect()->route('users.index')->with('status', "Account '{$user->username}' unlocked.");
    }

    // FR-0.2 behavior 4 — an Administrator may reset a password; the
    // reset is recorded in the audit log, never the password value itself.
    public function resetPassword(Request $request, User $user): RedirectResponse
    {
        $this->authorizationService->authorize($request->user(), 'users.manage');

        $data = $request->validate([
            'password' => ['required', 'string', 'min:8'],
        ]);

        $user->setPassword($data['password']);
        $user->must_change_password = true;
        $user->save();

        $this->auditService->record($request->user(), 'USER', $user->user_id, 'UPDATE', null, ['password_reset' => true]);

        return redirect()->route('users.index')->with('status', "Password reset for '{$user->username}'. They must change it at next sign-in.");
    }

    /**
     * @return Collection<int, Role>
     */
    private function activeRoles()
    {
        return Role::query()->where('is_active', true)->orderBy('role_name')->get();
    }
}
