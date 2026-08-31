<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

// UC-01 A1 / FR-0.2 behavior 2, AC-0.2.4 — "an account created by an
// Administrator cannot be used for payroll work until its initial
// password has been changed." Redirects everywhere except the
// change-password screen itself until must_change_password clears.
class EnsurePasswordIsChanged
{
    public function handle(Request $request, Closure $next)
    {
        $user = Auth::user();

        if ($user && $user->must_change_password && ! $request->routeIs('password.change', 'password.update', 'logout')) {
            return redirect()->route('password.change');
        }

        return $next($request);
    }
}
