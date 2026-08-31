<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\AuditService;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * UC-06 · Review audit log — FR-6.1 behavior 3 (browse and filter by user,
 * date range, record type, and action) and the FR-6.2 matrix row "View
 * audit log" (Approver, Administrator, Viewer; not Payroll Officer).
 */
class AuditLogViewerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);
    }

    public function test_an_approver_can_view_the_audit_log(): void
    {
        $approver = User::factory()->forRole('APPROVER')->create();

        $this->actingAs($approver)->get('/audit-log')->assertOk();
    }

    public function test_a_payroll_officer_cannot_view_the_audit_log(): void
    {
        $officer = User::factory()->forRole('PAYROLL_OFFICER')->create();

        $this->actingAs($officer)->get('/audit-log')->assertForbidden();
    }

    public function test_filtering_by_action_returns_only_matching_entries(): void
    {
        $admin = User::factory()->forRole('ADMINISTRATOR')->create();
        $target = User::factory()->create();

        $auditService = app(AuditService::class);
        $created = $auditService->record($admin, 'USER', $target->user_id, 'CREATE');
        $updated = $auditService->record($admin, 'USER', $target->user_id, 'UPDATE', ['is_active' => true], ['is_active' => false]);

        $response = $this->actingAs($admin)->get('/audit-log?action=CREATE');

        $response->assertOk();
        $response->assertSee(substr($created->entry_hash, 0, 12));
        $response->assertDontSee(substr($updated->entry_hash, 0, 12));
    }

    public function test_verifying_an_intact_chain_reports_it_as_intact(): void
    {
        $admin = User::factory()->forRole('ADMINISTRATOR')->create();
        app(AuditService::class)->record($admin, 'USER', $admin->user_id, 'LOGIN');

        $response = $this->actingAs($admin)->get('/audit-log?verify=1');

        $response->assertOk();
        $response->assertSee('Chain verified intact');
    }

    public function test_verifying_a_chain_broken_by_a_forged_row_reports_where_it_broke(): void
    {
        // BR-27's append-only triggers block UPDATE and DELETE on
        // audit_logs even from raw SQL — trg_audit_logs_no_update /
        // trg_audit_logs_no_delete, migration 000041 — so the threat this
        // test simulates is the one channel those triggers don't close: a
        // row INSERTed directly, outside AuditService, that never went
        // through the hash chain at all.
        $admin = User::factory()->forRole('ADMINISTRATOR')->create();
        $log = app(AuditService::class)->record($admin, 'USER', $admin->user_id, 'LOGIN');

        DB::table('audit_logs')->insert([
            'user_id' => $admin->user_id,
            'occurred_at' => now(),
            'entity_name' => 'USER',
            'entity_id' => $admin->user_id,
            'action' => 'LOGIN',
            'entry_hash' => str_repeat('a', 64),
            'prev_entry_hash' => str_repeat('f', 64), // does not match $log->entry_hash
            'created_at' => now(),
        ]);

        $response = $this->actingAs($admin)->get('/audit-log?verify=1');

        $response->assertOk();
        $response->assertSee('Chain broken');
    }
}
