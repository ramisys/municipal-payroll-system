# System Acceptance Test Script (UAT)

**Project:** Municipal Payroll Management System  
**Version:** 1.0 (Post-Pre-Oral Phase P5 Release Candidate)  
**Evaluation Scope:** All 45 Requirement Items (32 FR, 8 NFR, 5 DR) across 4 User Roles  
**Target Operator:** Municipal IT Officer / External Auditor (Independent Operator outside Build Team)  
**Prerequisites:** Fresh database seeded via `php artisan migrate:fresh --seed`  

---

## 1. Operator Preparation & Environmental Setup

### 1.1 Test Credentials

All accounts are pre-seeded with initial password `CorrectHorse!1` (or role-specific credentials in `UserSeeder`).

| Role | Username | Permitted Scope | Forbidden Scope |
|---|---|---|---|
| **Administrator** | `admin` | User management, column mapping, schedules, backup/restore, integrity verification | Create/submit/approve payroll runs |
| **Payroll Officer** | `officer` | Employee management, attendance import, worksheet export, register intake, run submit | Approve runs, finalize runs, verify integrity, backup/restore |
| **Approver** | `approver` | Register review, exceptions review, approve run, finalize run, reverse run, integrity verify | Create runs, import files, submit runs, system administration |
| **Viewer** | `viewer` | Read-only access to employees, payroll runs, reports, and payslip reprints | Any create, update, delete, approve, or admin action |

### 1.2 Verification Environment Verification

Open PowerShell or terminal in the application root:
```bash
# 1. Clean Database Reset & Seeder Check
php artisan migrate:fresh --seed

# 2. Start Application Server
php artisan serve --port=8000
```
Navigate browser to: `http://localhost:8000`

---

## 2. Test Execution Matrix

### Test Case TC-01: Authentication, Role-Based Access & Lockout (UC-01, NFR-6.5)

| Step | Action | Expected Result | Pass / Fail |
|:---:|---|---|:---:|
| 1 | Navigate to `http://localhost:8000/login`. Enter invalid password 5 consecutive times for username `officer`. | Failed login message displayed without revealing whether username or password was incorrect. Account locked on 5th attempt (`BR-31`). | [ ] Pass |
| 2 | Attempt 6th login with correct password `CorrectHorse!1`. | Login refused with alert stating account is locked. | [ ] Pass |
| 3 | Sign in as `admin`. Navigate to **User Management** (`/users`), locate `officer`, click **Unlock Account**. | Account unlocked successfully. Audit log records `UNLOCK` action. | [ ] Pass |
| 4 | Sign in as `officer`. | Redirects to Dashboard. Top navigation displays Officer modules only. | [ ] Pass |

---

### Test Case TC-02: Employee & Compensation Master Data (UC-08, UC-10, FR-1.1, FR-1.2)

| Step | Action | Expected Result | Pass / Fail |
|:---:|---|---|:---:|
| 1 | As `officer`, navigate to **Employees** (`/employees`). | Displays 30 seeded demo municipal employees (`E-1000` to `E-1029`). | [ ] Pass |
| 2 | Open employee `E-1000`. Click **Compensation Profile**. | Displays basic rate, statutory contribution enrollments, and standing deductions. | [ ] Pass |
| 3 | Click **Add Standing Deduction** with zero amount `0.00`. | System refuses submission; validation error highlights amount must be greater than zero. | [ ] Pass |

---

### Test Case TC-03: Attendance Intake & Input Worksheet Export (UC-15, UC-16, FR-1.3, FR-2.11)

| Step | Action | Expected Result | Pass / Fail |
|:---:|---|---|:---:|
| 1 | Navigate to **Attendance** (`/attendance`). Select pay period `2026-01-01` to `2026-01-15`. | Attendance overview displayed. | [ ] Pass |
| 2 | Upload `tests/Fixtures/attendance_clean.xlsx`. Click **Import**. | 30 employee records imported; derived late and undertime minutes calculated accurately. | [ ] Pass |
| 3 | Navigate to **Payroll Runs** (`/payroll-runs`). Click **Create Run**. Select semi-monthly period and `ALL` population. | Run created in `DRAFT` status with 0 payroll lines. | [ ] Pass |
| 4 | Click **Export Payroll Input Worksheet (XLSX)**. | Spreadsheet downloads with exactly 30 rows containing employee numbers, basic rates, attendance summaries, and standing loan references (`AC-2.11.1`). | [ ] Pass |

---

### Test Case TC-04: Register Intake, Centavo Arithmetic & Reconciliation Refusal (UC-18, UC-19, FR-2.8, FR-2.9)

