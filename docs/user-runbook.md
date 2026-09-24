# User Operations Runbook & Operational Guide

**Project:** Municipal Payroll Management System  
**Version:** 1.0 (Phase P5 Release Candidate)  
**Target Roles:** Payroll Officer (`PAYROLL_OFFICER`), Payroll Approver (`APPROVER`), Municipal Auditor / Viewer (`VIEWER`)  
**Standard Reference:** Baseline B2 Specification and FRS §4 / §6  

---

## 1. System Navigation & Access Overview

The Municipal Payroll Management System is a governed web application designed for on-premises municipal local area networks. All system actions are strictly attributed to individual user accounts and hash-chained in the append-only audit trail (`FR-6.1`).

### 1.1 Role Access Capabilities

```
+------------------------------------+-----------------+----------+--------+
| Functional Capability              | Payroll Officer | Approver | Viewer |
+------------------------------------+-----------------+----------+--------+
| Manage Employees & Compensation    |      FULL       |  VIEW    |  VIEW  |
| Import Attendance Timesheets       |      FULL       |  VIEW    |  VIEW  |
| Export Payroll Input Worksheet     |      FULL       |  VIEW    |  VIEW  |
| Create Payroll Run & Import Reg.   |      FULL       |   --     |   --   |
| Resolve & Acknowledge Exceptions   |      FULL       |  VIEW    |  VIEW  |
| Submit Run for Review              |      FULL       |   --     |   --   |
| Return Run with Reason             |       --        |  EXEC    |   --   |
| Approve Payroll Run                |       --        |  EXEC    |   --   |
| Finalize & Lock Run                |       --        |  EXEC    |   --   |
| Generate & Export Payslip Batches  |      FULL       |  FULL    |  VIEW  |
| Generate Reports & Remittances     |      FULL       |  FULL    |  VIEW  |
| Reverse Finalized Run              |       --        |  EXEC    |   --   |
| Audit Log & Integrity Inspection   |       --        |  VIEW    |  VIEW  |
+------------------------------------+-----------------+----------+--------+
```

---

## 2. Core Operational Workflows

### 2.1 Cycle Step 1: Attendance Import & Verification
*Actor: Payroll Officer*

1. Navigate to **Attendance Management** (`/attendance`) from the main menu.
2. Select the relevant payroll cut-off period (e.g., 1st to 15th of the month).
3. Click **Import Attendance File** and select the validated timesheet spreadsheet (`.xlsx` or `.csv`).
4. Click **Preview & Validate**:
   - The system checks each row against active municipal employees.
   - Days present, hours worked, late arrival minutes, and undertime minutes are derived deterministically.
5. Click **Confirm & Save**:
   - Attendance summaries are committed to the database and locked to the cut-off period.

### 2.2 Cycle Step 2: Payroll Input Worksheet Generation
*Actor: Payroll Officer*

1. Navigate to **Payroll Runs** (`/payroll-runs`) and click **Create Run**.
2. Select the **Payroll Period**, **Run Type** (`REGULAR`, `SPECIAL`, or `THIRTEENTH_MONTH`), and **Population Scope** (`ALL` or specific department).
3. The run is initialized in **`DRAFT`** status with zero payroll figures.
4. On the run details page, click **Export Input Worksheet (XLSX)** (`FR-2.11`):
   - The system produces an official pre-populated Excel workbook containing employee names, employee numbers, compensation basic rates, attendance summaries, standing statutory contribution settings, and current loan deduction balances.
5. Transmit this generated worksheet to the municipal **Accounting Office** for independent computation.
   > **Important Boundary Rule:** The Municipal Payroll System does **not** compute gross pay, deductions, or net pay. All arithmetic computation is performed independently by the Accounting Office within their master spreadsheet.

### 2.3 Cycle Step 3: Computed Register Intake & Strict Reconciliation
*Actor: Payroll Officer*

1. Upon receiving the completed register from the Accounting Office, open the corresponding `DRAFT` payroll run.
2. Click **Import Computed Register** (`FR-2.8`):
   - Select the designated column mapping (e.g., `Canonical Register Mapping` or a custom configured mapping version).
   - Attach the Accounting Office's `.xlsx` file.
3. Click **Upload & Preview**:
   - The system performs immediate zero-tolerance centavo reconciliation (`BR-37`):
     - **Row Gross Check:** Gross Pay must exactly equal the sum of all earning columns.
     - **Row Deduction Check:** Total Deductions must exactly equal the sum of all deduction columns.
     - **Row Net Pay Check:** Net Pay must exactly equal Gross Pay minus Total Deductions to the centavo.
     - **Control Totals:** File header totals must match the loaded row sums.
     - **Population Completeness:** Every active employee in the run population must be present.
4. **Refusal Protocol:** If any row or total fails by even 0.01 centavo, the intake is refused entirely. No payroll lines are saved. The report identifies the exact row, column, and discrepancy. The Accounting Office must correct and resubmit the register.
5. Once all checks pass, click **Commit Import**:
   - The register figures are committed immutably.
   - The file is hashed with SHA-256 and stamped with an import version number (`FR-2.10`).

### 2.4 Cycle Step 4: Exception Report Review & Resolution
*Actor: Payroll Officer*

1. Open the imported payroll run and click **Exception Report** (`FR-4.1`).
2. Review any identified warnings:
   - `EX-01`: Zero or negative net pay warning.
   - `EX-02`: Net pay below municipal statutory threshold.
   - `EX-04`: Excessive overtime hours (> 50% of basic hours).
   - `EX-05`: Missing statutory schedule configuration.
