<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

// FR-4.5 / BR-24 / AC-4.5.1 — A finalized payroll run and all its child lines
// (payroll_lines, earning_lines, deduction_lines) become immutable at the
// database level. Any UPDATE or DELETE on lines belonging to a FINALIZED run
// is rejected by MySQL triggers.
return new class extends Migration
{
    public function up(): void
    {
        DB::unprepared(<<<'SQL'
            CREATE TRIGGER trg_payroll_lines_immutable_upd
            BEFORE UPDATE ON payroll_lines
            FOR EACH ROW
            BEGIN
                DECLARE current_status VARCHAR(50);
                SELECT run_status INTO current_status
                    FROM payroll_runs WHERE payroll_run_id = OLD.payroll_run_id;
                IF current_status = 'FINALIZED' THEN
                    SIGNAL SQLSTATE '45000'
                        SET MESSAGE_TEXT = 'BR-24/FR-4.5: cannot modify a payroll line of a finalized run';
                END IF;
            END
        SQL);

        DB::unprepared(<<<'SQL'
            CREATE TRIGGER trg_payroll_lines_immutable_del
            BEFORE DELETE ON payroll_lines
            FOR EACH ROW
            BEGIN
                DECLARE current_status VARCHAR(50);
                SELECT run_status INTO current_status
                    FROM payroll_runs WHERE payroll_run_id = OLD.payroll_run_id;
                IF current_status = 'FINALIZED' THEN
                    SIGNAL SQLSTATE '45000'
                        SET MESSAGE_TEXT = 'BR-24/FR-4.5: cannot delete a payroll line of a finalized run';
                END IF;
            END
        SQL);

        DB::unprepared(<<<'SQL'
            CREATE TRIGGER trg_earning_lines_immutable_upd
            BEFORE UPDATE ON earning_lines
            FOR EACH ROW
            BEGIN
                DECLARE current_status VARCHAR(50);
                SELECT r.run_status INTO current_status
                    FROM payroll_runs r
                    JOIN payroll_lines l ON l.payroll_run_id = r.payroll_run_id
                    WHERE l.payroll_line_id = OLD.payroll_line_id;
                IF current_status = 'FINALIZED' THEN
                    SIGNAL SQLSTATE '45000'
                        SET MESSAGE_TEXT = 'BR-24/FR-4.5: cannot modify an earning line of a finalized run';
                END IF;
            END
        SQL);

        DB::unprepared(<<<'SQL'
            CREATE TRIGGER trg_earning_lines_immutable_del
            BEFORE DELETE ON earning_lines
            FOR EACH ROW
            BEGIN
                DECLARE current_status VARCHAR(50);
                SELECT r.run_status INTO current_status
                    FROM payroll_runs r
                    JOIN payroll_lines l ON l.payroll_run_id = r.payroll_run_id
                    WHERE l.payroll_line_id = OLD.payroll_line_id;
                IF current_status = 'FINALIZED' THEN
                    SIGNAL SQLSTATE '45000'
                        SET MESSAGE_TEXT = 'BR-24/FR-4.5: cannot delete an earning line of a finalized run';
                END IF;
            END
        SQL);

        DB::unprepared(<<<'SQL'
            CREATE TRIGGER trg_deduction_lines_immutable_upd
            BEFORE UPDATE ON deduction_lines
            FOR EACH ROW
            BEGIN
                DECLARE current_status VARCHAR(50);
                SELECT r.run_status INTO current_status
                    FROM payroll_runs r
                    JOIN payroll_lines l ON l.payroll_run_id = r.payroll_run_id
                    WHERE l.payroll_line_id = OLD.payroll_line_id;
                IF current_status = 'FINALIZED' THEN
                    SIGNAL SQLSTATE '45000'
                        SET MESSAGE_TEXT = 'BR-24/FR-4.5: cannot modify a deduction line of a finalized run';
                END IF;
            END
        SQL);

        DB::unprepared(<<<'SQL'
            CREATE TRIGGER trg_deduction_lines_immutable_del
            BEFORE DELETE ON deduction_lines
            FOR EACH ROW
            BEGIN
                DECLARE current_status VARCHAR(50);
                SELECT r.run_status INTO current_status
                    FROM payroll_runs r
                    JOIN payroll_lines l ON l.payroll_run_id = r.payroll_run_id
                    WHERE l.payroll_line_id = OLD.payroll_line_id;
                IF current_status = 'FINALIZED' THEN
                    SIGNAL SQLSTATE '45000'
                        SET MESSAGE_TEXT = 'BR-24/FR-4.5: cannot delete a deduction line of a finalized run';
                END IF;
            END
        SQL);
    }

    public function down(): void
    {
        $triggers = [
            'trg_payroll_lines_immutable_upd',
            'trg_payroll_lines_immutable_del',
            'trg_earning_lines_immutable_upd',
            'trg_earning_lines_immutable_del',
            'trg_deduction_lines_immutable_upd',
            'trg_deduction_lines_immutable_del',
        ];

        foreach ($triggers as $trigger) {
            DB::unprepared("DROP TRIGGER IF EXISTS {$trigger}");
        }
    }
};