| Step | Action | Expected Result | Pass / Fail |
|:---:|---|---|:---:|
| 1 | In the `DRAFT` run, click **Import Computed Register**. Select `Canonical Register Mapping`. | Upload screen displays column preview. | [ ] Pass |
| 2 | Upload defective register `tests/Fixtures/register_defect_row_imbalance.xlsx` (1 centavo imbalance). | **Refusal:** Import preview immediately blocks with error: `Row arithmetic mismatch at row 2: Gross - Total Deductions does not equal Net Pay`. No lines saved (`AC-2.9.1`). | [ ] Pass |
| 3 | Upload defective register `tests/Fixtures/register_defect_omitted_employee.xlsx` (missing 1 active employee). | **Refusal:** Import preview blocks with error: `Completeness failure: Active employee E-1029 omitted from register` (`AC-2.9.5`). | [ ] Pass |
| 4 | Upload clean register `tests/Fixtures/register_clean.xlsx`. Click **Preview & Validate**. | Arithmetic reconciles to the centavo; control totals match sum of loaded rows. | [ ] Pass |
| 5 | Click **Commit Import**. | Run updates with 30 payroll lines. Current import version stamped with SHA-256 hash. | [ ] Pass |

---

### Test Case TC-05: Exception Evaluation & Targeted Corrections (UC-20, UC-21, UC-22, FR-4.1)

| Step | Action | Expected Result | Pass / Fail |
|:---:|---|---|:---:|
| 1 | Click **Exception Report** on the run details screen. | Lists all warnings (e.g., negative net pay warning, excessive overtime, or statutory discrepancies). | [ ] Pass |
| 2 | Attempt to submit run while an unacknowledged warning exists. | System refuses submission with prompt: `All warnings must be acknowledged before submitting for review`. | [ ] Pass |
| 3 | Open warning instance, enter acknowledgment reason: `Verified against accounting timesheet memo`, click **Acknowledge**. | Warning status changes to `ACKNOWLEDGED`. Acting user and timestamp recorded. | [ ] Pass |

---

### Test Case TC-06: Governed Workflow & Separation of Duties (UC-24, UC-25, FR-4.3, FR-4.4, BR-28)

| Step | Action | Expected Result | Pass / Fail |
|:---:|---|---|:---:|
| 1 | As `officer`, click **Submit for Review**. | Run state transitions to `FOR_REVIEW`. Submit button disabled. | [ ] Pass |
| 2 | While signed in as `officer`, attempt direct HTTP POST to `/payroll-runs/{id}/approve`. | **Forbidden (403):** Preparer cannot approve their own payroll run (`BR-28`, `AC-6.2.1`). | [ ] Pass |
| 3 | Sign out. Sign in as `approver`. Navigate to the run. | Approve and Return buttons visible. | [ ] Pass |
| 4 | Click **Return Run**, enter reason: `Verify department 3 allowances`. | Run transitions to `RETURNED`. Officer receives notification on dashboard. | [ ] Pass |
| 5 | As `officer`, reopen run, click **Submit for Review** again. | Run transitions back to `FOR_REVIEW`. | [ ] Pass |
| 6 | As `approver`, click **Approve Payroll Run**. | Run state transitions to `APPROVED`. | [ ] Pass |
| 7 | Click **Finalize & Lock Payroll Run** (prompts confirmation modal per `NFR-6.3`). Click Confirm. | Run transitions to `FINALIZED`. Period is locked. Lines become immutable. Integrity anchor queued in outbox (`AC-4.5.5`). | [ ] Pass |

---

### Test Case TC-07: Payslip Batch Generation & Reprinting (UC-27, UC-28, FR-3.1, NFR-3.5)

| Step | Action | Expected Result | Pass / Fail |
|:---:|---|---|:---:|
| 1 | As `officer` or `viewer`, open the finalized run. Click **Generate All Payslips (PDF Batch)**. | Single PDF batch containing all 30 employee payslips generated in < 15 seconds (well within 5-minute `NFR-3.5` limit). | [ ] Pass |
| 2 | Open payslip for `E-1000`. | Displays municipality seal, employee information, stored earnings, stored deductions, and imported net pay. | [ ] Pass |
| 3 | Click **Reprint Payslip** for `E-1000`. | Re-renders exact stored values; issuance count increments to 2; flagged as `REPRINT`. | [ ] Pass |
| 4 | Attempt payslip generation on an unfinalized (`DRAFT`) run. | **Refusal:** Action rejected with error: `Payslips may only be generated for finalized payroll runs` (`AC-3.1.5`). | [ ] Pass |

---

### Test Case TC-08: 11-Report Catalogue & Derived Statutory Schedules (UC-06, FR-5.1–5.11, NFR-5.5)

