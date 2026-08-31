<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

// data-model.md §5.3 — ROLE.role_name is a fixed four-value enum (FR-0.2).
//
// `permissions` holds the FR-6.2 permission matrix, one JSON array of
// permission keys per role. This replaces the Sprint 0 placeholder stub —
// week 2 of pre-oral-demonstration-plan.md §6 is where AuthorizationService
// and this real matrix are due.
//
// Key list, each traced to its FRS §"Permission matrix" row. Two matrix
// rows ("Approve leave applications", "Maintain statutory tables") split
// into two keys each because the row itself grants a different verb to
// different roles (file vs approve; manage vs view-only) — everywhere else
// a matrix "read" cell is folded into the same key as a "✔" cell, because
// the underlying function is inherently a view/search/report action with
// no separate write variant in this release.
//
//   employees.manage          FR-1.1, FR-1.2  — PO, Admin
//   attendance.import         FR-1.3          — PO
//   leave.file                FR-1.4 (file)   — PO
//   leave.approve             FR-1.4 (approve)— Approver
//   payroll_run.create_import FR-2.6, FR-2.8  — PO
//   exception_report.view     FR-4.1          — PO, Approver, Admin, Viewer
//   payroll_run.submit        FR-4.4          — PO
//   payroll_run.approve_return FR-4.4         — Approver
//   payroll_run.finalize      FR-4.4, FR-4.5  — Approver
//   payslips.generate         FR-3.1, FR-3.3  — PO
//   payslips.reprint          FR-3.4          — PO, Approver, Admin, Viewer
//   payroll_records.search    FR-5.2          — PO, Approver, Admin, Viewer
//   reports.generate          FR-5.3          — PO, Approver, Admin, Viewer
//   statutory_tables.manage   FR-2.3 (manage) — Admin
//   statutory_tables.view     FR-2.3 (view)   — Admin, Viewer
//   users.manage               FR-0.2         — Admin
//   audit_log.view             FR-6.1         — Approver, Admin, Viewer
//   integrity.verify           FR-6.3         — Approver, Admin, Viewer
//   backup.run_restore         NFR-5.4        — Admin
class RoleSeeder extends Seeder
{
    public function run(): void
    {
        $roles = [
            [
                'name' => 'ADMINISTRATOR',
                'description' => 'Full system access, including user, reference-data, and integrity administration.',
                'permissions' => [
                    'employees.manage',
                    'exception_report.view',
                    'payslips.reprint',
                    'payroll_records.search',
                    'reports.generate',
                    'statutory_tables.manage',
                    'statutory_tables.view',
                    'users.manage',
                    'audit_log.view',
                    'integrity.verify',
                    'backup.run_restore',
                ],
            ],
            [
                'name' => 'PAYROLL_OFFICER',
                'description' => 'Prepares employee, attendance, and payroll data; imports registers; cannot approve.',
                'permissions' => [
                    'employees.manage',
                    'attendance.import',
                    'leave.file',
                    'payroll_run.create_import',
                    'exception_report.view',
                    'payroll_run.submit',
                    'payslips.generate',
                    'payslips.reprint',
                    'payroll_records.search',
                    'reports.generate',
                ],
            ],
            [
                'name' => 'APPROVER',
                'description' => 'Reviews and approves submitted payroll runs; cannot submit a run they approve (BR-28).',
                'permissions' => [
                    'leave.approve',
                    'exception_report.view',
                    'payroll_run.approve_return',
                    'payroll_run.finalize',
                    'payslips.reprint',
                    'payroll_records.search',
                    'reports.generate',
                    'audit_log.view',
                    'integrity.verify',
                ],
            ],
            [
                'name' => 'VIEWER',
                'description' => 'Read-only access to records and reports.',
                'permissions' => [
                    'exception_report.view',
                    'payslips.reprint',
                    'payroll_records.search',
                    'reports.generate',
                    'statutory_tables.view',
                    'audit_log.view',
                    'integrity.verify',
                ],
            ],
        ];

        $now = now();

        foreach ($roles as $role) {
            DB::table('roles')->insert([
                'role_name' => $role['name'],
                'permissions' => json_encode($role['permissions']),
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }
}
