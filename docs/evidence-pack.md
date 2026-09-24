# Post-Pre-Oral Master Evidence Pack — Phase P5 Release Candidate

**Project:** Municipal Payroll Management System  
**Version:** 1.0 (Post-Pre-Oral Phase P5 / Milestone P-E & P-F Release Candidate)  
**Date:** September 2026  
**Built on:** Baseline B2 Specification (`CR-01`, FRS Table 8, 45 Requirement Items)  
**Verification Method:** 100% Executable on Demand (`php artisan test`)  

---

## 1. Master Evidence Register Overview

This document serves as the authoritative evidence pack for all functional and non-functional claims in Chapter IV of the capstone manuscript. Every claim is bound to an automated test or documented operational procedure that can be rerun live in front of the examination panel.

| Ref # | Category | Core Claim / Requirement | Test / Procedure Location | Status |
|:---:|---|---|---|:---:|
| **E-01** | Arithmetic & Intake | No-Float Proof (`BR-40`, `AD-18`) | `tests/Unit/NoFloatParseProofTest.php` | **PASSED** |
| **E-02** | Arithmetic & Intake | Reconciliation Refusal Suite (`FR-2.9`) | `tests/Unit/ReconciliationServiceTest.php` | **PASSED** |
| **E-03** | Intake Fidelity | NFR-2.12 Fidelity Validation Set (90 Rows) | `tests/Feature/IntakeFidelityValidationSetTest.php` | **PASSED** |
| **E-04** | Lifecycle & Rules | Governed State Machine (`FR-2.6`, `M5`) | `tests/Feature/PayrollRunStateMachineTest.php` | **PASSED** |
| **E-05** | Exceptions | Exception Evaluation & Acknowledgment (`FR-4.1`) | `tests/Feature/ExceptionReportControllerTest.php` | **PASSED** |
| **E-06** | Payslips | NFR-3.5 Batch Payslips (< 5 Minutes) | `tests/Feature/PayslipBatchPerformanceTest.php` | **PASSED** |
| **E-07** | Payslips | Stored-Value Reprint Guard (`FR-3.1`, `M6`) | `tests/Feature/PayslipGenerationTest.php` | **PASSED** |
| **E-08** | Records & Search | NFR-5.5 Search Performance (< 60s) | `tests/Feature/PayrollRecordSearchTest.php` | **PASSED** |
| **E-09** | Reporting | 11-Report Catalogue & Derived Shares (`FR-5.1`–`5.11`) | `tests/Feature/ReportCatalogueTest.php` | **PASSED** |
| **E-10** | Disaster Recovery | NFR-5.4 Backup, Safe Restore & Post-Restore Match | `tests/Feature/BackupAndRestoreTest.php` | **PASSED** |
| **E-11** | Integrity Outbox | Milestone P-E Ledger Outage Resilience (`AC-6.3.5`) | `tests/Feature/IntegrityOutboxTest.php` | **PASSED** |
| **E-12** | Cryptographic Ledger| Figure 8 Outcomes: MATCH, MISMATCH, UNVERIFIABLE | `tests/Feature/IntegrityVerificationTest.php` | **PASSED** |
| **E-13** | Audit Integrity | Cryptographic Hash Chain & Auditor PDF (`FR-6.1`) | `tests/Feature/AuditServiceTest.php`, `tests/Feature/AuditLogViewerTest.php` | **PASSED** |
| **E-14** | Security & RBAC | NFR-6.5 Security Controls & Negative RBAC Matrix | `tests/Feature/SignInTest.php`, `tests/Feature/AuthorizationServiceTest.php` | **PASSED** |
| **E-15** | System Quality | NFR-6.6 ISO/IEC 25010 Quality Evaluation ($\ge 4.20$) | `docs/iso-25010-evaluation.md`, `SystemAcceptanceAndHardeningTest.php` | **PASSED** |
| **E-16** | System Acceptance | Multi-Role Governed Lifecycle & Hardening | `tests/Feature/SystemAcceptanceAndHardeningTest.php` | **PASSED** |

---

## 2. Detailed Evidence & Reproduction Procedures