| Step | Action | Expected Result | Pass / Fail |
|:---:|---|---|:---:|
| 1 | Navigate to **Reports Catalogue** (`/reports`). | Displays all 11 statutory and municipal reports. | [ ] Pass |
| 2 | Generate **Payroll Register Report**. | Displays complete payroll breakdown. Response returns in < 2 seconds (`NFR-5.5`). | [ ] Pass |
| 3 | Generate **SSS Remittance Schedule**. | Clear labels distinguish imported employee deductions from derived municipal employer shares (`AC-2.3.4`). | [ ] Pass |
| 4 | Click **Export to Excel (XLSX)** and **Export to PDF**. | Both formats download cleanly with exact numerical agreement to stored figures. | [ ] Pass |

---

### Test Case TC-09: Integrity Layer, Outbox Processing & Tamper Evidence (UC-31, UC-I6, FR-6.3, Milestone P-E)

| Step | Action | Expected Result | Pass / Fail |
|:---:|---|---|:---:|
| 1 | Sign in as `admin`. Navigate to **Integrity Verification** (`/integrity`). | Dashboard displays outbox metrics, pending/confirmed anchors, and audit chain card. | [ ] Pass |
| 2 | Click **Process Pending Anchors**. | Artisan outbox processor runs; pending anchor transmits to ledger; status becomes `CONFIRMED` with `ledger_tx_ref`. | [ ] Pass |
| 3 | Click **Verify Integrity Now** for the finalized run. | Live SHA-256 hash recomputed from MySQL matches ledger fingerprint byte-for-byte -> Outcome: **`MATCH`** banner. | [ ] Pass |
| 4 | Click **Download Auditor Verification Certificate (PDF)**. | Formal DomPDF certificate downloads displaying cryptographic hashes, block reference, and timestamp (`UC-31 A3`). | [ ] Pass |
| 5 | **Tampering Simulation:** Execute direct database update in MySQL: `UPDATE payroll_runs SET total_gross = total_gross + 100 WHERE payroll_run_id = 1;` | Tampering executed directly in storage layer outside application. | [ ] Pass |
| 6 | Return to `/integrity/runs/1` and click **Re-verify Integrity**. | Divergence detected immediately -> Outcome: **`MISMATCH`** warning banner. Failure position logged permanently. Record is never auto-repaired (`AC-6.3.3`). | [ ] Pass |

---

### Test Case TC-10: Database Backup, Safe Restore & Post-Restore Verification (UC-07, NFR-5.4, NFR-6.3)

| Step | Action | Expected Result | Pass / Fail |
|:---:|---|---|:---:|
| 1 | As `admin`, navigate to **Backup & Restore** (`/admin/backup`). | Displays backup archive table and storage volume status. | [ ] Pass |
| 2 | Click **Create Database Backup Now**. | Logical database archive created with SHA-256 manifest in `storage/app/backups/`. | [ ] Pass |
| 3 | Click **Restore Database**, enter confirmation phrase matching the backup file name. | Confirmation validated; schema restored; post-restore table row counts verified (`NFR-6.3`). | [ ] Pass |
| 4 | Navigate to `/integrity` and trigger integrity check. | Hash recomputation on restored database confirms clean state restored (`Milestone P-D`). | [ ] Pass |

---

### Test Case TC-11: Finalized Run Reversal (UC-26, FR-4.5, Milestone P-B)

| Step | Action | Expected Result | Pass / Fail |
|:---:|---|---|:---:|
| 1 | As `approver`, navigate to finalized run. Click **Reverse Payroll Run**. | Modal prompts for mandatory reversal reason and double-confirmation (`NFR-6.3`). | [ ] Pass |
| 2 | Enter reason: `Formal audit reversal due to municipal ordinance adjustment`. Confirm. | Run transitions to `REVERSED`. Reversal record created. Period unlocked for replacement run (`AC-4.5.1`). | [ ] Pass |
| 3 | Check `/integrity`. | New `REVERSAL` scope integrity anchor queued and confirmed on ledger. | [ ] Pass |

---

## 3. Acceptance Sign-Off

I hereby certify that all 11 test cases above have been executed on the deployment candidate in accordance with the Municipal Payroll System Specification Baseline B2.

| Role | Printed Name | Signature | Date |
|---|---|---|---|
| **Lead Operator / QA** | ____________________ | ____________________ | ____ / ____ / 2026 |
| **Municipal Auditor** | ____________________ | ____________________ | ____ / ____ / 2026 |
| **Project Sponsor** | ____________________ | ____________________ | ____ / ____ / 2026 |
