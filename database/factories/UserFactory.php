<?php

namespace Database\Factories;

use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $salt = Str::random(32);

        return [
            'role_id' => Role::query()->where('role_name', 'VIEWER')->value('role_id'),
            'employee_id' => null,
            'username' => 'user_'.Str::random(10),
            'password_salt' => $salt,
            'password_hash' => Hash::make($salt.'password'),
            'must_change_password' => false,
            'failed_attempt_count' => 0,
            'is_locked' => false,
            'is_active' => true,
        ];
    }

    public function forRole(string $roleName): static
    {
        return $this->state(fn (array $attributes) => [
            'role_id' => Role::query()->where('role_name', $roleName)->value('role_id'),
        ]);
    }

    public function locked(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_locked' => true,
        ]);
    }

    public function mustChangePassword(): static
    {
        return $this->state(fn (array $attributes) => [
            'must_change_password' => true,
        ]);
    }
}
