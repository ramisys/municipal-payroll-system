# Functional Requirements Specification

**Project:** Payroll Management System
**Document:** Functional Requirements Specification (FRS)
**Version:** 1.1
**Date:** August 30, 2026
**Baseline:** B1 — frozen August 30, 2026 · see [baseline.md](./baseline.md)
**Traces to:** [Problem-to-Requirements Matrix](./problem-requirements-matrix.md)

---

## Document control

| | |
|---|---|
| Functional requirements | 30 (26 traced from the matrix, 4 foundation requirements added — see §1.5) |
| Non-functional requirements | 8 gated (NFR-7.1–7.4 are ungated quality expectations — §5) |
| Data requirements | 5 (DR-1.6 structural, DR-2.1–2.4 retention and integrity) + 24 entity rows naming 25 entities in §8.1 |
| Business & computation rules | 36 |
| Actors | 4 human, 1 supporting |
| Modules | 7 |
| Open items requiring client confirmation | 11 (§11) — OI-08 answered by the [system architecture](./system-architecture.md); 10 outstanding |

---

# 1. Introduction

## 1.1 Purpose

This document specifies what the Payroll Management System must do. It is written to be verifiable: every functional requirement states its actor, its trigger, its behavior, the rules that govern it, and the acceptance criteria that decide whether it was met. It is the contract between the requirements analysis (Chapter I) and the system testing reported in Chapter IV.

This document does **not** specify how the system is built. Screen layouts, database schema, framework choice, and deployment topology belong to the design documentation — respectively the [use case model](./use-case-model.md), the [data model](./data-model.md), and the [system architecture](./system-architecture.md). Where this specification states an environment fact, as §2.4 now does, it is reporting what that documentation decided, not deciding it.

## 1.2 Scope

The system replaces the client's Excel-based payroll process for the preparation, computation, verification, approval, documentation, and record-keeping of employee compensation.

**In scope**

- Employee master data, compensation profiles, and employment history
- Attendance intake and leave administration as payroll inputs
- Payroll computation: basic pay, additional pay, adjustments, statutory and other deductions, net pay
- Payroll validation, review, and approval workflow
- Payslip generation, export, and reprinting
- Payroll records storage, retrieval, and reporting, including statutory remittance reports
- User authentication, role-based authorization, and audit logging
- Tamper-evidence for finalized payroll records and the audit trail, by anchoring cryptographic hashes in a permissioned ledger held outside the payroll database (FR-6.3)

