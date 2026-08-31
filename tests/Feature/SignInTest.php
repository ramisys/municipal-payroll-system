<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\SystemConfig;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Database\Seeders\SystemConfigSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * UC-01 · Sign in — FR-0.1, BR-30, BR-31, BR-32. Also the W2 milestone
 * end-to-end: "a signed-in action appears in AUDIT_LOG with a valid
 * prev_entry_hash."
 */
class SignInTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([RoleSeeder::class, SystemConfigSeeder::class]);
    }

    private function makeUser(string $password = 'CorrectHorse!1', bool $mustChangePassword = false): User
    {
        $user = User::factory()->create(['must_change_password' => $mustChangePassword]);
        $user->setPassword($password);
        $user->save();

        return $user;
    }

    public function test_valid_credentials_sign_in_and_write_a_login_audit_entry_with_a_valid_prev_entry_hash(): void
    {
        $user = $this->makeUser();

        $response = $this->post('/login', [
            'username' => $user->username,
            'password' => 'CorrectHorse!1',
        ]);

        $this->assertAuthenticatedAs($user->fresh());
        $response->assertRedirect(route('dashboard'));

        $log = AuditLog::query()->where('user_id', $user->user_id)->where('action', 'LOGIN')->first();

        self::assertNotNull($log, 'Expected a LOGIN audit entry to exist after a successful sign-in.');
        self::assertMatchesRegularExpression('/^[0-9a-f]{64}$/', $log->entry_hash);
        // This is the first row written in the test's transaction-scoped
        // table, so prev_entry_hash is validly null (BR-35: null only for
        // the first entry) — the milestone is that the column holds a
        // hash-shaped value consistent with the chain, not that it is
        // non-null in every possible history.
        self::assertTrue($log->prev_entry_hash === null || preg_match('/^[0-9a-f]{64}$/', $log->prev_entry_hash) === 1);
    }

    public function test_a_second_signed_in_action_chains_to_the_first(): void
    {
        $user = $this->makeUser();

        $this->post('/login', ['username' => $user->username, 'password' => 'CorrectHorse!1']);
        $this->post('/logout');
        $this->post('/login', ['username' => $user->username, 'password' => 'CorrectHorse!1']);

        $logs = AuditLog::query()->orderBy('audit_log_id')->get();

        self::assertGreaterThanOrEqual(2, $logs->count());
        self::assertNull($logs->first()->prev_entry_hash);
        self::assertSame($logs->first()->entry_hash, $logs->get(1)->prev_entry_hash);
    }

    public function test_invalid_password_denies_access_without_revealing_which_field_was_wrong(): void
    {
        $user = $this->makeUser();

        $response = $this->post('/login', [
            'username' => $user->username,
            'password' => 'wrong-password',
        ]);

        $this->assertGuest();
        $response->assertSessionHasErrors('username');

        $user->refresh();
        self::assertSame(1, $user->failed_attempt_count);
    }

    public function test_unknown_username_and_wrong_password_produce_the_identical_message(): void
    {
        // AC step 3 / E1 — "a message that does not disclose which of the
        // two values was wrong" is tested here as: the two failure causes
        // (no such account vs. wrong password for a real one) must not be
        // distinguishable from the response.
        $user = $this->makeUser();

        $this->post('/login', ['username' => 'no-such-user', 'password' => 'whatever'])
            ->assertSessionHasErrors('username');
        $unknownUserMessage = session('errors')->first('username');

        $this->post('/login', ['username' => $user->username, 'password' => 'wrong-password'])
            ->assertSessionHasErrors('username');
        $wrongPasswordMessage = session('errors')->first('username');

        $this->assertGuest();
        self::assertSame($unknownUserMessage, $wrongPasswordMessage);
    }

    public function test_reaching_the_failed_login_limit_locks_the_account(): void
    {
        $user = $this->makeUser();
        $limit = (int) SystemConfig::value('FAILED_LOGIN_LIMIT');

        for ($i = 0; $i < $limit; $i++) {
            $this->post('/login', ['username' => $user->username, 'password' => 'wrong-password']);
        }

        $user->refresh();
        self::assertTrue($user->is_locked);
        self::assertSame($limit, $user->failed_attempt_count);
    }

    public function test_a_locked_account_is_refused_even_with_the_correct_password(): void
    {
        $user = $this->makeUser();
        $user->is_locked = true;
        $user->save();

        $response = $this->post('/login', [
            'username' => $user->username,
            'password' => 'CorrectHorse!1',
        ]);

        $this->assertGuest();
        $response->assertSessionHasErrors('username');
        self::assertStringContainsString('locked', strtolower(session('errors')->first('username')));
    }

    public function test_a_deactivated_account_is_refused(): void
    {
        $user = $this->makeUser();
        $user->is_active = false;
        $user->save();

        $response = $this->post('/login', [
            'username' => $user->username,
            'password' => 'CorrectHorse!1',
        ]);

        $this->assertGuest();
        $response->assertSessionHasErrors('username');
    }

    public function test_first_sign_in_with_must_change_password_is_redirected_to_the_change_password_screen(): void
    {
        $user = $this->makeUser(mustChangePassword: true);

        $this->post('/login', ['username' => $user->username, 'password' => 'CorrectHorse!1']);

        $response = $this->get('/dashboard');

        $response->assertRedirect(route('password.change'));
    }

    public function test_changing_the_password_clears_the_flag_and_reaches_the_dashboard(): void
    {
        $user = $this->makeUser(mustChangePassword: true);
        $this->post('/login', ['username' => $user->username, 'password' => 'CorrectHorse!1']);

        $response = $this->post('/password/change', [
            'current_password' => 'CorrectHorse!1',
            'password' => 'BrandNewPassword!2',
            'password_confirmation' => 'BrandNewPassword!2',
        ]);

        $response->assertRedirect(route('dashboard'));

        $user->refresh();
        self::assertFalse($user->must_change_password);
        self::assertTrue($user->verifyPassword('BrandNewPassword!2'));
    }
}