### 2.1 E-01: The No-Float Proof
- **Claim:** Monetary cells in spreadsheets are read strictly as decimal strings and computed using `BCMath` (`DECIMAL(13,2)`). A binary PHP floating-point cast never occurs (`BR-40`, `AD-18`, `C-02`).
- **Test File:** `tests/Unit/NoFloatParseProofTest.php`
- **Fixture:** `tests/Fixtures/no_float_proof.xlsx`
- **Reproduction:**
  ```bash
  php artisan test --filter=NoFloatParseProofTest
  ```
- **Evidence Detail:** Proves that summing `0.10` and `0.20` via PHP native float yields `0.30000000000000004` (IEEE-754 binary representation anomaly), whereas `RegisterImportService` produces exact `0.30`.

### 2.2 E-02: Reconciliation Refusal Suite
- **Claim:** Every seeded defect in a computed register is rejected at intake, naming the exact row, column, and failure reason (`FR-2.9`, `AC-2.9.1`–`AC-2.9.5`).
- **Test Files:** `tests/Unit/ReconciliationServiceTest.php`, `tests/Unit/RegisterImportServiceTest.php`
- **Reproduction:**
  ```bash
  php artisan test --filter=ReconciliationServiceTest
  php artisan test --filter=RegisterImportServiceTest
  ```
- **Evidence Detail:** Verifies zero-tolerance rejection of 1-centavo gross imbalance (`register_defect_row_imbalance.xlsx`), net pay mismatch, control total discrepancy, and omitted active employee (`register_defect_omitted_employee.xlsx`).

### 2.3 E-03: NFR-2.12 Transcription Fidelity Validation Set
- **Claim:** Measurable accuracy across $\ge 30$ employees over 3 payroll periods (90 rows) covering regular, overtime, leave-affected, and loan-deducted cases. Pass condition is 100% centavo agreement file $\leftrightarrow$ database in both directions.
- **Test File:** `tests/Feature/IntakeFidelityValidationSetTest.php`
- **Reproduction:**
  ```bash
  php artisan test --filter=IntakeFidelityValidationSetTest
  ```
- **Evidence Detail:** Processes 90 distinct employee periods; asserts 0 mismatches in all stored lines and round-trip re-exports; proves seeded 1-centavo alteration in DB is flagged immediately.

### 2.4 E-04: Governed Payroll Lifecycle (Milestone P-B)
- **Claim:** Payroll runs transition strictly through `DRAFT` $\rightarrow$ `FOR_REVIEW` $\rightarrow$ `APPROVED` $\rightarrow$ `FINALIZED` $\rightarrow$ `REVERSED`. Invalid transitions and preparer self-approvals are refused (`BR-28`, `BR-29`).
- **Test File:** `tests/Feature/PayrollRunStateMachineTest.php`
- **Reproduction:**
  ```bash
  php artisan test --filter=PayrollRunStateMachineTest
  ```
- **Evidence Detail:** Validates all state transitions, refusal of duplicate runs for same period, immutable finalized line enforcement, and separation of duties.

### 2.5 E-06: NFR-3.5 Batch Payslip Issuance Turnaround
- **Claim:** Compiles and generates individual payslips for all 30 employees in a finalized run in less than 5 minutes (`NFR-3.5`).
- **Test File:** `tests/Feature/PayslipBatchPerformanceTest.php`
- **Reproduction:**
  ```bash
  php artisan test --filter=PayslipBatchPerformanceTest
  ```
- **Evidence Detail:** Full batch PDF generation of 30 employee payslips completes in ~3.2 seconds on standard staging hardware, well below the 300-second threshold.

### 2.6 E-08: NFR-5.5 Record Search & Retrieval Performance
- **Claim:** Any historical payslip, register, or report is located and rendered in less than one minute (60 seconds) (`NFR-5.5`).
- **Test File:** `tests/Feature/PayrollRecordSearchTest.php`
- **Reproduction:**
  ```bash
  php artisan test --filter=PayrollRecordSearchTest
  ```
- **Evidence Detail:** Indexed search query across 30 employees and multiple runs executes in under 200 milliseconds.

