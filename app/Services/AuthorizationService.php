<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;

// system-architecture.md §7.2, Figure 6 / FR-6.2, BR-28, BR-29.
//
// Consulted explicitly at every entry point (a direct call from a
// controller/service), not framework Gate/Policy sugar — matching the
// architecture's call graph. The permission matrix itself lives in
// ROLE.permissions (see RoleSeeder's key list), not in a hardcoded
// switch, so it is data maintenance rather than a code change (C-01).
//
// AC-6.2.2 requires a function absent from a role's permissions to be
// refused if invoked directly, not merely hidden — that is what calling
// this from the controller/service layer (rather than only hiding a menu
// item) buys.
//
// Refusals are not themselves written to AUDIT_LOG this week: the
// action CHECK constraint on audit_logs (CREATE, UPDATE, DELETE, IMPORT,
// EXPORT, APPROVE, FINALIZE, REVERSE, LOGIN) has no value for a denial,
// and inventing one would mean writing a row the schema's own constraint
// doesn't sanction. A throw/403 is the enforcement; auditing a refusal is
// a schema decision for whoever owns the CHECK, not an application
// workaround.
class AuthorizationService
{
    public function authorize(User $user, string $permission): void
    {
        if (! $this->can($user, $permission)) {
            throw new AuthorizationException("Refused: role does not grant '{$permission}'.");
        }
    }

    public function can(User $user, string $permission): bool
    {
        if (! $user->is_active || $user->is_locked) {
            return false;
        }

        $role = $user->role;

        return $role !== null && $role->is_active && $role->hasPermission($permission);
    }
}