3. **Resolution:**
   - **Blocking Errors:** Must be corrected either by importing a superseding register (`BR-39`) or adding a line adjustment (`FR-2.4`).
   - **Warnings:** Must be explicitly acknowledged with a written justification. Click **Acknowledge**, enter the memo reference, and confirm.

### 2.5 Cycle Step 5: Submission for Review
*Actor: Payroll Officer*

1. Once all exceptions are acknowledged and register figures verified, click **Submit for Review** (`UC-24`).
2. The run transitions from `DRAFT` to **`FOR_REVIEW`**.
3. The run figures are locked against further direct edits.
4. Automatic notification appears on the Approver's dashboard.

---

## 3. Approval & Finalization Workflows

### 3.1 Cycle Step 6: Approver Review & Decision
*Actor: Payroll Approver*

1. Sign in as `APPROVER`. Navigate to **Payroll Runs** (`/payroll-runs`).
2. Filter by status: `For Review`. Open the submitted run.
3. Review the high-level summary cards:
   - Total Gross Pay, Total Statutory Deductions, Total Non-Statutory Deductions, Total Net Pay.
   - Active Import Version SHA-256 fingerprint.
   - Exception log and Officer acknowledgment notes.
4. **Separation of Duty Rule (`BR-28`):** If the Approver was also the user who created or submitted the run, the system strictly refuses approval with a 403 Forbidden error.
5. **Decision Pathways:**
   - **Option A: Return Run (`UC-24 A1`):** Click **Return Run**. Enter a mandatory reason (e.g., `Correction required for overtime allocation`). The run transitions to `RETURNED`. The Payroll Officer can now correct and re-submit.
   - **Option B: Approve Run:** Click **Approve Payroll Run**. The run transitions to **`APPROVED`**.

### 3.2 Cycle Step 7: Finalization & Period Locking
*Actor: Payroll Approver*

1. On the `APPROVED` run, click **Finalize & Lock Payroll Run** (`UC-25`).
2. A confirmation dialog appears (`NFR-6.3`):
   > *"Finalizing will permanently lock all payroll lines and the associated pay period. A cryptographic anchor will be queued for ledger anchoring. Are you sure you wish to proceed?"*
3. Click **Confirm Finalization**:
   - The run state transitions permanently to **`FINALIZED`**.
   - Database triggers enforce write-protection across all 30 payroll lines.
   - The pay period status is marked **`CLOSED`**.
   - A cryptographic payload hash (SHA-256) is generated and committed to the outbox queue (`INTEGRITY_ANCHOR`).
   - Transmit to the external Hyperledger Besu ledger proceeds asynchronously without blocking payslip access (`AC-6.3.5`).

---

## 4. Payslip Generation, Distribution & Reprinting

### 4.1 Batch Payslip Generation
*Actor: Payroll Officer, Approver, or Viewer*

1. Navigate to the finalized payroll run.
2. Click **Generate All Payslips (PDF Batch)** (`UC-27`):
   - Generates a compiled PDF batch containing individual municipal payslips for all 30 employees.
   - Execution finishes in seconds (< 5 minutes per `NFR-3.5`).
3. Send to printer or distribute digitally to department heads.

### 4.2 Individual Payslip Inspection & Reprinting
*Actor: Payroll Officer, Approver, or Viewer*

1. Search for an employee in **Payroll Records Search** (`/records/search`).
2. Open the employee's payroll history. Click on the desired pay period.
3. Click **Download Payslip (PDF)** or **Reprint Payslip**:
   - Rendered strictly from stored historical values (`FR-3.1`).
   - First print records `ORIGINAL` issuance; subsequent prints are automatically stamped `REPRINT #N` in the audit log (`UC-28`).

---

## 5. Reporting & Statutory Remittances

*Actor: Payroll Officer, Approver, or Viewer*

Navigate to **Reports Catalogue** (`/reports`) (`FR-5.1` - `FR-5.11`):

1. **General Payroll Register (`FR-5.1`):** Complete municipal payroll register showing all earnings, deductions, and net pay.
2. **SSS Remittance Schedule (`FR-5.4`):** Monthly Social Security System contribution schedule detailing EE (employee) deductions and derived ER (employer) municipal shares.
3. **PhilHealth Remittance Schedule (`FR-5.5`):** National health insurance remittance breakdown.
4. **Pag-IBIG Remittance Schedule (`FR-5.6`):** Home Development Mutual Fund contributions.
5. **Tax Withholding Report (`FR-5.7`):** Bureau of Internal Revenue (BIR) monthly withholding tax schedules.
6. **Bank Transmittal / Payroll Advice (`FR-5.8`):** Net pay disbursement list for depository bank transmittal.

All reports offer one-click **Export to Excel (XLSX)** and **Export to PDF**.

---

## 6. Post-Finalization Run Reversal

*Actor: Payroll Approver*

In extraordinary circumstances (e.g., severe clerical error discovered after finalization), a finalized run may be reversed (`UC-26`, `FR-4.5`):

1. Navigate to the finalized run.
2. Click **Reverse Payroll Run**.
3. Enter the formal administrative or audit reason in the required field.
4. Confirm the prompt:
   - The run state transitions to **`REVERSED`**.
   - Historical payroll lines are retained for audit and marked void.
   - The pay period is unlocked, permitting a replacement run.
   - A reversal anchor is generated and queued for external ledger recording.
