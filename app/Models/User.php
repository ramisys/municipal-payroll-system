<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

// data-model.md §4.6 / §5.1 — USER. `employee_id` is nullable: an
// Administrator may be an external IT contact who is not an employee.
// USER rows are never deleted (AC-0.2.2).
//
// Password storage (BR-30, decision #4 of the W2 plan): `password_salt`
// is a per-user random value distinct from bcrypt's own internal salt.
// `password_hash` = bcrypt($password_salt . plaintext) via setPassword(),
// verified the same way in verifyPassword() — never Auth::attempt(), which
// assumes a bare `password` column and hasher input.
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory;

    protected $primaryKey = 'user_id';

    protected $fillable = [
        'role_id',
        'employee_id',
        'username',
        'password_hash',
        'password_salt',
        'must_change_password',
        'failed_attempt_count',
        'is_locked',
        'last_login_at',
        'is_active',
        'created_by',
        'updated_by',
    ];

    protected $hidden = [
        'password_hash',
        'password_salt',
    ];

    protected function casts(): array
    {
        return [
            'must_change_password' => 'boolean',
            'failed_attempt_count' => 'integer',
            'is_locked' => 'boolean',
            'is_active' => 'boolean',
            'last_login_at' => 'datetime',
        ];
    }

    public function role()
    {
        return $this->belongsTo(Role::class, 'role_id', 'role_id');
    }

    public function employee()
    {
        return $this->belongsTo(Employee::class, 'employee_id', 'employee_id');
    }

    /**
     * BR-30 — set a new salted password. Does not save(); caller persists.
     */
    public function setPassword(string $plain): void
    {
        $salt = Str::random(32);

        $this->password_salt = $salt;
        $this->password_hash = Hash::make($salt.$plain);
    }

    public function verifyPassword(string $plain): bool
    {
        return Hash::check($this->password_salt.$plain, $this->password_hash);
    }

    public function recordFailedAttempt(int $lockoutLimit): void
    {
        $this->failed_attempt_count++;

        if ($this->failed_attempt_count >= $lockoutLimit) {
            $this->is_locked = true;
        }

        $this->save();
    }

    public function resetFailedAttempts(): void
    {
        $this->failed_attempt_count = 0;
        $this->last_login_at = now();
        $this->save();
    }

    // Authenticatable overrides — this schema has no `password` or
    // `remember_token` column, so the framework defaults are replaced
    // rather than shimmed with columns that don't exist (AD-style: no
    // schema change for a framework convenience).
    public function getAuthPassword()
    {
        return $this->password_hash;
    }

    public function getAuthPasswordName()
    {
        return 'password_hash';
    }

    public function getRememberToken()
    {
        return null;
    }

    public function setRememberToken($value)
    {
        // No remember_token column — "remember me" is not offered.
    }

    public function getRememberTokenName()
    {
        return '';
    }
}