**Out of scope** (see §11 and the manuscript's Scope and Delimitations)

- Actual disbursement of pay — the system produces a bank transmittal listing or payroll register; it does not transfer funds
- Biometric or timekeeping hardware — the system consumes an export file; it does not operate the device
- Accounting, general ledger, or financial statement integration
- Employee self-service portal — payslips are issued by the payroll office, not retrieved by employees
- Recruitment, performance management, and other HR functions outside compensation

## 1.3 Definitions and acronyms

| Term | Definition |
|---|---|
| **Cut-off** | The date range of attendance covered by one payroll run. |
| **Pay period** | The calendar period for which pay is computed and released; may be semi-monthly or monthly (see OI-02). |
| **Payroll run** | One execution of the computation for one pay period, holding one payroll line per included employee. |
| **Payroll line** | The computed result for one employee within one payroll run: all earnings, all deductions, and net pay. |
| **Payroll register** | The tabular listing of every payroll line in a run. |
| **DTR** | Daily Time Record — the per-employee record of time in and time out. |
| **Gross pay** | Total earnings before any deduction. |
| **Taxable compensation** | Gross pay less non-taxable items, used as the base for withholding tax. |
| **Net pay** | Gross pay less total deductions; the amount payable to the employee. |
| **Day factor** | The divisor converting an annual or monthly rate into a daily rate (e.g., 261, 313, 365). |
| **Statutory deduction** | A deduction mandated by law: SSS, PhilHealth, Pag-IBIG, and withholding tax. |
| **Effectivity date** | The date from which a reference table or rate applies. |
| **De minimis benefits** | Benefits exempt from income tax up to limits set by the BIR. |
| **MoSCoW** | Prioritization scheme: Must have, Should have, Could have, Won't have this release. |

## 1.4 References

| Ref | Source |
|---|---|
| R-01 | Problem-to-Requirements Matrix, this project, August 2026 |
| R-02 | Presidential Decree No. 442, *Labor Code of the Philippines*, as amended — premium pay, overtime, night differential, holiday pay |
| R-03 | Republic Act No. 11199, *Social Security Act of 2018*, and the SSS contribution schedule in force |
| R-04 | Republic Act No. 11223, *Universal Health Care Act*, and the PhilHealth premium contribution circular in force |
| R-05 | Republic Act No. 9679, *Home Development Mutual Fund Law of 2009* (Pag-IBIG), and the contribution schedule in force |
| R-06 | Republic Act No. 10963 (TRAIN) and BIR Revenue Regulations No. 11-2018 — revised withholding tax on compensation |
| R-07 | Presidential Decree No. 851 — 13th month pay |
| R-08 | ISO/IEC 25010:2011, *Systems and software Quality Requirements and Evaluation — System and software quality models* |

> **Note on rates.** This specification defines the *structure* of every statutory computation but deliberately states no contribution or tax figures. Rates change by circular, and a rate hardcoded into a specification becomes wrong without notice. All rates and brackets are reference data entered under FR-2.3 and dated under BR-14.

## 1.5 Relationship to the Problem-to-Requirements Matrix

All 26 functional requirements from the matrix appear here with their identifiers unchanged, so a feature specified here, implemented in Chapter III, and tested in Chapter IV can be cited back to the problem it answers.

Four requirements marked **⊕** are new. They answer no single problem in the matrix and so were correctly absent from it, but no payroll system can operate without them — they are the foundation the traced requirements stand on. They are numbered `FR-0.x` and traced to OBJ 6, which covers system-level qualities.

| New | Requirement | Why the matrix did not surface it |
|---|---|---|
| ⊕ FR-0.1 | User authentication | Prerequisite for FR-6.1 (audit trail) and FR-6.2 (RBAC) — an audit entry is meaningless without an identified user. |
| ⊕ FR-0.2 | User account management | RBAC in FR-6.2 assigns roles; something must create and maintain the accounts that hold them. |
| ⊕ FR-0.3 | Organization profile and payroll calendar | FR-2.6 computes "a period"; periods must be defined before they can be computed. |
| ⊕ FR-0.4 | Reference data maintenance | FR-1.2 and FR-2.4 reference departments, positions, leave types, and earning/deduction types that must exist first. |

**`FR-6.3` is a twenty-sixth matrix requirement, not a fifth ⊕.** It deepens the response to a problem the matrix already carries rather than answering a new one: P6 asks that an error be made *detectable, attributable, and recoverable*, FR-6.1 and FR-6.2 make it detectable and attributable **within** the application, and design review found that neither detects a change made outside it. Because it answers P6 rather than standing apart from it, it was added to the matrix as P6's seventh requirement rather than carried here as a foundation addition. The matrix marks it **✦** and records its provenance; this specification treats it exactly as it treats FR-6.1 and FR-6.2.

The same is true of four data requirements. The matrix carried one — DR-1.6, the normalized database — and §8.2 adds **DR-2.1 through DR-2.4**, which state how payroll data must be retained, versioned, typed, and deleted. Like the `FR-0.x` requirements they answer no single problem on their own; they are the conditions under which DR-1.6 remains true over time. They are traced in §9 with the rest.

**Requirement arithmetic.** 26 functional requirements from the matrix + 4 foundation = **30 FR**; + **8 NFR** + **5 DR** = **43 requirement items**, which is the number traced in §9 and reached by the use case model. The matrix itself totals 35 — its 26 FR, 8 NFR, and single DR — and the difference of 8 is the four `FR-0.x` foundation requirements plus `DR-2.1` – `DR-2.4` (§8.2). NFR-7.1–7.4 are quality expectations rather than gated requirements (§5) and are outside that count.

---

# 2. Overall description

## 2.1 Product perspective

The system is a replacement, not an addition. Every function it performs is currently performed manually or in Microsoft Excel, and on cutover the spreadsheets cease to be the system of record. It is self-contained: it exchanges data with the outside world only through file import (attendance) and file export (payslips, reports, bank transmittal).

```
 Timekeeping device ──(export file)──▶ ┌─────────────────────────┐
                                       │                         │──▶ Payslips (PDF)
 Payroll Officer ────(encoding)──────▶ │  Payroll Management     │──▶ Payroll register
                                       │       System            │──▶ Remittance reports
 Approver ───────────(approval)──────▶ │                         │──▶ Bank transmittal
                                       └─────────────────────────┘
                                                  │
                                          Payroll database
```

## 2.2 Product functions (module summary)

| Module | Covers | Requirements |
|---|---|---|
| **M1** System Administration | Authentication, users, roles, organization profile, payroll calendar, reference data, audit log | FR-0.1–0.4, FR-6.1, FR-6.2 |
| **M2** Employee Management | Employee master file, compensation profile, employment status | FR-1.1, FR-1.2, FR-1.5 |
| **M3** Attendance & Leave | DTR import, exception encoding, leave filing and balances | FR-1.3, FR-1.4 |
| **M4** Payroll Computation | Basic pay, additional pay, adjustments, statutory deductions, net pay, period runs | FR-2.1–2.6 |
| **M5** Validation & Approval | Exception report, payroll register review, targeted recomputation, approval workflow, period lock | FR-4.1–4.5 |
| **M6** Payslip | Generation, layout, batch export, reprint | FR-3.1–3.4 |
| **M7** Records & Reporting | Storage, search, report generation, backup | FR-5.1–5.3, NFR-5.4 |

A module names where a function is **administered**, not everywhere its effect is felt. Two functions are administered from a module other than the one they serve, and the split is deliberate: statutory schedule maintenance (FR-2.3) and backup administration (NFR-5.4) are performed from **M1** by the Administrator; their effect belongs to **M4** and **M7** respectively. The use case model places UC-05 and UC-07 in M1 on this basis, and the data model's subject areas are a grouping of data rather than of modules (data model §2).

## 2.3 User classes and characteristics

| Actor | Description | Technical skill | Frequency of use |
|---|---|---|---|
| **Payroll Officer** | Prepares payroll. Encodes employee and attendance data, runs computation, corrects exceptions, submits for review. The system's primary user. | Comfortable with Excel; not technical. | Daily during a cut-off; heavy at period end. |
| **Approver** | Reviews and approves or returns payroll runs. Typically HR Head, Finance Officer, or owner. Does not encode. | Basic computer literacy. | Once or twice per pay period. |
| **Administrator** | Maintains user accounts, roles, reference data, statutory tables, and backups. | Highest of the four; may be an external IT contact. | Occasional; monthly or on rate changes. |
| **Viewer** | Read-only access to registers and reports for management or audit. Cannot encode, compute, or approve. | Basic. | Ad hoc. |
| **System Clock** | Supporting, non-human. Triggers scheduled behavior with no user present: the backup schedule of NFR-5.4 and the session timeout of BR-32. | — | Continuous. |

Four actors are human; the System Clock is a supporting non-human actor and holds no permissions in FR-6.2. Employees are **not** system users in this release. They receive printed or PDF payslips from the payroll office.

## 2.4 Operating environment

The deployment target was open when this specification was first written and is now **fixed** (OI-08, closed). The [system architecture](./system-architecture.md) derives it from constraints already stated here rather than choosing it: C-03 and SW-04 exclude any hosted option, and NFR-7.1 excludes a single workstation, leaving local-network operation as the only surviving candidate. The one genuinely open choice was the client style — browser rather than installed desktop application — recorded there as decision AD-01.

**The operating environment is therefore:**

| | |
|---|---|
| **Topology** | Browser clients on the payroll office local network, one application server host, one database server role (architecture §3, Figure 1) |
| **Client** | A current browser on each Microsoft Windows workstation, consistent with the client's existing Excel-based operation. No installed client and no per-workstation deployment |
| **Server** | Web server and PHP application on one host; a relational database management system enforcing the constraints of NFR-6.4 |
| **Network** | Local network only. No route to the internet and no public exposure (C-03, SW-04) |
| **Transport** | HTTPS with a locally-issued certificate (CM-01) |
| **Output** | Any printer available to the workstation operating system (HW-01) |

Two consequences bear on requirements stated elsewhere. **NFR-5.5** (retrieval performance) is measured against the database on the server host rather than against a local file, and the indexes that make one minute achievable are specified in the data model §7.3. **NFR-6.5** (security controls) is satisfied server-side: no database credential ever reaches a workstation — a property the installed-client alternative would not have provided, and one of the reasons AD-01 went the way it did.

A change to the topology would reopen both.

## 2.5 Design and implementation constraints

| ID | Constraint |
|---|---|
| C-01 | Statutory computation logic must be data-driven, not hardcoded. A change in an SSS, PhilHealth, Pag-IBIG, or BIR schedule must be satisfiable by editing reference data, without modifying source code. |
| C-02 | All monetary values are stored in a fixed-point or decimal type. Binary floating-point types must not be used for currency (see BR-01). |
| C-03 | The system must operate correctly with no internet connection available. |
| C-04 | Historical payroll runs must remain reproducible: a run computed under a superseded rate table must continue to display the figures it was computed with. |
| C-05 | The system must be operable by staff whose prior tool was Excel, with training measured under NFR-6.6. |

## 2.6 Assumptions and dependencies

| ID | Assumption |
|---|---|
| A-01 | The client supplies complete and accurate employee master data for initial migration. |
| A-02 | Attendance is captured by an existing means that can produce a file export, or is encoded manually (OI-04). |
| A-03 | The client provides the current statutory contribution schedules and their effectivity dates for initial reference-data loading. |
| A-04 | One approver is sufficient; multi-level approval is not required (OI-09). |
| A-05 | The client's pay frequency and day factor are fixed for the duration of the project (OI-02, OI-03). |

---

# 3. Priority and requirement notation

Each requirement below carries:

**Priority** — `Must` (system fails without it) · `Should` (significant value, deferrable) · `Could` (desirable)
**Source** — the problem from the matrix, or ⊕ for a foundation requirement
**Objective** — the Chapter I objective it fulfills
**Actor** — who performs or triggers it

Acceptance criteria are numbered `AC-<requirement>.<n>` and are written so that each one is independently pass/fail. Business rules referenced as `BR-nn` are defined in §7.

**Every requirement in this release is `Must`.** The scope was narrowed in the matrix rather than here: what survived into this specification is what the system fails without. The reason is structural — a requirement that supplies a pass/fail acceptance criterion to another requirement cannot be deferrable without making that criterion unenforceable. NFR-3.5, NFR-5.5, and NFR-6.3 each do exactly that, as does the reversal capability of FR-4.5, so all four are `Must`.

`Should` and `Could` remain defined for two reasons. Chapter III may need to defer an item under schedule pressure, and a deferral must be expressible in the same notation. And the scale is already in active use **inside** a requirement: the FR-5.3 report catalogue ranks eleven individual reports as `Must`, `Should`, or `Could`, so that a report can be dropped without dropping the requirement that produces reports. Priority applies to requirements and to the items within them; at requirement level, this release has no deferrable item.

---

# 4. Functional requirements

## M1 — System Administration

### ⊕ FR-0.1 — User authentication

**Priority** Must · **Source** ⊕ (enables FR-6.1, FR-6.2) · **Objective** OBJ 6 · **Actor** All

The system shall authenticate every user before granting access to any function or data.

**Behavior**

1. The system presents a login requiring a unique username and a password.
2. On valid credentials for an active account, the system establishes a session and records the sign-in in the audit log (FR-6.1).
3. On invalid credentials, the system denies access with a message that does not reveal which of the two values was wrong.
4. After a configurable number of consecutive failed attempts, the system locks the account until an Administrator releases it.
5. The system terminates an idle session after a configurable timeout (NFR-6.5) and on explicit sign-out.

**Rules** BR-30, BR-31, BR-32

**Acceptance criteria**

- AC-0.1.1 No system function is reachable without an authenticated session.
- AC-0.1.2 A password is never displayed, logged, or stored in recoverable form.
- AC-0.1.3 A locked account cannot authenticate even with correct credentials until released.
- AC-0.1.4 An idle session expires within the configured timeout and requires re-authentication.

---

### ⊕ FR-0.2 — User account management

**Priority** Must · **Source** ⊕ (enables FR-6.2) · **Objective** OBJ 6 · **Actor** Administrator

The system shall allow an Administrator to create, edit, deactivate, and reactivate user accounts and to assign exactly one role to each.

**Behavior**

1. The Administrator creates an account with a username, display name, role, and initial password.
2. The system requires the user to change the initial password at first sign-in.
3. The Administrator may deactivate an account; a deactivated account cannot authenticate but is never deleted, so audit entries retain a resolvable identity.
4. The Administrator may reset a password; the reset is recorded in the audit log.
5. A user may change their own password after re-entering the current one.

**Rules** BR-30, BR-33

**Acceptance criteria**

- AC-0.2.1 Usernames are unique across all accounts, active and inactive.
- AC-0.2.2 Deleting a user account is not offered; only deactivation.
- AC-0.2.3 Every account has exactly one role at any time.
- AC-0.2.4 An account created by an Administrator cannot be used for payroll work until its initial password has been changed.

---

### ⊕ FR-0.3 — Organization profile and payroll calendar

**Priority** Must · **Source** ⊕ (enables FR-2.6) · **Objective** OBJ 6 · **Actor** Administrator

The system shall hold the employer's identifying information and the definition of pay periods.

**Behavior**

1. The Administrator maintains the organization profile: registered name, address, employer identification numbers (SSS, PhilHealth, Pag-IBIG, BIR TIN), and the logo used on payslips and reports.
2. The Administrator defines the pay frequency and, for each payroll year, generates the pay periods with their cut-off start date, cut-off end date, and pay date.
3. The Administrator maintains the day factor used for rate derivation (BR-02).
4. The Administrator maintains the holiday calendar, marking each date as a regular holiday, special non-working day, or local holiday.

**Rules** BR-02, BR-05, BR-34

**Acceptance criteria**

- AC-0.3.1 Pay periods within a payroll year do not overlap and leave no gap.
- AC-0.3.2 A payroll run cannot be created for a period that has not been defined.
- AC-0.3.3 Employer identification numbers appear on the corresponding remittance reports without re-entry.
- AC-0.3.4 A date marked as a holiday is applied by FR-2.2 at the correct premium for its holiday type.

---

### ⊕ FR-0.4 — Reference data maintenance

**Priority** Must · **Source** ⊕ (enables FR-1.2, FR-2.4) · **Objective** OBJ 6 · **Actor** Administrator

The system shall allow maintenance of the lists that employee and payroll records refer to: departments, positions, employment statuses, leave types with their accrual rules, earning types, and deduction types.

**Behavior**

1. The Administrator adds, edits, and deactivates entries in each reference list.
2. Each earning type is flagged taxable or non-taxable, and each is flagged as included or excluded from the base for 13th month pay (BR-12, BR-21).
3. Each deduction type is flagged statutory or non-statutory and, if non-statutory, whether it participates in the net-pay floor check (BR-25).
4. A reference entry that is in use by any record may be deactivated but not deleted.

**Rules** BR-12, BR-21, BR-25, BR-33

**Acceptance criteria**

- AC-0.4.1 A reference entry referenced by an employee or payroll record cannot be deleted.
- AC-0.4.2 A deactivated entry cannot be selected on new records but continues to display correctly on existing ones.
- AC-0.4.3 Every earning type carries an explicit taxability flag; no earning type defaults silently.

---

### FR-6.1 — Audit trail

**Priority** Must · **Source** P6 · **Objective** OBJ 6 · **Actor** System (automatic); Administrator, Viewer (read)

The system shall record every create, update, delete, compute, approve, and sign-in action with the acting user account, the timestamp, the affected record, and — for updates — the previous and new values of each changed field.

**Behavior**

1. The system writes an audit entry as part of the same transaction as the action it records; if the action rolls back, so does its audit entry.
2. Audit entries are append-only. No user role, including Administrator, may edit or delete one from within the system.
2a. Each entry additionally stores the hash of its own content and the hash of the entry preceding it (BR-35), so that a deletion or alteration made outside the application breaks the chain and is detectable under FR-6.3.
3. An Administrator or Viewer may browse and filter the audit log by user, date range, record type, and action.
4. Audit entries for a payroll run are viewable from the run itself, giving the full change history of that period.

**Rules** BR-26, BR-27

**Acceptance criteria**

- AC-6.1.1 Every state-changing action performed through the system produces exactly one audit entry.
- AC-6.1.2 The system offers no function to modify or remove an audit entry.
- AC-6.1.3 An audit entry for an update shows both the previous and the new value of every field that changed.
- AC-6.1.4 An audit entry naming a deactivated user still resolves to that user's display name.
- AC-6.1.5 Every audit entry carries its own hash and the hash of its predecessor, and the chain verifies unbroken from the first entry.

---

### FR-6.2 — Role-based access control

**Priority** Must · **Source** P6 · **Objective** OBJ 6 · **Actor** Administrator (assignment); System (enforcement)

The system shall restrict every function and data view according to the signed-in user's role, enforcing separation between preparing and approving a payroll.

**Permission matrix**

| Function | Payroll Officer | Approver | Administrator | Viewer |
|---|:---:|:---:|:---:|:---:|
| Maintain employee records (FR-1.1, 1.2) | ✔ | — | ✔ | — |
| Import and encode attendance (FR-1.3) | ✔ | — | — | — |
| Approve leave applications (FR-1.4) | file only | ✔ | — | — |
| Create and compute a payroll run (FR-2.6) | ✔ | — | — | — |
| View exception report (FR-4.1) | ✔ | ✔ | ✔ | read |
| Submit run for review (FR-4.4) | ✔ | — | — | — |
| Approve or return a run (FR-4.4) | — | ✔ | — | — |
| Finalize a run (FR-4.4, 4.5) | — | ✔ | — | — |
| Generate payslips (FR-3.1, 3.3) | ✔ | — | — | — |
| Reprint a past payslip (FR-3.4) | ✔ | ✔ | ✔ | read |
| Search and retrieve payroll records (FR-5.2) | ✔ | ✔ | ✔ | ✔ |
| Generate reports (FR-5.3) | ✔ | ✔ | ✔ | ✔ |
| Maintain statutory tables (FR-2.3) | — | — | ✔ | read |
| Maintain users and roles (FR-0.2) | — | — | ✔ | — |
| View audit log (FR-6.1) | — | ✔ | ✔ | ✔ |
| Verify record integrity (FR-6.3) | — | ✔ | ✔ | ✔ |
| Run and restore backup (NFR-5.4) | — | — | ✔ | — |

**Rules** BR-28, BR-29

**Acceptance criteria**

- AC-6.2.1 The account that submits a payroll run for review cannot approve that run.
- AC-6.2.2 A function absent from a role's permissions is not merely hidden in the interface but refused if invoked directly.
- AC-6.2.3 A Viewer cannot alter any record in any module.
- AC-6.2.4 An Administrator cannot create, compute, or approve a payroll run.
- AC-6.2.5 A Viewer cannot open employee master data, attendance records, or leave applications. The Viewer's read access is limited to payroll registers, payslips, reports, the exception report, search results over payroll records, and the audit log — the outputs of payroll, not its inputs.

---

### FR-6.3 — Ledger-anchored integrity verification

**Priority** Must · **Source** P6 ✦ (added in design review — see matrix §P6 and §1.5) · **Objective** OBJ 6 · **Actor** System (anchoring); Administrator, Approver, Viewer (verification)

The system shall record a cryptographic fingerprint of every finalized payroll run, every reversal, and every segment of the audit trail in an append-only ledger held outside the payroll database, so that any later alteration of those records is **detectable** rather than merely prohibited.

**On the strength of this requirement.** Every acceptance criterion below holds regardless of who administers the ledger: altering MySQL without altering the ledger produces a mismatch either way. What the administrative model changes is the **threat** the requirement covers. Where the ledger is administered separately from the payroll database, FR-6.3 detects alteration by anyone, including a database administrator acting deliberately. Where one person administers both, it detects accidental corruption, failed restores, and application defects — a smaller claim, and still a useful one. The arrangement is recommended in the architecture and pending client validation as **OI-11**; Chapter IV must state whichever is true rather than the stronger of the two.

**What this adds to FR-6.1 and FR-4.5.** BR-27 forbids the system from altering an audit entry or a finalized run, and FR-6.2 prevents any role from doing so. Both are enforced *inside* the payroll database. Neither detects a change made **outside** the application — by a database administrator with direct access, or by restoration of a doctored backup. FR-6.3 closes that gap: it does not prevent such a change, it makes it provable after the fact.

**Behavior**

1. On finalization of a payroll run (FR-4.5), the system computes a hash over the run's totals, its payroll lines, and the version references bound to them, and queues that hash for anchoring.
2. On creation of a reversal record (FR-4.5), the system computes and queues a hash of the reversal.
3. Each audit entry stores the hash of its own content together with the hash of the preceding entry, forming a chain (BR-35). At a defined interval the system computes a root hash over the segment of entries written since the previous anchor and queues it.
4. **Only hashes are written to the ledger.** No employee name, figure, rate, or any other payroll datum leaves the payroll database. The ledger holds fingerprints, never records.
5. Anchoring is **asynchronous**. The queue entry is written in the same transaction as the payroll action, but transmission to the ledger occurs afterward, so that ledger latency or unavailability never delays or fails a payroll operation (see AC-6.3.5).
6. If the ledger is unreachable, queued anchors accumulate and are transmitted when it returns. The system reports the number of unanchored records and their age wherever integrity status is displayed.
7. A user with permission may verify any anchored record on demand: the system recomputes the hash from the current contents of the payroll database and compares it with the value held in the ledger, reporting **match**, **mismatch**, or **not yet anchored**.
8. Verification is itself an auditable event, recorded with the acting user, the timestamp, and the outcome.
9. A mismatch is reported prominently and is never resolved automatically. It states which record diverged and when that record was anchored, and directs the Administrator to the restore procedure of NFR-5.4.

**Rules** BR-26, BR-27, BR-35, BR-36

**Acceptance criteria**

- AC-6.3.1 Finalizing a payroll run produces exactly one queued anchor, and that anchor reaches the ledger and is confirmed.
- AC-6.3.2 Verification of an unaltered finalized run reports a match, and does so for a run finalized before the most recent statutory schedule change (C-04).
- AC-6.3.3 A payroll figure altered directly in the database, bypassing the application, causes verification of that run to report a mismatch naming the run.
- AC-6.3.4 An audit entry deleted or altered directly in the database breaks the hash chain, and verification identifies the position at which the chain fails.
- AC-6.3.5 With the ledger stopped, a payroll run can still be computed, submitted, approved, and finalized; the anchor is queued and is transmitted once the ledger is restarted.
- AC-6.3.6 No payroll data of any kind is present in the ledger. Inspection of ledger contents yields only hashes, identifiers, and timestamps.
- AC-6.3.7 A verification attempt records an audit entry naming the user, the record verified, and the outcome.

---

## M2 — Employee Management

### FR-1.1 — Employee master file

**Priority** Must · **Source** P1 · **Objective** OBJ 1 · **Actor** Payroll Officer, Administrator

The system shall maintain one authoritative record per employee, created once and reused by every succeeding payroll period.

**Behavior**

1. The user creates an employee record capturing: employee number, full name, date of birth, sex, civil status, contact details, address, date hired, employment status, department, position, and government identification numbers (SSS, PhilHealth, Pag-IBIG, TIN).
2. The system assigns or accepts a unique employee number (BR-06).
3. The user may edit any field; the system records the change under FR-6.1.
4. The user may deactivate an employee on separation, capturing the separation date and reason. A deactivated employee is excluded from new payroll runs from the period following the separation date but remains in all historical runs.
5. The user may reactivate a rehired employee, preserving the original record and its history.
6. The user may search and filter the employee list by name, employee number, department, position, and status.

**Rules** BR-06, BR-07, BR-33

**Acceptance criteria**

- AC-1.1.1 An employee record entered once is available to all succeeding payroll periods with no re-encoding.
- AC-1.1.2 Employee numbers are unique across active and inactive employees.
- AC-1.1.3 Deleting an employee who appears in any payroll run is refused; only deactivation is permitted.
- AC-1.1.4 A deactivated employee does not appear in a new payroll run but appears unchanged in every prior run and report.
- AC-1.1.5 Over three consecutive test payroll periods, zero unchanged employee records require re-encoding.

---

### FR-1.2 — Compensation profile

**Priority** Must · **Source** P1 · **Objective** OBJ 1 · **Actor** Payroll Officer, Administrator

The system shall hold each employee's pay basis, rates, recurring allowances, and standing deduction schedules, and carry them forward automatically to each new period.

**Behavior**

1. The user records the pay basis — monthly, daily, or hourly — and the corresponding basic rate.
2. The system derives the remaining rates from the basic rate and the day factor (BR-02) and displays them; derived rates are not hand-entered.
3. The user records recurring earnings (e.g., transportation, meal, or cost-of-living allowance), each referencing an earning type from FR-0.4, with an amount and an effectivity date.
4. The user records recurring deductions and loan accounts, each with a principal, an amortization amount, a start period, and a term. The system tracks the outstanding balance and stops the deduction when the balance reaches zero (BR-23).
5. The user records the statutory coverage flags and, where applicable, the fixed contribution basis for each agency.
6. A rate change is recorded as a new dated entry rather than an overwrite, so historical runs remain reproducible (C-04, BR-08).

**Rules** BR-02, BR-08, BR-22, BR-23

**Acceptance criteria**

- AC-1.2.1 Derived daily and hourly rates match BR-02 for the configured day factor.
- AC-1.2.2 Recurring allowances and deductions appear in the next payroll run without re-entry.
- AC-1.2.3 A loan deduction stops automatically in the period its outstanding balance reaches zero, with no over-deduction.
- AC-1.2.4 A rate change effective mid-year does not alter the figures of any already-finalized run.
- AC-1.2.5 An employee with no compensation profile is flagged by FR-4.1 and cannot produce a computed payroll line.

---

### FR-1.5 — Validation at point of entry

**Priority** Must · **Source** P1 · **Objective** OBJ 1 · **Actor** System

The system shall validate data as it is entered and refuse to save records that fail validation.

**Behavior**

1. Required fields are enforced: no employee record saves without employee number, name, date hired, pay basis, and basic rate.
2. Data types and ranges are enforced: dates are valid calendar dates, rates and amounts are non-negative, and government identification numbers match their expected format.
3. Date logic is enforced: date hired is not in the future; separation date is not earlier than date hired; a leave date range does not end before it begins.
4. Duplicate detection is enforced: a duplicate employee number is refused, and a probable duplicate by name and date of birth raises a warning the user must acknowledge.
5. Validation messages name the field and state the correction required.

**Rules** BR-06, BR-07

**Acceptance criteria**

- AC-1.5.1 A record failing any required-field, type, range, or date-logic rule cannot be saved.
- AC-1.5.2 A duplicate employee number is refused with a message identifying the existing record.
- AC-1.5.3 After the test data load, the database contains zero duplicate and zero incomplete employee records.
- AC-1.5.4 Every validation message names the specific field and the required correction; no message reads only "invalid input."

---

## M3 — Attendance & Leave

### FR-1.3 — Attendance intake

**Priority** Must · **Source** P1 · **Objective** OBJ 1 · **Actor** Payroll Officer

The system shall load daily time records from a file export and restrict manual encoding to exceptions.

**Behavior**

1. The user downloads a template defining the expected import columns: employee number, date, time in, time out, and optional break times.
2. The user uploads a CSV or Excel file, or a biometric device export in the agreed format (OI-04).
3. The system validates every row before committing any: it verifies that the employee number exists and is active, the date falls within the target cut-off, and the times are valid and ordered.
4. The system presents an import preview reporting rows accepted, rows rejected, and the reason for each rejection. The user confirms or cancels; a cancelled import commits nothing.
5. The system computes hours worked, late minutes, undertime minutes, night differential hours, and overtime hours from the raw times against the employee's schedule (BR-03, BR-04).
6. The user may add, edit, or delete individual attendance records for exceptions — a missed punch, an official business trip, a manual correction — with each edit audited.
7. Re-importing the same cut-off replaces rather than duplicates previously imported rows, subject to user confirmation.

**Rules** BR-03, BR-04, BR-09

**Acceptance criteria**

- AC-1.3.1 An import containing any invalid row commits nothing until the user confirms the preview.
- AC-1.3.2 Every rejected row is reported with its row number and the specific reason.
- AC-1.3.3 Re-importing a cut-off does not create duplicate attendance records.
- AC-1.3.4 At least 90% of attendance rows in the acceptance test are loaded by import rather than keyed manually.
- AC-1.3.5 Computed late, undertime, night differential, and overtime figures match a manual computation of the same raw times.

---

### FR-1.4 — Leave administration

**Priority** Must · **Source** P1 · **Objective** OBJ 1 · **Actor** Payroll Officer (file), Approver (approve)

The system shall record leave applications, maintain leave balances, and post approved leaves to the covering payroll period automatically.

**Behavior**

1. The user files a leave application specifying employee, leave type, date range, number of days, and reason.
2. The system checks the available balance for the leave type and refuses an application exceeding it unless the leave type permits a negative balance.
3. The Approver approves, returns, or cancels the application; the system updates the balance on approval.
4. On payroll computation, the system posts approved leave days falling within the cut-off: paid leave types produce a paid-leave earning; unpaid leave types produce an absence deduction (BR-11).
5. The system maintains a per-employee ledger of leave credits earned, used, and remaining, per leave type per year, and applies the carry-over rule configured for that type.
6. A leave application overlapping an existing approved leave for the same employee is refused.

**Rules** BR-10, BR-11

**Acceptance criteria**

- AC-1.4.1 An approved leave within a cut-off appears in that period's computation without separate encoding.
- AC-1.4.2 The leave balance after approval equals the prior balance less the days approved.
- AC-1.4.3 A paid leave day produces no reduction in basic pay; an unpaid leave day reduces it per BR-11.
- AC-1.4.4 Overlapping approved leave for one employee is refused.
- AC-1.4.5 Leave records and payroll deductions reconcile with no second encoding pass.

---

## M4 — Payroll Computation

### FR-2.6 — Payroll run lifecycle

**Priority** Must · **Source** P2 · **Objective** OBJ 2 · **Actor** Payroll Officer

The system shall create, compute, and recompute an entire pay period in a single operation.

**Behavior**

1. The user creates a payroll run by selecting a defined pay period (FR-0.3) and the population to include — all active employees, or a department, or a named selection.
2. The system creates one payroll line per included employee and executes the computation of FR-2.1 through FR-2.5 for each, in the order specified in BR-13.
3. The system reports the outcome: employees computed, employees skipped, and total elapsed time.
4. The user may recompute the whole run at any time while it is in `Draft` or `Returned` state; recomputation discards prior computed values and recomputes from current inputs.
5. The system prevents two concurrent runs of the same run type for the same pay period and population. Runs of different types — a regular run and a 13th-month run, for instance — may coexist for one period.
6. Every computation and recomputation is audited under FR-6.1.

**Rules** BR-13, BR-14

**Acceptance criteria**

- AC-2.6.1 One user action computes every included employee for the period; no per-employee action is required.
- AC-2.6.2 Recomputing an unchanged run produces identical figures.
- AC-2.6.3 A second run of the same run type for the same period and population is refused; a run of a different run type for that period is permitted.
- AC-2.6.4 Computation of a full period completes faster than the client's baseline Excel preparation time for the same period.
- AC-2.6.5 A run cannot be computed for a period whose payroll has been finalized (FR-4.5).

---

### FR-2.1 — Basic pay computation

**Priority** Must · **Source** P2 · **Objective** OBJ 2 · **Actor** System

The system shall derive each employee's regular earnings for the period from the attendance record and the compensation profile, with no formula entered by the user.

**Behavior**

1. For a monthly-paid employee, the system computes basic pay for the period per BR-05 and deducts absences, tardiness, and undertime per BR-11.
2. For a daily-paid employee, the system computes basic pay as days present × daily rate.
3. For an hourly-paid employee, the system computes basic pay as hours worked × hourly rate.
4. Paid leave days are included in basic pay per BR-11.
5. The system records the components of the computation on the payroll line so the figure can be explained without recomputation.

**Rules** BR-01, BR-02, BR-05, BR-11

**Acceptance criteria**

- AC-2.1.1 Basic pay for each pay basis matches an independently verified manual computation.
- AC-2.1.2 The computation formula exists once in the system; no per-period or per-employee formula copy exists.
- AC-2.1.3 Every computed basic pay figure can be traced on screen to the days, hours, and rate that produced it.

---

### FR-2.2 — Additional pay computation

**Priority** Must · **Source** P2 · **Objective** OBJ 2 · **Actor** System

The system shall compute overtime, night differential, holiday and rest-day premiums, and allowances using configurable multipliers.

**Behavior**

1. The system classifies each worked day using the holiday calendar of FR-0.3: ordinary day, rest day, special non-working day, regular holiday, or a combination.
2. The system applies the premium multiplier configured for that classification to the hours worked, and the overtime multiplier to hours beyond the standard workday (BR-15, BR-16).
3. The system computes night differential on hours falling within the configured night period (BR-17).
4. The system adds recurring allowances from the compensation profile and any one-time allowances encoded for the period, each carrying the taxability flag of its earning type.
5. Multipliers are configuration values, editable by an Administrator, defaulting to the rates in R-02.
6. The system sums all earnings into gross pay.

**Rules** BR-15, BR-16, BR-17, BR-18

**Acceptance criteria**

- AC-2.2.1 A change to a premium multiplier takes effect on the next computation without code modification.
- AC-2.2.2 Work on each day classification is paid at the configured multiplier for that classification.
- AC-2.2.3 Night differential is computed only on hours within the configured night period.
- AC-2.2.4 Gross pay equals the sum of all earning lines on the payroll line, to the centavo.

---

### FR-2.3 — Statutory deduction reference tables

**Priority** Must · **Source** P2 · **Objective** OBJ 2 · **Actor** Administrator (maintain); System (apply)

The system shall hold SSS, PhilHealth, Pag-IBIG, and BIR withholding tax schedules as effectivity-dated reference tables maintainable without code changes, and apply the table in force for the period being computed.

**Behavior**

1. The Administrator maintains, for each agency, a set of dated schedules: SSS as salary brackets with employee and employer shares; PhilHealth as a premium rate with salary floor and ceiling; Pag-IBIG as contribution rates with a compensation cap; withholding tax as compensation-range brackets with a base tax and marginal rate, by pay frequency.
2. Each schedule carries an effectivity date and, optionally, an end date. Overlapping effectivity ranges for one agency are refused.
3. On computation, the system selects the schedule whose effectivity range contains the pay period's pay date and applies it (BR-14).
4. The system computes the taxable compensation base per BR-19 before applying the withholding tax table.
5. A finalized payroll run permanently retains the schedule version it was computed with (C-04).
6. The system shows, for each statutory deduction on a payroll line, which schedule version produced it.

**Rules** BR-14, BR-19, BR-20

**Acceptance criteria**

- AC-2.3.1 A new contribution schedule can be entered and take effect with no source code modification.
- AC-2.3.2 Overlapping effectivity ranges for one agency are refused at entry.
- AC-2.3.3 A payroll run computed before a schedule change continues to display its original figures after the change.
- AC-2.3.4 Each statutory deduction on a payroll line names the schedule version applied.
- AC-2.3.5 Computed statutory deductions match a manual computation using the same published schedule.

---

### FR-2.4 — Adjustments and other deductions

**Priority** Must · **Source** P2 · **Objective** OBJ 2 · **Actor** Payroll Officer

The system shall apply non-statutory deductions and adjustments as discrete, auditable line items on the payroll line.

**Behavior**

1. The system applies standing deductions and loan amortizations from the compensation profile (FR-1.2) automatically.
2. The user may add one-time deductions or adjustments to a specific payroll line — a cash advance, a reimbursement, a retroactive correction — each with a deduction or earning type, an amount, and a remark.
3. The system computes tardiness and undertime deductions from attendance per BR-11.
4. Retroactive adjustments arising from a rate change effective in a closed period are encoded as adjustment lines in the current open period; they never alter a finalized run (BR-24).
5. Every adjustment carries the user who added it and the timestamp, and is removable only while the run is unapproved.
6. The system totals all deductions.

**Rules** BR-11, BR-22, BR-23, BR-24

**Acceptance criteria**

- AC-2.4.1 Every adjustment is an individually visible line with an amount, a type, and a remark — never an unexplained change to a total.
- AC-2.4.2 An adjustment on an approved or finalized run is refused.
- AC-2.4.3 Loan amortizations are applied automatically and reduce the outstanding balance by exactly the amount deducted.
- AC-2.4.4 Total deductions equal the sum of all deduction lines on the payroll line, to the centavo.

---

### FR-2.5 — Net pay determination

**Priority** Must · **Source** P2 · **Objective** OBJ 2 · **Actor** System

The system shall compute net pay for every employee in the run as gross earnings less total deductions. Net pay shall never be an entered value.

**Behavior**

1. The system computes net pay per BR-13 after all earnings and deductions are resolved.
2. The system offers no field, import, or override permitting a user to type a net pay figure.
3. The system flags a payroll line whose net pay is zero, negative, or below the configured floor for review under FR-4.1 (BR-25).
4. The system computes and displays the run totals: total gross, total by deduction type, and total net.

**Rules** BR-01, BR-13, BR-25

**Acceptance criteria**

- AC-2.5.1 No interface path exists by which net pay can be hand-entered.
- AC-2.5.2 For every payroll line, net pay = gross pay − total deductions, to the centavo.
- AC-2.5.3 Run totals equal the sum of the corresponding payroll line values, to the centavo.
- AC-2.5.4 A zero, negative, or below-floor net pay is flagged and blocks finalization until resolved.

---

## M5 — Validation & Approval

### FR-4.1 — Pre-finalization validation report

**Priority** Must · **Source** P4 · **Objective** OBJ 4 · **Actor** Payroll Officer, Approver

The system shall check every computed run against defined exception rules and present the exceptions as a report, so that review is directed rather than exhaustive.

**Exception rules**

| Code | Condition | Severity |
|---|---|---|
| EX-01 | Employee included in the run has no attendance record for the cut-off | Blocking |
| EX-02 | Employee has no active compensation profile or basic rate | Blocking |
| EX-03 | Net pay is zero, negative, or below the configured floor | Blocking |
| EX-04 | Total deductions exceed gross pay | Blocking |
| EX-05 | No statutory schedule in force for the pay date | Blocking |
| EX-06 | Employee is missing a government identification number required by a report | Warning |
| EX-07 | Gross pay differs from the prior period by more than a configured percentage | Warning |
| EX-08 | Overtime hours exceed a configured threshold for the period | Warning |
| EX-09 | Employee active in the prior run is absent from this run | Warning |
| EX-10 | Attendance record exists for a date outside the cut-off | Warning |

**Behavior**

1. The system runs all exception rules automatically at the end of every computation and on demand.
2. The report groups exceptions by severity and names the affected employee, the rule, and the values that triggered it.
3. Each exception links directly to the record needing correction.
4. A run with any unresolved **blocking** exception cannot be submitted for review.
5. **Warning** exceptions do not block submission but must be individually acknowledged, and the acknowledgment is audited.
6. Thresholds for EX-03, EX-07, and EX-08 are configurable.

**Rules** BR-25

**Acceptance criteria**

- AC-4.1.1 Every rule EX-01 through EX-10 is evaluated for every payroll line on every computation.
- AC-4.1.2 A run with an unresolved blocking exception cannot be submitted for review.
- AC-4.1.3 Each exception identifies the employee, the rule, and the triggering values, and links to the record to correct.
- AC-4.1.4 No more than 15% of the payroll lines in a computed run are flagged for manual review, measured over the parallel-run set of NFR-2.7 (at least 30 employees across three payroll periods). The baseline is the 100% of lines inspected under the current process.

---

### FR-4.2 — Payroll register review

**Priority** Must · **Source** P4 · **Objective** OBJ 4 · **Actor** Payroll Officer, Approver, Viewer

The system shall present the whole run in one authoritative on-screen view.

**Behavior**

1. The register lists every payroll line with employee number, name, department, days and hours, gross pay, each deduction category, and net pay, with column totals.
2. The user may sort by any column and filter by department, employment status, exception status, and net pay range.
3. Selecting a line opens the full computation breakdown for that employee — every earning line, every deduction line, and the rate and schedule versions applied.
4. The user may compare the run side by side with the immediately preceding period, showing the difference per employee.
5. The register is exportable to PDF and Excel.

**Acceptance criteria**

- AC-4.2.1 All employees and all computed columns for the period are visible in one view without switching context.
- AC-4.2.2 Column totals equal the run totals from FR-2.5.
- AC-4.2.3 Any line expands to a breakdown accounting for every centavo of its gross, deductions, and net.
- AC-4.2.4 The period-over-period comparison correctly identifies employees whose net pay changed.

---

### FR-4.3 — Targeted correction and recomputation

**Priority** Must · **Source** P4 · **Objective** OBJ 4 · **Actor** Payroll Officer

The system shall allow an input to be corrected and only the affected employees recomputed.

**Behavior**

1. From an exception or a register line, the user navigates to the source record — attendance, leave, compensation profile, or adjustment — and corrects it.
2. The system identifies which payroll lines depend on the corrected record and marks them stale.
3. The user recomputes the stale lines only; unaffected lines retain their computed values and are not recomputed.
4. The system re-runs the exception rules of FR-4.1 after any recomputation.
5. Targeted recomputation is permitted only while the run is in `Draft` or `Returned` state.
6. Each correction and targeted recomputation is audited with before and after values.

**Rules** BR-13

**Acceptance criteria**

- AC-4.3.1 Correcting one employee's input requires recomputing only that employee, not the run.
- AC-4.3.2 Payroll lines not dependent on the corrected record are bit-for-bit unchanged after a targeted recomputation.
- AC-4.3.3 A stale payroll line is visibly marked and blocks submission until recomputed.
- AC-4.3.4 The exception report is refreshed after every recomputation.

---

### FR-4.4 — Approval workflow

**Priority** Must · **Source** P4 · **Objective** OBJ 4 · **Actor** Payroll Officer (submit), Approver (approve, return, finalize)

The system shall move each payroll run through explicit states, recording the acting user and timestamp at every transition.

**State model**

```
  Draft ──submit──▶ For Review ──approve──▶ Approved ──finalize──▶ Finalized
    ▲                    │                      │                        │
    │                 return                 return             (locked — FR-4.5)
    │                    ▼                      ▼
    └──────────────── Returned ◀────────────────┘

  Draft ──cancel──▶ Cancelled
```

**One return rule.** Both return paths land in `Returned`, and `Returned` reopens to `Draft`. The state exists so that a run sent back is visibly a returned run rather than an ordinary draft — it carries its return reason, and the register and run list can show why it came back. A run in `Draft` that was never submitted is a different thing from a run a reviewer rejected, and the two are not merged.

| From | Action | To | Permitted role | Precondition |
|---|---|---|---|---|
| Draft | Submit | For Review | Payroll Officer | Computed; no blocking exception; no stale line |
| For Review | Approve | Approved | Approver | Reviewer is not the submitter |
| For Review | Return | Returned → Draft | Approver | Reason required |
| Approved | Return | Returned → Draft | Approver | Reason required; not yet finalized |
| Approved | Finalize | Finalized | Approver | — |
| Draft | Cancel | Cancelled | Payroll Officer | Not previously approved |

**Behavior**

1. Every transition records the acting user, timestamp, and — for a return — the reason, all visible on the run's history.
2. Editing of inputs and adjustments is permitted only in `Draft` and `Returned`.
3. Payslips (FR-3.1) may be generated only from a `Finalized` run.
4. A returned run carries its return reason to the Payroll Officer. Every return — from `For Review` or from `Approved` — transitions the run to `Returned`; `Returned` then reopens to `Draft` for correction. There is no direct `For Review → Draft` transition.
5. A return from `Approved` clears `approved_by` and `approved_at` on the run, so that no returned run reads as approved by anyone. The earlier approval is not lost: it remains permanently in the run's transition history (data model `RUN_TRANSITION`), which is where approval history belongs.
6. Cancellation of a `Draft` run is performed by the Payroll Officer through UC-17 A2, requires confirmation under NFR-6.3, and is audited under FR-6.1. A cancelled run is terminal — it cannot be reopened, computed, or submitted, and it does not block the creation of a new run for the same period, population, and run type.
7. The run's current state is displayed prominently wherever the run appears.

**Rules** BR-28, BR-29

**Acceptance criteria**

- AC-4.4.1 Only the transitions in the table above are possible; any other is refused.
- AC-4.4.2 The user who submitted a run cannot approve it.
- AC-4.4.3 Every transition is recorded with user and timestamp, and a return additionally with a reason.
- AC-4.4.4 A run not in `Finalized` state cannot produce payslips.
- AC-4.4.5 The full transition history of any run is viewable from the run.
- AC-4.4.6 A run returned from `Approved` carries no approver on the run record, while its earlier approval remains visible in the transition history.
- AC-4.4.7 A cancelled run cannot be edited, computed, submitted, or reopened, and does not prevent a new run for the same period, population, and run type.

---

### FR-4.5 — Period locking

**Priority** Must · **Source** P4 · **Objective** OBJ 4 · **Actor** System

The system shall make a finalized payroll run read-only; changes affecting a finalized period shall be recorded as adjustments in an open period.

**Behavior**

1. On finalization, the run and all its payroll lines, earning lines, and deduction lines become immutable, and the run is queued for ledger anchoring (FR-6.3, BR-36).
2. The system offers no edit, delete, or recompute function on a finalized run.
3. A finalized run may be reversed only by an Approver, which creates an explicit reversal record with a reason, restores the period to `Draft`, and leaves the reversal permanently in the audit trail. A run whose payslips have been issued and whose pay date has passed may not be reversed (BR-24).
4. Corrections to a finalized period that cannot be reversed are encoded as retroactive adjustments in the current open period, referencing the affected period.
5. Attendance, leave, and compensation-profile records for dates inside a finalized period may still be corrected for accuracy, but do not alter the finalized run's figures.

**Rules** BR-24, BR-27

**Acceptance criteria**

- AC-4.5.1 No function within the system modifies a finalized payroll line.
- AC-4.5.2 A reversal is recorded with the acting user, timestamp, and reason, and is permanently visible, and is queued for anchoring (FR-6.3).
- AC-4.5.5 Finalization queues exactly one integrity anchor for the run (FR-6.3, AC-6.3.1).
- AC-4.5.3 Reversal is refused once payslips are issued and the pay date has passed.
- AC-4.5.4 A retroactive adjustment names the period it corrects.

---

## M6 — Payslip

### FR-3.1 — Automatic payslip generation

**Priority** Must · **Source** P3 · **Objective** OBJ 3 · **Actor** Payroll Officer

The system shall generate a payslip for every employee in a finalized run directly from stored payroll data, with no figure re-entered.

**Behavior**

1. The user triggers generation for a finalized run; the system produces one payslip per payroll line.
2. Every value on the payslip is read from the payroll line. The system offers no field permitting a payslip figure to be typed or edited.
3. Generation is repeatable and idempotent: regenerating produces identical documents.
4. The system records the generation event, the count produced, and the user, under FR-6.1.

**Acceptance criteria**

- AC-3.1.1 Every figure on a payslip equals the corresponding figure on the payroll line, to the centavo.
- AC-3.1.2 No interface exists for editing a payslip value.
- AC-3.1.3 A payslip cannot be generated from a run that is not finalized.
- AC-3.1.4 Regenerating a period's payslips produces documents identical to the first generation.

---

### FR-3.2 — Payslip content and layout

**Priority** Must · **Source** P3 · **Objective** OBJ 3 · **Actor** System

The system shall render every payslip in one standard layout disclosing all earnings and all deductions.

**Required content**

| Section | Content |
|---|---|
| Header | Employer name, address, logo; the words "Payslip"; pay period covered and pay date |
| Employee | Employee number, full name, department, position, employment status |
| Earnings | Basic pay with days or hours; each additional pay item (overtime, night differential, holiday premium) with its hours and rate; each allowance by name; **gross pay** total |
| Deductions | Each statutory deduction by agency name; tardiness and undertime with minutes; each loan or other deduction by name with remaining balance where applicable; **total deductions** |
| Net | **Net pay** in figures |
| Footer | Generation timestamp and a notice that the payslip is system-generated |

**Behavior**

1. Every earning and deduction appears as a named line with its amount; no item is folded into an "others" total.
2. Zero-amount lines are suppressed except where disclosure is required.
3. The layout is identical for every employee in every period.
4. Year-to-date totals are included where configured (`Could` priority).

**Rules** BR-01, BR-21

**Acceptance criteria**

- AC-3.2.1 Every deduction applied is disclosed on the payslip by name and amount.
- AC-3.2.2 Gross less total deductions equals the net pay printed, to the centavo.
- AC-3.2.3 Payslips from different employees and different periods are identical in structure.
- AC-3.2.4 The payslip fits its page without truncation for an employee carrying the maximum configured number of earning and deduction lines.

---

### FR-3.3 — Batch generation and export

**Priority** Must · **Source** P3 · **Objective** OBJ 3 · **Actor** Payroll Officer

The system shall generate, export, and print all payslips for a period in one action.

**Behavior**

1. The user generates the full set for a finalized run in one action, optionally filtered by department.
2. The system exports the set as a single multi-page PDF for printing, or as individual PDF files named by employee number and period.
3. The system reports progress and completion, including the count produced.
4. The batch is printable directly from the system.

**Rules** NFR-3.5

**Acceptance criteria**

- AC-3.3.1 One user action produces payslips for every employee in the run.
- AC-3.3.2 The exported PDF contains exactly one payslip per payroll line, with none missing and none duplicated.
- AC-3.3.3 Individual files are named uniquely and identifiably.
- AC-3.3.4 The complete set for one period is produced within five minutes of the request (NFR-3.5).

---

### FR-3.4 — Payslip retrieval and reprint

**Priority** Must · **Source** P3 · **Objective** OBJ 3 · **Actor** Payroll Officer, Approver, Administrator; Viewer (read)

The system shall retrieve and reissue the payslip for any past period.

**Behavior**

1. The user searches by employee, by period, or by both.
2. The system regenerates the payslip from the stored payroll line of that period — it does not retrieve a saved file that could diverge from the record.
3. The reissued payslip is byte-identical in content to the original, including the rate and schedule versions in force at the time.
4. The system marks a reprint as such and records who reprinted it and when.

**Rules** C-04

**Acceptance criteria**

- AC-3.4.1 A payslip for any past finalized period can be reissued without re-entering any figure.
- AC-3.4.2 A reissued payslip matches the original in every value.
- AC-3.4.3 A reprint is recorded in the audit trail with user and timestamp.
- AC-3.4.4 Retrieval completes within one minute (NFR-5.5).

---

## M7 — Records & Reporting

### FR-5.1 — Payroll records storage

**Priority** Must · **Source** P5 · **Objective** OBJ 5 · **Actor** System

The system shall persist every payroll run, payroll line, payslip basis, and supporting input, indexed for retrieval.

**Behavior**

1. Recording a payroll transaction and filing it are the same action; no separate filing step exists.
2. Every payroll line retains the inputs that produced it: attendance summary, leave posted, rate version, and statutory schedule version.
3. Records are indexed by pay period, employee, and department.
4. No payroll record is deleted by any system function; superseded records are marked, never removed.
5. Records are retained for the configured retention period (OI-10) and remain retrievable throughout.

**Rules** BR-26, BR-27, C-04

**Acceptance criteria**

- AC-5.1.1 Every finalized run is retrievable in full, with all inputs that produced it.
- AC-5.1.2 The system offers no function that deletes a payroll record.
- AC-5.1.3 A payroll line retrieved after a rate or schedule change displays its original figures.

---

### FR-5.2 — Search and retrieval

**Priority** Must · **Source** P5 · **Objective** OBJ 5 · **Actor** All roles per FR-6.2

The system shall locate historical payroll records by query rather than by manual file search.

**Behavior**

1. The user searches payroll records by employee name or number, pay period or date range, department, and run state.
2. Results list matching runs and payroll lines and open directly to the record.
3. Partial-match search on employee name and number is supported.
4. Result sets are exportable to PDF and Excel.

**Acceptance criteria**

- AC-5.2.1 Any historical payroll record is located by at least one of the supported criteria.
- AC-5.2.2 Search returns results within one minute for the full retained data set (NFR-5.5).
- AC-5.2.3 A search returning no result says so plainly and states the criteria applied.

---

### FR-5.3 — Report generation

**Priority** Must · **Source** P5 · **Objective** OBJ 5 · **Actor** All roles per FR-6.2

The system shall generate payroll, remittance, and transmittal reports from stored data on demand.

**Report catalogue**

| Report | Content | Priority |
|---|---|---|
| Payroll register | All payroll lines for a period with earnings, deductions, and net, with totals | Must |
| Payroll summary | Totals by department and by earning and deduction category | Must |
| SSS remittance | Employee and employer contributions per employee for the month, with SSS number | Must |
| PhilHealth remittance | Employee and employer premiums per employee for the month, with PhilHealth number | Must |
| Pag-IBIG remittance | Employee and employer contributions per employee for the month, with Pag-IBIG MID | Must |
| Withholding tax report | Taxable compensation and tax withheld per employee for the period | Must |
| Bank transmittal listing | Employee name, account number, and net pay for the period, in the client's bank format | Must |
| 13th month pay report | Basic salary earned per employee for the year and the 13th month pay due (BR-21) | Must |
| Leave ledger | Credits earned, used, and remaining per employee per leave type | Should |
| Loan ledger | Principal, amortization, amounts deducted, and outstanding balance per employee | Should |
| Payroll cost comparison | Period-over-period movement by department | Could |

**Behavior**

1. The user selects a report, sets its parameters — period or date range, department, employee — and generates it.
2. Every report is exportable to PDF and to Excel.
3. Reports draw exclusively from stored payroll data; no report requires manual compilation or re-entry.
4. Each report displays its parameters, generation timestamp, and the user who generated it.
5. Remittance reports use the employer identification numbers from FR-0.3 without re-entry.
6. A report over an unfinalized run is watermarked as provisional.

**Rules** BR-20, BR-21

**Acceptance criteria**

- AC-5.3.1 Every report in the catalogue marked Must is generated without manual compilation.
- AC-5.3.2 Report totals reconcile to the payroll register of the same period, to the centavo.
- AC-5.3.3 Every report exports to both PDF and Excel with content intact.
- AC-5.3.4 A remittance report includes every employee covered by that agency for the period, and no one else.
- AC-5.3.5 A report over an unfinalized run is visibly marked provisional.

---

# 5. Non-functional requirements

Carried forward from the matrix with their identifiers unchanged, and made verifiable.

| ID | Requirement | Verification method |
|---|---|---|
| **NFR-2.7** | Computational accuracy — system output shall agree exactly with an independently verified manual computation. | Parallel run: ≥ 30 employees across 3 payroll periods, covering regular, overtime, leave-affected, and loan-deducted cases. Pass = 100% agreement to the centavo. |
| **NFR-3.5** | Payslip issuance turnaround — a complete payslip set for one period shall be produced within five minutes of finalization. | Timed test on the full employee population. Baseline: client's current typing time per period. |
| **NFR-5.4** | Backup and restore — the payroll database shall be backed up on a defined schedule with a documented restore procedure. | Scheduled backup observed to run; a full restore to a test environment performed and verified against the source. |
| **NFR-5.5** | Retrieval performance — any past payslip, register, or report shall be located and displayed within one minute. | Timed test against the full retained data set. Baseline: client's current manual look-up time. |
| **NFR-6.3** | Irreversible actions shall require confirmation and, where feasible, offer a reversal path. | Inspection of every destructive action (finalize, reverse, delete, deactivate, import overwrite); usability testing of each. |
| **NFR-6.4** | Database-level integrity — referential integrity, unique keys, and non-null constraints shall be enforced on payroll-critical fields. | Schema inspection plus negative testing: direct insertion of invalid data is rejected by the database, not only by the application. |
| **NFR-6.5** | Security — passwords stored hashed with a salted algorithm; session timeout enforced; individual non-shared accounts. | Inspection of stored credentials (no plaintext or reversible encryption); timeout test; account audit confirming no shared account. |
| **NFR-6.6** | System quality evaluation using ISO/IEC 25010 — functional suitability, performance efficiency, usability, reliability, and security. | Survey of the client's payroll personnel, five-point Likert. Target weighted mean ≥ 4.20 (Very Satisfactory), reported per characteristic. |

### Additional quality expectations

These four are **not separately gated**. They inform design and are verified by inspection during user acceptance testing rather than by a numbered acceptance criterion, and they are excluded from the requirement counts and from the traceability table in §9 for that reason. They are stated because a reader of Chapter III needs them, not because Chapter IV reports against them individually.

| ID | Requirement |
|---|---|
| NFR-7.1 | Concurrency — the system shall support at least four simultaneous users without data corruption or lost updates. |
| NFR-7.2 | Usability — a Payroll Officer shall complete a full payroll cycle unaided after one training session, measured during user acceptance testing. |
| NFR-7.3 | Error messages shall state what went wrong and what the user should do; no message shall expose a technical stack trace. |
| NFR-7.4 | Availability — the system shall be operable during the client's working hours, with maintenance scheduled outside payroll cut-off periods. |

---

# 6. External interface requirements

## 6.1 User interfaces

| ID | Requirement |
|---|---|
| UI-01 | Every screen shall display the signed-in user, their role, and the active payroll period context. |
| UI-02 | Payroll run state shall be shown as a visible status indicator wherever a run appears. |
| UI-03 | Monetary values shall be displayed to two decimal places with a thousands separator and right-aligned in tables. |
| UI-04 | Destructive actions shall be visually distinguished from routine actions and require confirmation (NFR-6.3). |
| UI-05 | Data entry screens shall show validation messages inline, adjacent to the field in error (FR-1.5). |
| UI-06 | List screens shall support keyboard navigation and shall not require a mouse for data entry. |

## 6.2 Hardware interfaces

| ID | Requirement |
|---|---|
| HW-01 | The system shall print payslips and reports to any printer available to the workstation operating system; no specific printer model is required. |
| HW-02 | The system shall not communicate directly with a biometric or timekeeping device. It consumes an export file produced by that device (FR-1.3). |

## 6.3 Software interfaces

| ID | Requirement |
|---|---|
| SW-01 | Attendance import shall accept CSV and Microsoft Excel (`.xlsx`) files matching the published template. |
| SW-02 | All reports and payslips shall export to PDF, and all tabular reports additionally to `.xlsx`. |
| SW-03 | The bank transmittal listing shall be exportable in the layout required by the client's bank (OI-06). |
| SW-04 | The system shall require no internet connection for any function (C-03). |

## 6.4 Communications interfaces

| ID | Requirement |
|---|---|
| CM-01 | Communication between client and server shall be encrypted. The system is deployed over a local network (§2.4, OI-08), so this applies to every session: HTTPS with a certificate issued locally, with no dependency on a public certificate authority and no internet access required (C-03, SW-04). |
| CM-02 | Emailed distribution of payslips is out of scope for this release. |

---

# 7. Business and computation rules

These rules are referenced by the requirements above. They are stated as rules, not as code, and are the single point of definition for each behavior.

## Precision and rates

| ID | Rule |
|---|---|
| **BR-01** | All monetary values are carried and stored to two decimal places. Rounding is half-up, applied at each line item, and totals are the sum of rounded line items — never the rounding of an unrounded sum. Binary floating-point types are not used for currency (C-02). |
| **BR-02** | Rate derivation from the configured day factor: `daily rate = (monthly rate × 12) ÷ day factor`; `hourly rate = daily rate ÷ standard hours per day`. The day factor and standard hours per day are configuration values (FR-0.3, OI-03). |
| **BR-03** | Hours worked are computed from time in and time out less unpaid break time, against the employee's assigned schedule. |
| **BR-04** | Late minutes are the excess of actual time in over scheduled time in; undertime minutes are the excess of scheduled time out over actual time out. Neither offsets the other, and neither offsets overtime. |
| **BR-05** | For a monthly-paid employee under a semi-monthly frequency, basic pay for a period is half the monthly rate before absence, tardiness, and undertime deductions, unless the client's practice differs (OI-02). |

## Employee data

| ID | Rule |
|---|---|
| **BR-06** | Employee number is unique across all employees, active and inactive, and is never reused. |
| **BR-07** | An employee record with any dependent payroll, attendance, or leave record cannot be deleted. Deactivation is the only removal. |
| **BR-08** | A compensation change is recorded as a new dated entry. The rate applied to a payroll run is the entry in force on the period's cut-off end date. |
| **BR-09** | Attendance dates outside the cut-off of the run being computed are excluded from that run's computation. |

## Leave

| ID | Rule |
|---|---|
| **BR-10** | A leave application cannot be approved for more days than the employee's available balance for that leave type, unless the leave type is configured to permit a negative balance. |
| **BR-11** | Absence, tardiness, and undertime deductions: `absence = daily rate × unpaid days absent`; `tardiness = (hourly rate ÷ 60) × late minutes`; `undertime = (hourly rate ÷ 60) × undertime minutes`. Approved paid leave days are treated as days present and produce no deduction. |

## Earnings

| ID | Rule |
|---|---|
| **BR-12** | Every earning type carries an explicit taxable or non-taxable flag. Non-taxable earnings are excluded from the withholding tax base per BR-19. |
| **BR-13** | Computation order for every payroll line: (1) basic pay; (2) additional pay and allowances; (3) gross pay; (4) absence, tardiness, and undertime; (5) statutory deductions in the order SSS, PhilHealth, Pag-IBIG; (6) taxable compensation and withholding tax; (7) loans and other deductions; (8) total deductions; (9) net pay. The order is fixed because withholding tax depends on the mandatory contributions computed before it. |
| **BR-15** | Day classification determines the premium multiplier: ordinary day, rest day, special non-working day, regular holiday, and the combinations of rest day with each holiday type. Multipliers are configuration values defaulting to the rates in R-02. |
| **BR-16** | Overtime applies to hours worked beyond the standard hours per day, at the overtime multiplier configured for that day's classification. |
| **BR-17** | Night differential applies to hours worked within the configured night period (by default 10:00 p.m. to 6:00 a.m.) at the configured night differential rate, and is computed in addition to any applicable premium or overtime. |
| **BR-18** | Gross pay is the sum of all earning lines on the payroll line. |

## Statutory deductions

| ID | Rule |
|---|---|
| **BR-14** | The statutory schedule applied to a run is the one whose effectivity range contains the pay period's pay date. Effectivity ranges for one agency may not overlap. A finalized run permanently retains the schedule version it was computed with. |
| **BR-19** | Taxable compensation = gross pay − non-taxable earnings (BR-12) − mandatory employee contributions to SSS, PhilHealth, and Pag-IBIG − de minimis benefits within their statutory limits. Withholding tax is computed on this base using the bracket schedule for the applicable pay frequency (R-06). |
| **BR-20** | Employer shares of statutory contributions are computed and stored on the payroll line for remittance reporting, but do not affect net pay. |
| **BR-21** | 13th month pay = total basic salary earned during the calendar year ÷ 12. Only earning types flagged as included in the 13th month base participate (R-07, FR-0.4). |

## Deductions and adjustments

| ID | Rule |
|---|---|
| **BR-22** | A recurring deduction on the compensation profile is applied to every payroll run within its effectivity range without re-encoding. |
| **BR-23** | A loan amortization is deducted each period until the outstanding balance reaches zero. The final deduction is the lesser of the amortization amount and the remaining balance; over-deduction is not permitted. |
| **BR-24** | A finalized payroll run is never modified. A correction to a finalized period is either a reversal — permitted only before payslips are issued and the pay date has passed — or a retroactive adjustment recorded in the current open period and referencing the corrected period. |
| **BR-25** | Net pay below the configured floor, zero, or negative is a blocking exception (EX-03). Non-statutory deductions flagged as participating in the floor check may be reduced or deferred to satisfy the floor; statutory deductions may not. |

## Records, audit, and access

| ID | Rule |
|---|---|
| **BR-26** | Every state-changing action produces exactly one audit entry, written in the same transaction as the action. |
| **BR-27** | Audit entries and payroll records are append-only. No system function deletes either. |
| **BR-28** | The user who submits a payroll run for review may not approve it. |
| **BR-29** | A permission absent from a role is refused at the point of execution, not merely hidden in the interface. |
| **BR-30** | Passwords are stored only as a salted hash. No function displays or exports a password. |
| **BR-31** | A configurable number of consecutive failed sign-in attempts locks the account until an Administrator releases it. |
| **BR-32** | A session idle beyond the configured timeout is terminated and requires re-authentication. |
| **BR-33** | A record referenced by any other record may be deactivated but not deleted. |
| **BR-34** | Pay periods within a payroll year neither overlap nor leave a gap. |
| **BR-35** | Each audit entry stores the hash of its own content and the hash of the entry preceding it. The chain is unbroken from the first entry onward. |
| **BR-36** | A record's ledger anchor is written only after the record has become immutable — a payroll run at finalization, a reversal at creation, an audit segment once closed. Nothing is anchored while it can still legitimately change. |

---

# 8. Data requirements

## DR-1.6 — Normalized relational database

**Priority** Must · **Source** P1 · **Objective** OBJ 1

The system shall store data in a normalized relational structure holding one authoritative record per employee, referenced by the attendance, leave, payroll, and payslip modules. No module shall maintain its own copy of employee data.

**Acceptance criteria**

- AC-1.6.1 Employee identity is stored in exactly one table; all other tables reference it by key.
- AC-1.6.2 No employee attribute is duplicated in a second table in a way that permits divergence.
- AC-1.6.3 Referential integrity is enforced by the database (NFR-6.4).

## 8.1 Entity inventory

| # | Entity | Purpose | Key relationships |
|---|---|---|---|
| 1 | `Employee` | Authoritative employee identity and personal data | Referenced by nearly all entities |
| 2 | `EmploymentDetail` | Date hired, status, separation, department, position | → Employee, Department, Position |
| 3 | `Department` | Organizational unit | Reference list |
| 4 | `Position` | Job title | Reference list |
| 5 | `CompensationProfile` | Dated pay basis and basic rate | → Employee (dated, BR-08) |
| 6 | `RecurringEarning` | Standing allowance | → Employee, EarningType |
| 7 | `RecurringDeduction` | Standing non-loan deduction | → Employee, DeductionType |
| 8 | `LoanAccount` | Principal, amortization, term, balance | → Employee |
| 9 | `LoanAmortization` | Per-period deduction against a loan | → LoanAccount, PayrollLine |
| 10 | `WorkSchedule` | Standard hours, rest days | → Employee |
| 11 | `AttendanceRecord` | One employee-day of time in and out and derived hours | → Employee |
| 12 | `LeaveType` | Leave category with accrual and pay rules | Reference list |
| 13 | `LeaveApplication` | Filed leave with state and approver | → Employee, LeaveType |
| 14 | `LeaveBalance` | Credits earned, used, remaining, per year | → Employee, LeaveType |
| 15 | `Holiday` | Dated holiday with classification | Reference list |
| 16 | `PayrollPeriod` | Cut-off start, cut-off end, pay date, frequency | Reference calendar |
| 17 | `PayrollRun` | One computation of one period, with state | → PayrollPeriod |
| 18 | `PayrollLine` | One employee's computed result within a run | → PayrollRun, Employee |
| 19 | `EarningLine` | One named earning on a payroll line | → PayrollLine, EarningType |
| 20 | `DeductionLine` | One named deduction on a payroll line | → PayrollLine, DeductionType |
| 21 | `StatutorySchedule` | Dated SSS, PhilHealth, Pag-IBIG, or tax table with its brackets | Referenced by PayrollLine for reproducibility |
| 22 | `RunTransition` | One state change of a run, with user, timestamp, reason | → PayrollRun, User |
| 23 | `User` / `Role` | Account and its single role | → Employee (optional) |
| 24 | `AuditLog` | Append-only record of every state-changing action | → User |

## 8.2 Data retention and integrity

| ID | Requirement |
|---|---|
| DR-2.1 | Payroll records shall be retained for the period required by the client's policy and applicable regulation (OI-10), and shall remain retrievable and reproducible throughout. |
| DR-2.2 | A payroll line shall reference the compensation and statutory schedule versions used to compute it, so the run remains reproducible after those versions are superseded (C-04). |
| DR-2.3 | Every monetary column shall use a decimal type of sufficient precision for BR-01. |
| DR-2.4 | Deletion shall be implemented as deactivation for every entity referenced by a payroll record (BR-33). |

These four **are** gated and appear in the traceability table in §9 alongside DR-1.6, bringing the data requirements to five. Each is verifiable by schema inspection or by an existing acceptance criterion, and each is cited by the data model: DR-2.1 drives `SYSTEM_CONFIG.RECORD_RETENTION_YEARS`, DR-2.2 is realized by `PAYROLL_LINE.compensation_profile_id` and `DEDUCTION_LINE.statutory_schedule_id`, DR-2.3 by the `DECIMAL(13,2)` convention, and DR-2.4 by the `is_active` soft-delete rule.

---

# 9. Requirements traceability

**Table 8.** *Functional requirement to problem, objective, module, and verification*

| Req | Title | Problem | Obj. | Module | Priority | Verified by |
|---|---|:---:|:---:|:---:|:---:|---|
| ⊕ FR-0.1 | User authentication | ⊕ | 6 | M1 | Must | AC-0.1.1–4 |
| ⊕ FR-0.2 | User account management | ⊕ | 6 | M1 | Must | AC-0.2.1–4 |
| ⊕ FR-0.3 | Organization profile and payroll calendar | ⊕ | 6 | M1 | Must | AC-0.3.1–4 |
| ⊕ FR-0.4 | Reference data maintenance | ⊕ | 6 | M1 | Must | AC-0.4.1–3 |
| FR-1.1 | Employee master file | P1 | 1 | M2 | Must | AC-1.1.1–5 |
| FR-1.2 | Compensation profile | P1 | 1 | M2 | Must | AC-1.2.1–5 |
| FR-1.3 | Attendance intake | P1 | 1 | M3 | Must | AC-1.3.1–5 |
| FR-1.4 | Leave administration | P1 | 1 | M3 | Must | AC-1.4.1–5 |
| FR-1.5 | Validation at point of entry | P1 | 1 | M2 | Must | AC-1.5.1–4 |
| DR-1.6 | Normalized relational database | P1 | 1 | — | Must | AC-1.6.1–3 |
| DR-2.1 | Records retained and reproducible | P5 | 5 | M7 | Must | AC-5.1.1–3 |
| DR-2.2 | Computed lines reference their versions | P2 | 2 · 4 | M4 | Must | AC-2.3.3, AC-5.1.3 |
| DR-2.3 | Decimal type for every monetary column | P2 | 2 · 6 | — | Must | Schema inspection, §5 |
| DR-2.4 | Deletion implemented as deactivation | P1 | 1 · 6 | M2 | Must | AC-1.1.3, AC-1.1.4 |
| FR-2.1 | Basic pay computation | P2 | 2 | M4 | Must | AC-2.1.1–3 |
| FR-2.2 | Additional pay computation | P2 | 2 | M4 | Must | AC-2.2.1–4 |
| FR-2.3 | Statutory deduction tables | P2 | 2 | M4 † | Must | AC-2.3.1–5 |
| FR-2.4 | Adjustments and other deductions | P2 | 2 | M4 | Must | AC-2.4.1–4 |
| FR-2.5 | Net pay determination | P2 | 2 | M4 | Must | AC-2.5.1–4 |
| FR-2.6 | Payroll run lifecycle | P2 | 2 | M4 | Must | AC-2.6.1–5 |
| NFR-2.7 | Computational accuracy | P2 | 2 · 6 | M4 | Must | Parallel run, §5 |
| FR-3.1 | Automatic payslip generation | P3 | 3 | M6 | Must | AC-3.1.1–4 |
| FR-3.2 | Payslip content and layout | P3 | 3 | M6 | Must | AC-3.2.1–4 |
| FR-3.3 | Batch generation and export | P3 | 3 | M6 | Must | AC-3.3.1–4 |
| FR-3.4 | Payslip retrieval and reprint | P3 | 3 · 5 | M6 | Must | AC-3.4.1–4 |
| NFR-3.5 | Issuance turnaround | P3 | 3 · 6 | M6 | Must | Timed test, §5 |
| FR-4.1 | Pre-finalization validation report | P4 | 4 | M5 | Must | AC-4.1.1–4 |
| FR-4.2 | Payroll register review | P4 | 4 | M5 | Must | AC-4.2.1–4 |
| FR-4.3 | Targeted correction and recomputation | P4 | 4 | M5 | Must | AC-4.3.1–4 |
| FR-4.4 | Approval workflow | P4 | 4 | M5 | Must | AC-4.4.1–7 |
| FR-4.5 | Period locking | P4 | 4 · 6 | M5 | Must | AC-4.5.1–4 |
| FR-5.1 | Payroll records storage | P5 | 5 | M7 | Must | AC-5.1.1–3 |
| FR-5.2 | Search and retrieval | P5 | 5 | M7 | Must | AC-5.2.1–3 |
| FR-5.3 | Report generation | P5 | 5 | M7 | Must | AC-5.3.1–5 |
| NFR-5.4 | Backup and restore | P5 | 5 · 6 | M7 † | Must | Restore test, §5 |
| NFR-5.5 | Retrieval performance | P5 | 5 · 6 | M7 | Must | Timed test, §5 |
| FR-6.1 | Audit trail | P6 | 6 | M1 | Must | AC-6.1.1–5 |
| FR-6.2 | Role-based access control | P6 | 6 | M1 | Must | AC-6.2.1–5 |
| FR-6.3 | ✦ Ledger-anchored integrity verification | P6 | 6 | M1 | Must | AC-6.3.1–7 |
| NFR-6.3 | Confirmation and reversal | P6 | 6 | M1 | Must | Inspection, §5 |
| NFR-6.4 | Database integrity constraints | P6 | 6 | — | Must | Negative test, §5 |
| NFR-6.5 | Security controls | P6 | 6 | M1 | Must | Inspection, §5 |
| NFR-6.6 | ISO/IEC 25010 evaluation | P6 | 6 | — | Must | Survey, §5 |

✦ **Added in design review.** FR-6.3 is the one requirement in this table that was not present when Chapter I closed. It answers P6, which the matrix already carried, and was added to the matrix as P6's seventh requirement; the [matrix §P6](./problem-requirements-matrix.md) records why. Every other identifier here traces to the matrix as originally written.

† **Administered from M1.** These two requirements are maintained by the Administrator from the System Administration module, while their effect belongs to the module named — see §2.2. The use case model places UC-05 and UC-07 in M1 for this reason.

**Coverage check.** Every problem P1–P6 has at least one requirement. Every objective OBJ 1–6 has at least one requirement. Every requirement in the matrix appears here with its identifier intact. The four ⊕ requirements are the only additions and are declared in §1.5.

---

# 10. Acceptance and verification plan

| Stage | What is verified | Method | Exit criterion |
|---|---|---|---|
| **Unit** | Individual computations against BR-01 through BR-25 | Developer test cases per rule | Every business rule has a passing test |
| **Module** | Each module's requirements against its acceptance criteria | Functional testing per AC | All Must-priority AC pass |
| **Integration** | End-to-end payroll cycle: data entry → attendance → computation → validation → approval → payslip → report | Scenario testing on a full cut-off | A complete cycle runs with no manual intervention outside the specified inputs |
| **Parallel run** | NFR-2.7 accuracy | System output compared to independently verified manual computation, ≥ 30 employees × 3 periods | 100% agreement to the centavo |
| **Performance** | NFR-3.5, NFR-5.5, NFR-7.1 | Timed tests on the full population and data set | Each stated threshold met |
| **Security** | FR-0.1, FR-6.2, NFR-6.5 | Permission matrix testing per role; credential storage inspection | No role reaches a function outside its row in FR-6.2; no plaintext credential |
| **User acceptance** | NFR-6.6, NFR-7.2 | ISO/IEC 25010 survey of payroll personnel | Weighted mean ≥ 4.20, reported per characteristic |

**Baseline capture.** Before development begins, record from the current process: preparation time per period, payslip typing time per period, record retrieval time, and error incidence per period. Several acceptance criteria above are comparative and cannot be evaluated without these figures.

---

# 11. Open items requiring client confirmation

Each item below changes at least one requirement. None blocks the start of development, but each must be resolved before the affected requirement is implemented.

| ID | Question | Affects | Needed by |
|---|---|---|---|
| **OI-01** | How many employees, and what is the expected growth? | NFR-3.5, NFR-5.5, NFR-7.1 | Performance testing |
| **OI-02** | Pay frequency — semi-monthly or monthly? Any second population on a different frequency? | FR-0.3, BR-05, BR-19 | Computation module |
| **OI-03** | Which day factor is in use — 261, 313, 365, or another? | BR-02 | Computation module |
| **OI-04** | Does a biometric or timekeeping device exist, and what export format does it produce? | FR-1.3, SW-01, HW-02 | Attendance module |
| **OI-05** | Mix of pay bases — monthly, daily, hourly — across the workforce? | FR-1.2, FR-2.1 | Computation module |
| **OI-06** | Which bank receives the payroll, and what transmittal layout does it require? | FR-5.3, SW-03 | Reporting module |
| **OI-07** | Is pay released by cash, check, or ATM payroll? | FR-5.3, scope boundary | Reporting module |
| **OI-08** | Deployment target — single workstation, local network, or hosted? · **Answered:** local network, browser client, one application server ([system architecture](./system-architecture.md) §3). The client confirms only that a host machine is available. | §2.4, NFR-5.5, NFR-6.5, CM-01 | ✅ Closed — Architecture design |
| **OI-09** | Is one approver sufficient, or is multi-level approval required? | FR-4.4, A-04 | Approval module |
| **OI-10** | What retention period applies to payroll records? | FR-5.1, DR-2.1 | Data design |
| **OI-11** | Ledger administration and topology. **Decided:** the platform is Hyperledger Besu with QBFT consensus ([system architecture](./system-architecture.md) AD-14). **Recommended but not yet validated with the client:** four validator nodes, with the ledger hosts administered by the external IT contact described in §2.3 and the payroll database account excluded from them. The final administrator and the final node count are to be settled in consultation with the client. Nothing in the build waits on this — the answer is deployment configuration rather than design (architecture §7.3) — but it decides how strongly FR-6.3 may be claimed in Chapter IV. | FR-6.3, NFR-6.5 | Before Chapter IV claims are written |

---

# 12. Change log

| Version | Date | Author | Change |
|---|---|---|---|
| 1.0 | August 30, 2026 | — | Initial specification derived from the Problem-to-Requirements Matrix. 25 traced functional requirements specified; 4 foundation requirements added (§1.5); 34 business rules, 24 data entities, and 10 open items documented. |
| 1.1 | August 30, 2026 | — | **Baseline B1.** Consistency audit rounds 1 and 2 resolved (22 + 8 findings). `FR-6.3` added — ledger-anchored integrity verification — with `BR-35`, `BR-36`, and `AC-6.3.1`–`AC-6.3.7`; the matrix carries it as P6's seventh requirement, so matrix-traced functional requirements rise from 25 to 26. `AC-6.1.5`, `AC-4.4.6`, `AC-4.4.7`, `AC-4.5.5`, `AC-6.2.5` added. `DR-2.1`–`DR-2.4` brought into traceability. `NFR-3.5`, `NFR-5.5`, `NFR-6.3` and UC-26 raised to `Must`. `AC-4.1.4` given a 15% threshold. `For Review` return corrected to land in `Returned`. Viewer read access narrowed to payroll outputs. `OI-08` closed by the system architecture; `OI-11` opened and remains the sole outstanding design question. Totals: 30 FR, 8 gated NFR, 5 DR, 36 business rules, 43 requirement items. |
