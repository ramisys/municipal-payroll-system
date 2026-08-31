<?php

namespace Database\Factories;

use App\Models\Employee;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Employee>
 */
class EmployeeFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'employee_no' => 'E-'.Str::random(8),
            'last_name' => 'Dela Cruz',
            'first_name' => 'Juan',
            'middle_name' => null,
            'birth_date' => '1990-01-01',
            'sex' => 'M',
            'civil_status' => 'SINGLE',
            'contact_no' => null,
            'address' => null,
            'sss_no' => null,
            'philhealth_no' => null,
            'pagibig_mid' => null,
            'tin' => null,
            'is_active' => true,
        ];
    }
}