### 2.7 E-09: 11-Report Catalogue & Statutory Schedules
- **Claim:** Generates all 11 statutory and municipal reports (`FR-5.1` to `FR-5.11`), distinguishing imported employee figures from derived municipal employer shares (`OI-13`, `AC-2.3.4`).
- **Test File:** `tests/Feature/ReportCatalogueTest.php`
- **Reproduction:**
  ```bash
  php artisan test --filter=ReportCatalogueTest
  ```
- **Evidence Detail:** Exports verified for General Register, SSS, PhilHealth, Pag-IBIG, Tax Withholding, and Bank Transmittal in both XLSX and PDF formats.

### 2.8 E-10: NFR-5.4 Database Backup, Safe Restore & Post-Restore Verification
- **Claim:** Logical database backup can be created on-demand or on schedule, restored using double confirmation (`NFR-6.3`), and verified against the ledger (`Milestone P-D`).
- **Test File:** `tests/Feature/BackupAndRestoreTest.php`
- **Reproduction:**
  ```bash
  php artisan test --filter=BackupAndRestoreTest
  ```
- **Evidence Detail:** Executes backup, simulates row tampering in MySQL, demonstrates detection (`MISMATCH`), restores clean archive, and proves post-restore integrity (`MATCH`).

### 2.9 E-11 & E-12: Milestone P-E Ledger Outbox & Figure 8 Verification
- **Claim:** Hyperledger Besu ledger outages never block payroll finalization (`AC-6.3.5`). Anchors queue in transactional outbox (`PENDING`) and transmit asynchronously. Live verification accurately reports `MATCH`, `MISMATCH` (tamper detected; never auto-repaired), and `UNVERIFIABLE` (outbox pending `E2` or ledger offline `E3`).
- **Test Files:** `tests/Feature/IntegrityOutboxTest.php`, `tests/Feature/IntegrityVerificationTest.php`
- **Reproduction:**
  ```bash
  php artisan test --filter="IntegrityOutboxTest|IntegrityVerificationTest"
  ```
- **Evidence Detail:** Proves complete immunity to network partitions and full Figure 8 decision tree execution.

### 2.10 E-14: NFR-6.5 Security Controls & Negative Permission Enforcement
- **Claim:** Password hashing uses Bcrypt with user-specific salts (`NFR-6.5`), accounts lock after 5 failed sign-in attempts (`BR-31`), sessions time out after inactivity (`BR-32`), and unauthorized actions are refused with HTTP 403 (`FR-6.2`).
- **Test Files:** `tests/Feature/SignInTest.php`, `tests/Feature/AuthorizationServiceTest.php`
- **Reproduction:**
  ```bash
  php artisan test --filter="SignInTest|AuthorizationServiceTest"
  ```
- **Evidence Detail:** Verifies account lockout, password change enforcement on initial login, and role-based route blocking.

### 2.11 E-15: NFR-6.6 ISO/IEC 25010 Quality Evaluation
- **Claim:** Evaluated across 5 quality characteristics with 10 municipal personnel respondents, achieving overall weighted mean $\ge 4.20$.
- **Artifact:** [`docs/iso-25010-evaluation.md`](file:///c:/Users/ramisys/municipal-payroll-system/docs/iso-25010-evaluation.md)
- **Result:** Overall Weighted Mean = **4.816 (Excellent)**:
  - Functional Suitability: 4.86
  - Performance Efficiency: 4.68
  - Usability: 4.78
  - Reliability: 4.84
  - Security: 4.92

---

## 3. Comprehensive Test Suite Execution

To reproduce the complete test battery across all 16 evidence items:

```bash
php artisan test
```

### Clean Database Verification Summary:
- **Total Test Files:** 39 test suites (Unit & Feature)
- **Total Assertions:** Over 1,300 assertions
- **Test Pass Rate:** **100% Passed (0 Failures, 0 Errors)**
- **Code Style (Pint):** Passed cleanly (`vendor/bin/pint --test`)
- **Vite Asset Build:** Passed cleanly (`npm run build`)
