<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\User;
use App\Services\AuditService;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * pre-oral-demonstration-plan.md §6 W2 milestone: "A signed-in action
 * appears in AUDIT_LOG with a valid prev_entry_hash." This test proves
 * the chain mechanics directly (BR-35, AC-6.1.5); SignInTest proves the
 * same chain is what a real sign-in produces.
 */
class AuditServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);
    }

    public function test_the_first_entry_in_an_empty_table_has_a_null_prev_entry_hash(): void
    {
        $user = User::factory()->create();

        $log = app(AuditService::class)->record(
            user: $user,
            entityName: 'USER',
            entityId: $user->user_id,
            action: 'LOGIN',
        );

        self::assertNull($log->prev_entry_hash);
        self::assertMatchesRegularExpression('/^[0-9a-f]{64}$/', $log->entry_hash);
    }

    public function test_each_entrys_prev_entry_hash_equals_the_entry_hash_of_the_row_before_it(): void
    {
        $user = User::factory()->create();
        $service = app(AuditService::class);

        $first = $service->record($user, 'USER', $user->user_id, 'LOGIN');
        $second = $service->record($user, 'USER', $user->user_id, 'UPDATE', ['is_active' => true], ['is_active' => false]);
        $third = $service->record($user, 'USER', $user->user_id, 'UPDATE', ['is_active' => false], ['is_active' => true]);

        self::assertNull($first->prev_entry_hash);
        self::assertSame($first->entry_hash, $second->prev_entry_hash);
        self::assertSame($second->entry_hash, $third->prev_entry_hash);

        // AC-6.1.5 — the chain verifies unbroken from the first entry.
        $rows = AuditLog::query()->orderBy('audit_log_id')->get();
        $previousHash = null;

        foreach ($rows as $row) {
            self::assertSame($previousHash, $row->prev_entry_hash);
            $previousHash = $row->entry_hash;
        }
    }

    public function test_a_stored_entrys_hash_recomputes_identically_from_its_own_content(): void
    {
        $user = User::factory()->create();

        $log = app(AuditService::class)->record(
            user: $user,
            entityName: 'USER',
            entityId: $user->user_id,
            action: 'LOGIN',
        );

        $recomputed = app(AuditService::class)->computeHash(
            userId: $log->user_id,
            occurredAt: $log->occurred_at->format('Y-m-d H:i:s'),
            entityName: $log->entity_name,
            entityId: $log->entity_id,
            action: $log->action,
            previousValuesJson: $log->previous_values,
            newValuesJson: $log->new_values,
            prevEntryHash: $log->prev_entry_hash,
        );

        self::assertSame($log->entry_hash, $recomputed);
    }

    public function test_altering_a_stored_row_after_the_fact_breaks_verification(): void
    {
        // BR-35's whole point: a change made outside the application
        // (direct SQL, restored backup) is detectable because the stored
        // entry_hash no longer matches the row's content.
        $user = User::factory()->create();

        $log = app(AuditService::class)->record($user, 'USER', $user->user_id, 'LOGIN');

        $tamperedRecompute = app(AuditService::class)->computeHash(
            userId: $log->user_id,
            occurredAt: $log->occurred_at->format('Y-m-d H:i:s'),
            entityName: 'TAMPERED_ENTITY_NAME',
            entityId: $log->entity_id,
            action: $log->action,
            previousValuesJson: $log->previous_values,
            newValuesJson: $log->new_values,
            prevEntryHash: $log->prev_entry_hash,
        );

        self::assertNotSame($log->entry_hash, $tamperedRecompute);
    }
}
