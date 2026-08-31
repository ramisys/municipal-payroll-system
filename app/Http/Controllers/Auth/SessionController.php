<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\SystemConfig;
use App\Models\User;
use App\Services\AuditService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

// UC-01 · Sign in — FR-0.1, BR-30, BR-31, BR-32.
//
// Deliberately not Auth::attempt(): this schema hashes with an explicit
// per-user password_salt (User::verifyPassword()), which Laravel's default
// EloquentUserProvider::validateCredentials() has no way to apply. Login is
// verified and established explicitly instead — consistent with
// AuthorizationService and AuditService also being called explicitly
// rather than through framework sugar (system-architecture.md §7.2).
class SessionController extends Controller
{
    public function create(): View|RedirectResponse
    {
        if (Auth::check()) {
            return redirect()->route('dashboard');
        }

        return view('auth.login');
    }

    public function store(Request $request, AuditService $auditService): RedirectResponse
    {
        $credentials = $request->validate([
            'username' => ['required', 'string'],
            'password' => ['required', 'string'],
        ]);

        $user = User::query()->where('username', $credentials['username'])->first();

        // E1 — invalid credentials. A message that does not disclose which
        // value was wrong (AC step 3): identical wording whether the
        // username doesn't exist or the password is wrong.
        if (! $user || ! $user->verifyPassword($credentials['password'])) {
            if ($user) {
                $user->recordFailedAttempt((int) SystemConfig::value('FAILED_LOGIN_LIMIT', 5));
            }

            return back()
                ->withInput($request->only('username'))
                ->withErrors(['username' => 'The username or password is incorrect.']);
        }

        // E2 — account locked. Correct credentials do not override the lock.
        if ($user->is_locked) {
            return back()
                ->withInput($request->only('username'))
                ->withErrors(['username' => 'This account is locked. An Administrator must release it before you can sign in.']);
        }

        // E3 — account deactivated.
        if (! $user->is_active) {
            return back()
                ->withInput($request->only('username'))
                ->withErrors(['username' => 'This account is deactivated. Contact an Administrator.']);
        }

        $user->resetFailedAttempts();

        $request->session()->regenerate();
        $request->session()->put('last_activity_at', now());

        Auth::login($user);

        $auditService->record(
            user: $user,
            entityName: 'USER',
            entityId: $user->user_id,
            action: 'LOGIN',
        );

        return redirect()->intended(route('dashboard'));
    }

    public function destroy(Request $request): RedirectResponse
    {
        Auth::logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }
}
