<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

// data-model.md §5.2 — the business rules that are real, per-row invariants
// but reach across rows or across tables, so a plain CHECK constraint cannot
// express them. MySQL has no deferred/cross-row CHECK, so each is realized
// as a trigger. This is the "control" layer data-model.md §10 point 2 tells
// a panel to present alongside the schema.
//
// Covered here: non-overlapping statutory schedule effectivity (BR-14),
// non-overlapping compensation profile effectivity (BR-08), no loan
// over-deduction (BR-23), the two BR-37 PAYROLL_LINE sum checks, and the
// append-only / restricted-update guarantees on AUDIT_LOG (BR-27),
// PAYROLL_IMPORT (BR-39), INTEGRITY_ANCHOR (BR-36), and
// INTEGRITY_VERIFICATION.
//
// Not covered, and left to the application services named: PAYROLL_PERIOD
// and IMPORT_COLUMN_MAP overlap (BR-34, BR-41 — Sprint 2), LEAVE_APPLICATION
// overlap (Sprint 4), STATUTORY_BRACKET contiguity (Sprint 8), and
// AUDIT_LOG.prev_entry_hash chaining (BR-35 — checked by AuditService at
// write time; a trigger cannot see the "previous row" of an unordered table
// any more reliably than the application already does).
return new class extends Migration
{
    public function up(): void
    {
        // BR-14 — non-overlapping statutory schedule effectivity per agency.
        DB::unprepared(<<<'SQL'
            CREATE TRIGGER trg_statutory_schedules_no_overlap_ins
            BEFORE INSERT ON statutory_schedules
            FOR EACH ROW
            BEGIN
                IF EXISTS (
                    SELECT 1 FROM statutory_schedules
                    WHERE agency = NEW.agency
                      AND effective_from <= COALESCE(NEW.effective_to, '9999-12-31')
                      AND COALESCE(effective_to, '9999-12-31') >= NEW.effective_from
                ) THEN
                    SIGNAL SQLSTATE '45000'
                        SET MESSAGE_TEXT = 'BR-14: overlapping effective range for this agency';
                END IF;
            END
        SQL);

        DB::unprepared(<<<'SQL'
            CREATE TRIGGER trg_statutory_schedules_no_overlap_upd
            BEFORE UPDATE ON statutory_schedules
            FOR EACH ROW
            BEGIN
                IF EXISTS (
                    SELECT 1 FROM statutory_schedules
                    WHERE agency = NEW.agency
                      AND statutory_schedule_id <> NEW.statutory_schedule_id
                      AND effective_from <= COALESCE(NEW.effective_to, '9999-12-31')
                      AND COALESCE(effective_to, '9999-12-31') >= NEW.effective_from
                ) THEN
                    SIGNAL SQLSTATE '45000'
                        SET MESSAGE_TEXT = 'BR-14: overlapping effective range for this agency';
                END IF;
            END
        SQL);

        // BR-08 — non-overlapping compensation profile effectivity per employee.
        DB::unprepared(<<<'SQL'
            CREATE TRIGGER trg_compensation_profiles_no_overlap_ins
            BEFORE INSERT ON compensation_profiles
            FOR EACH ROW
            BEGIN
                IF EXISTS (
                    SELECT 1 FROM compensation_profiles
                    WHERE employee_id = NEW.employee_id
                      AND effective_from <= COALESCE(NEW.effective_to, '9999-12-31')
                      AND COALESCE(effective_to, '9999-12-31') >= NEW.effective_from
                ) THEN
                    SIGNAL SQLSTATE '45000'
                        SET MESSAGE_TEXT = 'BR-08: overlapping effective range for this employee';
                END IF;
            END
        SQL);

        DB::unprepared(<<<'SQL'
            CREATE TRIGGER trg_compensation_profiles_no_overlap_upd
            BEFORE UPDATE ON compensation_profiles
            FOR EACH ROW
            BEGIN
                IF EXISTS (
                    SELECT 1 FROM compensation_profiles
                    WHERE employee_id = NEW.employee_id
                      AND compensation_profile_id <> NEW.compensation_profile_id
                      AND effective_from <= COALESCE(NEW.effective_to, '9999-12-31')
                      AND COALESCE(effective_to, '9999-12-31') >= NEW.effective_from
                ) THEN
                    SIGNAL SQLSTATE '45000'
                        SET MESSAGE_TEXT = 'BR-08: overlapping effective range for this employee';
                END IF;
            END
        SQL);

        // BR-23 — no loan over-deduction: amount_deducted may not exceed the
        // account's current outstanding balance at the moment it is deducted.
        DB::unprepared(<<<'SQL'
            CREATE TRIGGER trg_loan_amortizations_no_overdeduction
            BEFORE INSERT ON loan_amortizations
            FOR EACH ROW
            BEGIN
                DECLARE current_balance DECIMAL(13,2);
                SELECT outstanding_balance INTO current_balance
                    FROM loan_accounts WHERE loan_account_id = NEW.loan_account_id;
                IF NEW.amount_deducted > current_balance THEN
                    SIGNAL SQLSTATE '45000'
                        SET MESSAGE_TEXT = 'BR-23: amount_deducted exceeds the loan account outstanding_balance';
                END IF;
            END
        SQL);

        // BR-37 — PAYROLL_LINE.gross_pay must equal SUM(EARNING_LINE.amount)
        // and PAYROLL_LINE.total_deductions must equal SUM(DEDUCTION_LINE.amount)
        // for that line, at the moment either total is written. A freshly
        // inserted line has no children yet, so it must be inserted at zero;
        // the importer sets real totals in one UPDATE after every child row
        // exists (see 2025_08_31_000033_create_payroll_lines_table).
        DB::unprepared(<<<'SQL'
            CREATE TRIGGER trg_payroll_lines_reconcile_ins
            BEFORE INSERT ON payroll_lines
            FOR EACH ROW
            BEGIN
                IF NEW.gross_pay <> 0 OR NEW.total_deductions <> 0 THEN
                    SIGNAL SQLSTATE '45000'
                        SET MESSAGE_TEXT = 'BR-37: a payroll line must be inserted with zero totals; set totals by UPDATE after its earning/deduction lines exist';
                END IF;
            END
        SQL);

        DB::unprepared(<<<'SQL'
            CREATE TRIGGER trg_payroll_lines_reconcile_upd
            BEFORE UPDATE ON payroll_lines
            FOR EACH ROW
            BEGIN
                DECLARE earning_sum DECIMAL(13,2);
                DECLARE deduction_sum DECIMAL(13,2);
                IF NEW.gross_pay <> OLD.gross_pay OR NEW.total_deductions <> OLD.total_deductions THEN
                    SELECT COALESCE(SUM(amount), 0) INTO earning_sum
                        FROM earning_lines WHERE payroll_line_id = NEW.payroll_line_id;
                    SELECT COALESCE(SUM(amount), 0) INTO deduction_sum
                        FROM deduction_lines WHERE payroll_line_id = NEW.payroll_line_id;
                    IF NEW.gross_pay <> earning_sum THEN
                        SIGNAL SQLSTATE '45000'
                            SET MESSAGE_TEXT = 'BR-37: gross_pay does not equal the sum of this line''s earning_lines';
                    END IF;
                    IF NEW.total_deductions <> deduction_sum THEN
                        SIGNAL SQLSTATE '45000'
                            SET MESSAGE_TEXT = 'BR-37: total_deductions does not equal the sum of this line''s deduction_lines';
                    END IF;
                END IF;
            END
        SQL);

        // BR-27 — AUDIT_LOG is append-only.
        DB::unprepared(<<<'SQL'
            CREATE TRIGGER trg_audit_logs_no_update
            BEFORE UPDATE ON audit_logs
            FOR EACH ROW
            BEGIN
                SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'BR-27: audit_logs is append-only, no UPDATE permitted';
            END
        SQL);

        DB::unprepared(<<<'SQL'
            CREATE TRIGGER trg_audit_logs_no_delete
            BEFORE DELETE ON audit_logs
            FOR EACH ROW
            BEGIN
                SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'BR-27: audit_logs is append-only, no DELETE permitted';
            END
        SQL);

        // BR-39 — a superseded PAYROLL_IMPORT is retained, never rewritten:
        // only is_current (plus bookkeeping columns) may change; no DELETE.
        DB::unprepared(<<<'SQL'
            CREATE TRIGGER trg_payroll_imports_restricted_update
            BEFORE UPDATE ON payroll_imports
            FOR EACH ROW
            BEGIN
                IF NEW.payroll_run_id <> OLD.payroll_run_id
                   OR NEW.import_column_map_id <> OLD.import_column_map_id
                   OR NEW.version_no <> OLD.version_no
                   OR NEW.source_filename <> OLD.source_filename
                   OR NEW.source_sha256 <> OLD.source_sha256
                   OR NEW.imported_by <> OLD.imported_by
                   OR NEW.imported_at <> OLD.imported_at
                   OR NEW.row_count <> OLD.row_count
                   OR NEW.control_total_gross <> OLD.control_total_gross
                   OR NEW.control_total_deductions <> OLD.control_total_deductions
                   OR NEW.control_total_net <> OLD.control_total_net
                THEN
                    SIGNAL SQLSTATE '45000'
                        SET MESSAGE_TEXT = 'BR-39: only is_current may be updated on a payroll_import row';
                END IF;
            END
        SQL);

        DB::unprepared(<<<'SQL'
            CREATE TRIGGER trg_payroll_imports_no_delete
            BEFORE DELETE ON payroll_imports
            FOR EACH ROW
            BEGIN
                SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'BR-39: payroll_imports rows are never deleted';
            END
        SQL);

        // BR-36 — an anchored hash is never rewritten: only anchor_status,
        // ledger_tx_ref, ledger_block_ref, confirmed_at, retry_count may change.
        DB::unprepared(<<<'SQL'
            CREATE TRIGGER trg_integrity_anchors_restricted_update
            BEFORE UPDATE ON integrity_anchors
            FOR EACH ROW
            BEGIN
                IF NEW.scope_type <> OLD.scope_type
                   OR NEW.payload_hash <> OLD.payload_hash
                   OR NEW.hash_algorithm <> OLD.hash_algorithm
                   OR NEW.chain_position <> OLD.chain_position
                   OR NEW.queued_at <> OLD.queued_at
                   OR NOT (NEW.payroll_run_id <=> OLD.payroll_run_id)
                   OR NOT (NEW.reversal_record_id <=> OLD.reversal_record_id)
                   OR NOT (NEW.audit_log_from <=> OLD.audit_log_from)
                   OR NOT (NEW.audit_log_to <=> OLD.audit_log_to)
                THEN
                    SIGNAL SQLSTATE '45000'
                        SET MESSAGE_TEXT = 'BR-36: an anchored hash is never rewritten';
                END IF;
            END
        SQL);

        DB::unprepared(<<<'SQL'
            CREATE TRIGGER trg_integrity_anchors_no_delete
            BEFORE DELETE ON integrity_anchors
            FOR EACH ROW
            BEGIN
                SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'BR-36: integrity_anchors rows are never deleted';
            END
        SQL);

        // INTEGRITY_VERIFICATION — append-only verification history.
        DB::unprepared(<<<'SQL'
            CREATE TRIGGER trg_integrity_verifications_no_update
            BEFORE UPDATE ON integrity_verifications
            FOR EACH ROW
            BEGIN
                SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'integrity_verifications is append-only, no UPDATE permitted';
            END
        SQL);

        DB::unprepared(<<<'SQL'
            CREATE TRIGGER trg_integrity_verifications_no_delete
            BEFORE DELETE ON integrity_verifications
            FOR EACH ROW
            BEGIN
                SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'integrity_verifications is append-only, no DELETE permitted';
            END
        SQL);
    }

    public function down(): void
    {
        $triggers = [
            'trg_statutory_schedules_no_overlap_ins',
            'trg_statutory_schedules_no_overlap_upd',
            'trg_compensation_profiles_no_overlap_ins',
            'trg_compensation_profiles_no_overlap_upd',
            'trg_loan_amortizations_no_overdeduction',
            'trg_payroll_lines_reconcile_ins',
            'trg_payroll_lines_reconcile_upd',
            'trg_audit_logs_no_update',
            'trg_audit_logs_no_delete',
            'trg_payroll_imports_restricted_update',
            'trg_payroll_imports_no_delete',
            'trg_integrity_anchors_restricted_update',
            'trg_integrity_anchors_no_delete',
            'trg_integrity_verifications_no_update',
            'trg_integrity_verifications_no_delete',
        ];

        foreach ($triggers as $trigger) {
            DB::unprepared("DROP TRIGGER IF EXISTS {$trigger}");
        }
    }
};
