<?php

namespace Tests\Feature;

use App\Models\ImportColumnMap;
use App\Models\User;
use App\Services\IntakeFidelityHarness;
use App\Services\RegisterImportService;
use Database\Seeders\DeductionTypeSeeder;
use Database\Seeders\EarningTypeSeeder;
use Database\Seeders\ImportColumnMapSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * NFR-2.12 transcription fidelity — pre-oral-demonstration-plan.md §6
 * Table 6, W4: "the intake fidelity harness — file→database and
 * database→file." Validation set here is register_clean.xlsx's three
 * employees; the 30-employee/3-period evidence run is W6-W7 (§8), once
 * real compensation profiles exist — this test proves the harness itself
 * is correct against fixtures, per §3.3.
 */
class IntakeFidelityHarnessTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(EarningTypeSeeder::class);
        $this->seed(DeductionTypeSeeder::class);
        $this->seed(ImportColumnMapSeeder::class);
        $this->seed(RoleSeeder::class);
    }

    private function harness(): IntakeFidelityHarness
    {
        return new IntakeFidelityHarness(new RegisterImportService);
    }

    public function test_every_stored_value_agrees_with_the_source_file_to_the_centavo(): void
    {
        $map = ImportColumnMap::active('CANONICAL');
        $user = User::factory()->forRole('ADMINISTRATOR')->create();

        $stored = $this->harness()->importAndStore(base_path('tests/Fixtures/register_clean.xlsx'), $map, $user->user_id);

        self::assertCount(3, $stored['source_rows']);

        $mismatches = $this->harness()->compareFileToDatabase($stored['source_rows'], $stored['line_ids']);

        self::assertSame([], array_map(fn ($m) => $m->toArray(), $mismatches), 'file -> database must agree to the centavo (NFR-2.12).');
    }

    public function test_the_stored_run_reexports_identically_to_what_was_imported(): void
    {
        $map = ImportColumnMap::active('CANONICAL');
        $user = User::factory()->forRole('ADMINISTRATOR')->create();

        $stored = $this->harness()->importAndStore(base_path('tests/Fixtures/register_clean.xlsx'), $map, $user->user_id);

        $mismatches = $this->harness()->compareDatabaseToFile($stored['source_rows'], $stored['line_ids']);

        self::assertSame([], array_map(fn ($m) => $m->toArray(), $mismatches), 'database -> file must reexport identically to what was imported (NFR-2.12).');
    }

    /**
     * NFR-2.12's own pass condition: "a value altered in the source file
     * must produce a different stored value, proving the comparison is
     * live." Alters a copy of the already-stored, already-verified rows
     * by one centavo and confirms the harness reports it — proof the two
     * tests above pass because the figures genuinely agree, not because
     * the comparison cannot fail.
     */
    public function test_an_altered_source_value_is_detected_against_the_stored_figures(): void
    {
        $map = ImportColumnMap::active('CANONICAL');
        $user = User::factory()->forRole('ADMINISTRATOR')->create();

        $stored = $this->harness()->importAndStore(base_path('tests/Fixtures/register_clean.xlsx'), $map, $user->user_id);

        $alteredRows = $stored['source_rows'];
        $alteredRows[0]['earnings']['BASIC'] = bcadd($alteredRows[0]['earnings']['BASIC'], '0.01', 2);

        $mismatches = $this->harness()->compareFileToDatabase($alteredRows, $stored['line_ids']);

        self::assertNotSame([], $mismatches);
        self::assertSame('earnings.BASIC', $mismatches[0]->field);
        self::assertSame($alteredRows[0]['employee_no'], $mismatches[0]->employeeNo);
    }
}
