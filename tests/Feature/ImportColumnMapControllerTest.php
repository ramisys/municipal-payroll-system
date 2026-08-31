<?php

namespace Tests\Feature;

use App\Models\ImportColumnMap;
use App\Models\User;
use App\Services\ImportColumnMapService;
use Database\Seeders\ImportColumnMapSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * UC-04 mapping editor — AD-17, BR-41, C-01. Proves a renamed register
 * column is absorbed by publishing a new version, with the previous
 * version retained rather than overwritten.
 */
class ImportColumnMapControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);
        $this->seed(ImportColumnMapSeeder::class);
    }

    private function admin(): User
    {
        return User::factory()->forRole('ADMINISTRATOR')->create();
    }

    private function payload(array $overrides = []): array
    {
        $base = [
            'effective_from' => '2027-01-01',
            'employee_no' => 'Employee No.',
            'gross_pay' => 'Gross Pay',
            'total_deductions' => 'Total Deductions',
            'net_pay' => 'Net Pay',
            'earnings' => array_combine(ImportColumnMapService::EARNING_CODES, ImportColumnMapService::EARNING_CODES),
            'deductions' => array_combine(ImportColumnMapService::DEDUCTION_CODES, ImportColumnMapService::DEDUCTION_CODES),
            'employer_shares' => array_combine(ImportColumnMapService::EMPLOYER_SHARE_CODES, ImportColumnMapService::EMPLOYER_SHARE_CODES),
        ];

        return array_replace($base, $overrides);
    }

    public function test_publishing_a_new_version_retains_the_previous_one(): void
    {
        $response = $this->actingAs($this->admin())->post('/import-column-maps', $this->payload([
            'net_pay' => 'Take-Home Pay',
        ]));

        $response->assertRedirect(route('import-column-maps.index'));

        $versions = ImportColumnMap::query()->where('map_name', 'CANONICAL')->orderBy('version_no')->get();
        self::assertCount(2, $versions);

        $v1 = $versions[0];
        $v2 = $versions[1];

        self::assertSame(1, $v1->version_no);
        self::assertSame('2026-12-31', $v1->effective_to->toDateString(), 'The previous open-ended version is closed the day before the new one starts.');
        self::assertSame('Net Pay', $v1->column_bindings['net_pay'], 'The previous version is retained unchanged, not overwritten (BR-41).');

        self::assertSame(2, $v2->version_no);
        self::assertNull($v2->effective_to);
        self::assertSame('Take-Home Pay', $v2->column_bindings['net_pay']);

        // The renamed header is now what an import resolves — proving the
        // rename was absorbed as configuration (AD-17), not code.
        self::assertSame($v2->import_column_map_id, ImportColumnMap::active('CANONICAL')->import_column_map_id);
    }

    public function test_a_new_version_effective_on_or_before_the_latest_is_refused(): void
    {
        $response = $this->actingAs($this->admin())->post('/import-column-maps', $this->payload([
            'effective_from' => '2026-01-01', // ImportColumnMapSeeder's version 1 is effective from today.
        ]));

        $response->assertSessionHasErrors('effective_from');
        self::assertSame(1, ImportColumnMap::query()->where('map_name', 'CANONICAL')->count());
    }

    public function test_no_non_administrator_role_can_reach_the_mapping_editor(): void
    {
        foreach (['PAYROLL_OFFICER', 'APPROVER', 'VIEWER'] as $roleName) {
            $actor = User::factory()->forRole($roleName)->create();

            $this->actingAs($actor)->get('/import-column-maps')->assertForbidden();
            $this->actingAs($actor)->post('/import-column-maps', $this->payload())->assertForbidden();
        }
    }
}
