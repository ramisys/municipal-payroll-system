<?php

namespace App\Http\Middleware;

use App\Models\SystemConfig;
use Closure;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

// FR-0.1 behavior 5, AC-0.1.4, BR-32 — "the system terminates an idle
// session after a configurable timeout ... and requires re-authentication."
//
// Deterministic per-request idle check against SESTEM_CONFIG's
// SESSION_TIMEOUT_MINUTES, rather than relying solely on the session
// driver's own garbage collection (which is lottery-based and not a
// reliable "expired within the configured timeout" guarantee — see
// AppServiceProvider, which also lowers config('session.lifetime') to the
// same value as a storage-level backstop).
class EnsureSessionIsNotIdle
{
    public function handle(Request $request, Closure $next)
    {
        if (Auth::check()) {
            $timeoutMinutes = SystemConfig::value('SESSION_TIMEOUT_MINUTES', 30);
            $lastActivity = $request->session()->get('last_activity_at');

            if ($lastActivity !== null && now()->diffInMinutes($lastActivity) >= $timeoutMinutes) {
                Auth::logout();
                $request->session()->invalidate();
                $request->session()->regenerateToken();

                throw new AuthenticationException('Session expired after '.$timeoutMinutes.' idle minutes.');
            }

            $request->session()->put('last_activity_at', now());
        }

        return $next($request);
    }
}
