<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

// pre-oral-demonstration-plan.md §6 Table 6, W6 milestone P-B: "30
// employees exist with complete compensation profiles — the NFR-2.12
// population." Also the "Employee list — 30 employees" of the
// demonstration script's beat 3 (§7).
//
// Uses DB::table(), not the Eloquent models or EmployeeController/
// CompensationProfileService, per this repo's seeder convention (seeders
// stay decoupled from the application layer those weeks build). Each of
// the 30 rows gets a complete EMPLOYMENT_DETAIL, WORK_SCHEDULE, and
// COMPENSATION_PROFILE — the same three tables EmployeeController and
// CompensationProfileService open for a real registration — so the
// population is a realistic fixture for the NFR-2.12 fidelity run, not a
// bare employee list.
class EmployeeDemoSeeder extends Seeder
{
    public function run(): void
    {
        $departmentIds = DB::table('departments')->orderBy('department_id')->pluck('department_id')->all();
        $positionIds = DB::table('positions')->orderBy('position_id')->pluck('position_id')->all();
        $statusIds = DB::table('employment_statuses')->orderBy('employment_status_id')->pluck('employment_status_id')->all();

        $now = now();
        $employees = $this->employees();

        foreach ($employees as $i => $employee) {
            $departmentId = $departmentIds[$i % count($departmentIds)];
            $positionId = $positionIds[$i % count($positionIds)];
            $statusId = $statusIds[$i % count($statusIds)];
            $dateHired = sprintf('20%02d-%02d-01', 18 + ($i % 6), 1 + ($i % 12));
            $payBasis = $statusId === $statusIds[2] ? 'DAILY' : 'MONTHLY'; // JOB ORDER paid daily
            $basicRate = $payBasis === 'DAILY' ? (string) (600 + ($i * 5)) : (string) (22000 + ($i * 350));

            $employeeId = DB::table('employees')->insertGetId([
                'employee_no' => sprintf('E-%04d', 1000 + $i),
                'last_name' => $employee['last'],
                'first_name' => $employee['first'],
                'middle_name' => $employee['middle'],
                'birth_date' => sprintf('19%02d-%02d-%02d', 65 + ($i % 30), 1 + ($i % 12), 1 + ($i % 27)),
                'sex' => $employee['sex'],
                'civil_status' => ['SINGLE', 'MARRIED', 'WIDOWED', 'SEPARATED'][$i % 4],
                'contact_no' => null,
                'address' => null,
                'sss_no' => sprintf('%02d-%07d-%d', 10 + ($i % 89), 1000000 + $i, $i % 10),
                'philhealth_no' => sprintf('%02d-%09d-%d', 10 + ($i % 89), 100000000 + $i, $i % 10),
                'pagibig_mid' => sprintf('%04d-%04d-%04d', 1000 + $i, 2000 + $i, 3000 + $i),
                'tin' => sprintf('%03d-%03d-%03d', 100 + $i, 200 + $i, 300 + $i),
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            DB::table('employment_details')->insert([
                'employee_id' => $employeeId,
                'department_id' => $departmentId,
                'position_id' => $positionId,
                'employment_status_id' => $statusId,
                'date_hired' => $dateHired,
                'effective_from' => $dateHired,
                'effective_to' => null,
                'separation_date' => null,
                'separation_reason' => null,
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            DB::table('work_schedules')->insert([
                'employee_id' => $employeeId,
                'standard_hours_per_day' => '8.00',
                'rest_days' => 'SAT,SUN',
                'scheduled_time_in' => '08:00:00',
                'scheduled_time_out' => '17:00:00',
                'unpaid_break_hours' => '1.00',
                'effective_from' => $dateHired,
                'effective_to' => null,
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            DB::table('compensation_profiles')->insert([
                'employee_id' => $employeeId,
                'pay_basis' => $payBasis,
                'basic_rate' => $basicRate,
                'sss_covered' => true,
                'philhealth_covered' => true,
                'pagibig_covered' => true,
                'effective_from' => $dateHired,
                'effective_to' => null,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    /**
     * 30 names — enough distinct pairs that none repeats, no significance
     * beyond a plausible municipal-employee demo population.
     *
     * @return array<int, array{last: string, first: string, middle: string, sex: string}>
     */
    private function employees(): array
    {
        $lastNames = [
            'Santos', 'Reyes', 'Cruz', 'Bautista', 'Ocampo', 'Garcia', 'Mendoza', 'Torres',
            'Flores', 'Rivera', 'Villanueva', 'Castillo', 'Aquino', 'Del Rosario', 'Navarro',
            'Ramos', 'Salazar', 'Gonzales', 'Domingo', 'Pascual', 'Fernandez', 'Alonzo',
            'Mercado', 'Espino', 'Lazaro', 'Manalo', 'Concepcion', 'Guevarra', 'Tolentino', 'Ignacio',
        ];
        $firstNamesMale = ['Juan', 'Jose', 'Ramon', 'Antonio', 'Ricardo', 'Ernesto', 'Marlon', 'Danilo', 'Alfredo', 'Rodel', 'Noel', 'Efren', 'Bienvenido', 'Rogelio', 'Arnel'];
        $firstNamesFemale = ['Maria', 'Ana', 'Teresita', 'Corazon', 'Luz', 'Josefina', 'Remedios', 'Gloria', 'Norma', 'Perla', 'Divina', 'Rosario', 'Leonora', 'Marites', 'Imelda'];
        $middleNames = ['Santos', 'Cruz', 'Reyes', 'Garcia', 'Torres', 'Ramos'];

        $employees = [];
        for ($i = 0; $i < 30; $i++) {
            $sex = $i % 2 === 0 ? 'M' : 'F';
            $firstPool = $sex === 'M' ? $firstNamesMale : $firstNamesFemale;

            $employees[] = [
                'last' => $lastNames[$i],
                'first' => $firstPool[$i % count($firstPool)],
                'middle' => $middleNames[$i % count($middleNames)],
                'sex' => $sex,
            ];
        }

        return $employees;
    }
}
