# Problem-to-Requirements Matrix

**Project:** Payroll Management System
**Document:** Requirements Analysis (Chapter I / Chapter III support)
**Version:** 1.2
**Date:** August 30, 2026
**Baseline:** B2 — frozen August 30, 2026 · see [baseline.md](./baseline.md)
**Change:** [CR-01](./change-request-cr-01.md) — payroll computation retained by the accounting office

| | |
|---|---|
| Problems identified | 6 |
| Requirements derived | 37 (28 functional, 8 non-functional, 1 data) — one added in design review, marked ✦; the P2 cluster restated on the client's decision, marked ✧ |
| Objectives traced | 6 |

---

## Introduction

The client's current payroll cycle is carried out end to end by hand: employee and attendance data are re-encoded each period, computation is performed in Microsoft Excel, payslips are typed one by one, verification is done by visual inspection, and records are filed and retrieved manually. Six problems follow from this arrangement, and five of them are manual steps a computerized payroll system can absorb.

The sixth is different, and this document records why. **The client has decided that the payroll computation itself stays with the accounting office** ([CR-01](./change-request-cr-01.md), August 30, 2026). The spreadsheet is not replaced. What P2 identifies, therefore, is no longer the computation — it is everything that happens to the computed figures once they leave the spreadsheet, which until now has been transcription, circulation, and filing by hand with no verification and no record. §P2 states the restated problem and the requirements that answer it.

The matrix below reads in one direction only: a problem produces requirements, and each requirement carries the measure that will show it worked. No requirement appears here that is not answering a documented problem — this is what keeps the system scope defensible before the panel, and what allows Chapter IV to report results against Chapter I objectives rather than against a wish list.

### Requirement classes

| Class | Meaning |
|---|---|
| **FR** — Functional | Something the system must do: a screen, a computation, a generated document. |
| **NFR** — Non-functional | A quality the system must hold: accuracy, speed, security, usability. |
| **DR** — Data | A structural rule about how payroll data is stored and related. |
| **Workflow step** | The node in the client's current flowchart that the requirement replaces or automates. |

---

## P1 — Manual data entry

Employee, attendance, and leave information is re-encoded by hand for every payroll period, even when the underlying data has not changed since the last cycle.

**Workflow steps:** B · C · D  **Requirements:** 6  **Primary objective:** OBJ 1

**Table 1.** *Requirements derived from P1*

| ID | Requirement | Type | How it removes the problem · success measure | Obj. |
|---|---|---|---|---|
| FR-1.1 | Employee master file — add, update, and deactivate employee records in one place. | FR | Employee data is encoded once and reused by every succeeding payroll run; measured by zero re-encoding of unchanged records across three consecutive test periods. | OBJ 1 |
| FR-1.2 | Compensation profile per employee — pay basis (monthly, daily, hourly), basic rate, fixed allowances, and standing loan or deduction schedules. | FR | Rates and recurring items carry forward automatically instead of being retyped into each period's worksheet. | OBJ 1 |
| FR-1.3 | Attendance intake — import daily time records from a CSV or Excel template or a biometric export; manual encoding reserved for exceptions. | FR | Eliminates line-by-line transcription of the DTR; measured by the share of attendance rows loaded by import rather than keyed (target ≥ 90%). | OBJ 1 |
| FR-1.4 | Leave module — file, approve, and track leave balances, with approved leaves posted automatically to the covering payroll period. | FR | Removes the separate manual review of leave records before payroll preparation; credits and deductions reconcile without a second encoding pass. | OBJ 1 |
| FR-1.5 | Validation at point of entry — required fields, value ranges, date logic, and duplicate employee-number detection. | FR | Bad data is rejected when captured rather than discovered during verification; measured by zero duplicate or incomplete employee records in the test database. | OBJ 1 |
| DR-1.6 | Normalized relational database with one authoritative record per employee, referenced by the attendance, leave, payroll, and payslip modules. | DR | Makes single-entry structurally possible: no module keeps its own copy of employee data, so nothing can drift out of sync. | OBJ 1 |

---

## P2 — ✧ Uncontrolled handling of computed payroll

Payroll is computed in spreadsheets, and the resulting figures are then transcribed, circulated, approved, and filed by hand. Nothing verifies that the register was carried forward intact, nothing records which version of it was approved, and nothing protects it from alteration afterward. A figure that is wrong when it leaves the spreadsheet, or that changes after it leaves, is not detectable by any step in the current process.

**Workflow steps:** G · H · I (intake side of the review loop)  **Requirements:** 9  **Primary objective:** OBJ 2

✧ **Restated on the client's decision of August 30, 2026.** This problem was originally stated as *Dependence on Excel for computation*, and the seven requirements beneath it replaced the spreadsheet with a computation engine. The client has since decided that the accounting office continues to perform the computation. §"On restating a problem after analysis closed" below records the provenance in full; [CR-01](./change-request-cr-01.md) records the analysis behind it.

**Table 2.** *Requirements derived from P2*

| ID | Requirement | Type | How it removes the problem · success measure | Obj. |
|---|---|---|---|---|
| FR-2.3 | ✧ Statutory reference tables for remittance reporting — SSS, PhilHealth, Pag-IBIG, and BIR schedules as effectivity-dated bracket tables, used to derive **employer shares** where the imported register does not carry them. They take no part in determining any employee's pay. | FR | Keeps the per-agency remittance reports of FR-5.3 producible regardless of what the accounting office's register carries; a contribution-table change is applied once and past runs keep the table in force at their time. | OBJ 2 · 5 |
| FR-2.4 | ✧ Adjustments and other deductions recorded as line items — cash advances, loan amortizations, and retroactive corrections attached to the payroll line. The system records and tracks them; it does not determine their amounts. | FR | Every adjustment becomes an auditable record attached to the run instead of an untraceable edited cell or a note on a printout. | OBJ 2 |
| FR-2.5 | ✧ Net pay integrity check — the system verifies that every imported row satisfies `net = gross − total deductions` and that every total is the sum of its own line items. | FR | A row that does not reconcile is refused before it enters the record; measured by 100% of non-reconciling rows blocked in the validation set. | OBJ 2 · 6 |
| FR-2.6 | ✧ Period-based payroll run — create, import, and re-import an entire cut-off as one governed unit with an explicit lifecycle. | FR | One authoritative run per period and run type, replacing the circulating spreadsheet copies; measured by the absence of any second system of record for a period. | OBJ 2 |
| FR-2.8 | ✧ Computed payroll register import — load the accounting office's completed register through a defined template and a configurable column mapping. | FR | Replaces the manual transcription of figures from the spreadsheet into forms, payslips, and reports; measured by zero payroll figures keyed by hand into the system. | OBJ 2 · 3 |
| FR-2.9 | ✧ Import reconciliation and completeness — row arithmetic, file control totals, and employee matching against the master file, all checked before the register is accepted. | FR | Detects a register that is internally inconsistent or incomplete at the moment it enters, rather than after payment; measured by 100% of seeded inconsistencies refused. | OBJ 2 · 6 |
| FR-2.10 | ✧ Import versioning — every imported register retained with its file hash, importing user, and timestamp; a corrected re-import supersedes without erasing. | FR | Makes it answerable which file produced an approved payroll, which the current process cannot answer at all. | OBJ 2 · 6 |
| FR-2.11 | ✧ Payroll input worksheet export — the system exports employee, compensation, attendance, and leave data in the form the accounting office computes on. | FR | Prevents the re-encoding that P1 exists to eliminate from returning at a new point in the cycle; measured by the share of the accounting office's worksheet populated by export rather than keyed (target 100% of employee and attendance columns). | OBJ 2 · 1 |
| NFR-2.12 | ✧ Transcription fidelity — every value the system stores equals the value in the source register to the centavo, and a stored run re-exports identically to what was imported. | NFR | 100% agreement on a validation set of at least 30 employees across three payroll periods, covering regular, overtime, leave-affected, and loan-deducted cases. | OBJ 2 · 6 |

**Retired from this cluster by CR-01.** `FR-2.1` (basic pay computation), `FR-2.2` (additional pay computation), and `NFR-2.7` (computational accuracy) are retired: each specified a figure the system no longer derives. Their identifiers are not reused and not reassigned. `NFR-2.12` replaces `NFR-2.7` as this cluster's measurable quality — it measures faithful carriage of a figure rather than correct derivation of one, and it uses the same validation-set design so the Chapter IV sample is unchanged.

**What this cluster no longer claims.** The spreadsheet formulas remain unprotected and uncontrolled, and the system does not change that. Chapter IV must not report an accuracy result for a computation the system did not perform. What it can report is that the computed register entered the record intact, reconciled, versioned, attributable, and thereafter unalterable — which is what the restated P2 asks for.

---

## P3 — Manual payslip generation

Payslips are typed individually after the payroll is finalized, duplicating figures that already exist in the worksheet and allowing the payslip and the payroll register to disagree.

**Workflow steps:** L · M  **Requirements:** 5  **Primary objective:** OBJ 3

**Table 3.** *Requirements derived from P3*

| ID | Requirement | Type | How it removes the problem · success measure | Obj. |
|---|---|---|---|---|
| FR-3.1 | Automatic payslip generation from the finalized payroll run, with no re-entry of any figure. | FR | Replaces step M entirely; the payslip is a rendering of stored payroll data, so it cannot disagree with the register. | OBJ 3 |
| FR-3.2 | Standard payslip layout showing employee and period details, itemized earnings, itemized deductions, and net pay. | FR | Satisfies the statutory requirement to disclose deductions and removes the format inconsistency between individually typed slips. | OBJ 3 |
| FR-3.3 | Batch generation, PDF export, and printing of all payslips for a period in one action. | FR | Replaces step L; measured by generation time for a full period against the hours currently spent typing. | OBJ 3 |
| FR-3.4 | Payslip reprint and retrieval for any past period, by employee or by period. | FR | A reissued payslip is regenerated from the original run rather than retyped from a filed copy. | OBJ 3 · 5 |
| NFR-3.5 | Issuance turnaround — the complete payslip set for one payroll period produced within five minutes of finalization. | NFR | Quantifies the labor removed from steps L and M; baseline is the client's current typing time per period. | OBJ 3 · 6 |

---

## P4 — Manual verification

Every computation is checked by eye before the payroll can proceed, and a single correction sends the whole cycle back through computation with no record of what changed or who approved it.

**Workflow steps:** G · H · I · J · K  **Requirements:** 5  **Primary objective:** OBJ 4

**Table 4.** *Requirements derived from P4*

| ID | Requirement | Type | How it removes the problem · success measure | Obj. |
|---|---|---|---|---|
| FR-4.1 | Pre-finalization validation report flagging exceptions: missing attendance, missing rate, zero or negative net pay, deductions exceeding gross pay, and out-of-range values. | FR | Turns step G from a full manual sweep into a review of flagged exceptions only; measured by the share of a run still requiring manual inspection. | OBJ 4 |
| FR-4.2 | On-screen payroll register showing all employees and imported payroll/result columns for a period, sortable and filterable. | FR | Gives the reviewer one authoritative view instead of scrolling across worksheet tabs. | OBJ 4 |
| FR-4.3 | ✧ Targeted correction — amend a payroll line by a recorded adjustment, or replace the run by re-importing a corrected register; unaffected lines are untouched in either path. | FR | Narrows the H → I loop to the lines actually affected instead of re-transcribing the whole payroll. Correction of a computed figure itself returns to the accounting office — see the delimitation note in §"Scope the matrix defines". | OBJ 4 |
| FR-4.4 | Approval workflow with explicit states — Draft → For Review → Approved → Finalized — recording approver and timestamp at each transition. | FR | Makes steps J and K system-enforced and evidenced rather than an undocumented handoff. | OBJ 4 |
| FR-4.5 | Period locking — a finalized run becomes read-only; later changes require a documented adjustment or reversal entry. | FR | Prevents silent post-approval edits, the failure an unprotected worksheet cannot guard against. | OBJ 4 · 6 |

---

## P5 — Manual record management

Payroll transactions, documents, and historical records are filed and retrieved by hand, so reporting and any look-up of a past period depend on locating the correct physical or spreadsheet file.

**Workflow steps:** N · O · P  **Requirements:** 5  **Primary objective:** OBJ 5

**Table 5.** *Requirements derived from P5*

| ID | Requirement | Type | How it removes the problem · success measure | Obj. |
|---|---|---|---|---|
| FR-5.1 | Persistent storage of every payroll run, payslip, and supporting input in the database, indexed by period, employee, and department. | FR | Replaces step P; recording a transaction and filing it become the same action. | OBJ 5 |
| FR-5.2 | Search and filter across historical payroll records by employee, period, department, or status. | FR | Retrieval becomes a query rather than a manual file search. | OBJ 5 |
| FR-5.3 | Report generation — payroll summary and register, per-agency remittance reports (SSS, PhilHealth, Pag-IBIG, BIR), bank transmittal listing, and 13th-month pay report, exportable to PDF and Excel. | FR | Replaces steps N and O; reports are produced from stored data instead of being compiled by hand whenever requested. | OBJ 5 |
| NFR-5.4 | Scheduled database backup with a documented restore procedure. | NFR | Addresses the loss exposure of records held only as local worksheet files; verified by a successful test restore. | OBJ 5 · 6 |
| NFR-5.5 | Retrieval performance — any past payslip, register, or report located and displayed within one minute. | NFR | Quantifies the improvement over manual retrieval; baseline measured against the client's current look-up time. | OBJ 5 · 6 |

---

## P6 — Risk of human error

Because entry, computation, checking, and document preparation are all manual, an error can enter at any stage and may go undetected until after payment. This problem is cross-cutting: P1–P5 reduce how often errors occur, while the requirements below make them detectable, attributable, and recoverable.

**Workflow steps:** B–P (all)  **Requirements:** 7  **Primary objective:** OBJ 6

**Table 6.** *Requirements derived from P6*

| ID | Requirement | Type | How it removes the problem · success measure | Obj. |
|---|---|---|---|---|
| FR-6.1 | Audit trail logging every create, update, delete, and approve action with the user account and timestamp. | FR | An error can be traced to its origin and reversed; a worksheet offers no equivalent record. | OBJ 6 |
| FR-6.2 | Role-based access control separating Administrator, Payroll Officer, Approver, and Viewer permissions. | FR | Enforces separation between preparing and approving a payroll — impossible when a single shared file is the system of record. | OBJ 6 |
| NFR-6.3 | Confirmation prompts and reversal paths for irreversible actions (finalize, void, delete). | NFR | Reduces the cost of a mis-click; verified through usability testing of each destructive action. | OBJ 6 |
| NFR-6.4 | Database-level integrity constraints — referential integrity, unique keys, and non-null rules on payroll-critical fields. | NFR | Makes structurally invalid payroll data impossible to save, rather than something verification must catch later. | OBJ 6 |
| NFR-6.5 | Security controls — hashed password storage, session timeout, and individual (non-shared) user accounts. | NFR | Protects payroll data and makes the audit trail meaningful, since every action maps to a real person. | OBJ 6 |
| NFR-6.6 | System quality evaluation using ISO/IEC 25010 — functional suitability, performance efficiency, usability, reliability, and security — with the client's payroll staff as respondents. | NFR | Target weighted mean of at least 4.20 (Very Satisfactory) on a five-point Likert scale, reported per characteristic. | OBJ 6 |
| FR-6.3 | ✦ Ledger-anchored integrity verification — a cryptographic fingerprint of every finalized run, every reversal, and every segment of the audit trail recorded in a permissioned ledger held outside the payroll database. | FR | FR-6.1 and FR-6.2 make an error detectable and attributable *within* the application. Neither detects a change made outside it — by direct database access or by restoring a doctored backup. This makes such a change provable after the fact; measured by a seeded alteration being reported as a mismatch naming the affected run. | OBJ 6 |

✦ **Added during design review, not initial analysis.** The client stated P6 as *risk of human error*, and the six requirements above it were derived directly from that statement. FR-6.3 came later: reviewing the design against P6 showed that "detectable, attributable, and recoverable" had been answered only for errors arising *through* the system, leaving alteration of the stored records themselves outside the answer. The gap is a design-level finding — not something the client could have been expected to articulate — and it is recorded here rather than only in the specification so that the matrix remains the single authoritative statement of what this system's requirements are and where each came from.

Its identifier is `FR-6.3` because it belongs to the P6 cluster; the numbering follows the problem, not the order of discovery.

---

## Traceability grid

Every problem must reach at least one objective, and every objective must answer at least one problem. A blank row or a blank column means either an unaddressed problem or an objective with nothing to justify it — both are defects in the requirements, not in the system.

**Table 7.** *Problem coverage by specific objective*

| Problem | OBJ 1 | OBJ 2 | OBJ 3 | OBJ 4 | OBJ 5 | OBJ 6 | Reqs |
|---|:---:|:---:|:---:|:---:|:---:|:---:|:---:|
| P1 — Manual data entry | ● | ○ | | | ○ | ○ | 6 |
| P2 — ✧ Uncontrolled handling of computed payroll | ○ | ● | ○ | | ○ | ○ | 9 |
| P3 — Manual payslip generation | | ○ | ● | | ○ | ○ | 5 |
| P4 — Manual verification | | ○ | | ● | | ○ | 5 |
| P5 — Manual record management | | | ○ | | ● | ○ | 5 |
| P6 — Risk of human error | ○ | ○ | ○ | ○ | ○ | ● | 7 |

**Legend:** ● Primary — the objective exists to solve this problem · ○ Supporting — contributes to the solution

---

## Specific objectives the matrix traces to

These are stated in the form the manuscript needs — one objective per problem cluster, each measurable, and each with a Chapter V conclusion that can be written against it. Six objectives require exactly six conclusions, in this order.

1. **OBJ 1 — Employee and payroll data management.** Develop a module that stores employee, compensation, attendance, and leave data once and makes it available to all succeeding payroll periods without re-encoding.
2. **OBJ 2 — ✧ Verified payroll intake.** Develop a module that imports the accounting office's computed payroll register, verifies its arithmetic integrity and completeness against the employee master file, and establishes it as the controlled system of record for approval, issuance, and reporting.
3. **OBJ 3 — Payslip generation.** Develop a module that generates, exports, and reprints payslips automatically from finalized payroll runs.
4. **OBJ 4 — Validation and approval.** Develop a module that validates payroll runs against defined exception rules and routes them through a recorded review-and-approval workflow.
5. **OBJ 5 — Records and reporting.** Develop a module that stores payroll records for retrieval and generates payroll, remittance, and transmittal reports on demand.
6. **OBJ 6 — Evaluation.** Evaluate the accuracy, security, and usability of the developed system using ISO/IEC 25010 with the client's payroll personnel as respondents.

---

## Scope the matrix defines

| Count | Item |
|---:|---|
| 28 | Functional requirements |
| 8 | Non-functional requirements |
| 1 | Data requirement |
| **37** | **Total requirements** |
| 13 | Workflow steps absorbed |

Of the twenty process steps between Start and End in the client's current workflow, **thirteen** are absorbed by the system. Seven remain outside its boundary: the receipt of source documents at the start, the actual release of payment at the end, and — following [CR-01](./change-request-cr-01.md) — steps **E, F, and F1 through F5**, the computation itself, which the accounting office continues to perform in Microsoft Excel.

**Scope and Delimitations must state all seven.** The computation exclusion is the one that most changes how this project should be described: the system does not determine any employee's pay. It supplies the data the computation is performed on (FR-2.11), receives the result (FR-2.8), proves the result is internally consistent and complete (FR-2.9), and then governs everything that happens to it afterward. A correction to a computed figure is made in the accounting office's spreadsheet and re-enters through a corrected import; the system does not amend it.

The remaining exclusions are unchanged: disbursement, timekeeping hardware, general-ledger integration, and employee self-service.

With each problem now bound to named requirements and a measurable objective, the next section can move from what the system must do to how it will be built — the development methodology, tools, and cost framework of the operational framework.

---

## Notes

**Verification note.** Every success measure above states a baseline drawn from the client's current process. Capture those baselines — preparation time per period, payslip typing time, retrieval time, and error incidence — before development begins, or the Chapter IV comparison cannot be made.

**Numbering.** Requirement IDs are stable references (class–problem.sequence). Keep them unchanged through Chapters III and IV so every implemented feature and every test result can be cited back to the problem it answers.

**Revision.** This matrix carried 34 requirements when Chapter I was written. `FR-6.3` was added afterward, during design review, bringing it to 35 — the one requirement here that did not come from the client's own account of their process. It is marked ✦ throughout, and §P6 records why.

A second revision followed on **August 30, 2026**, on the client's decision that the accounting office continues to perform the payroll computation. `P2` was restated, `OBJ 2` was restated, and the P2 cluster was rebuilt: `FR-2.1`, `FR-2.2`, and `NFR-2.7` retired; `FR-2.3` through `FR-2.6` reworded; `FR-2.8`, `FR-2.9`, `FR-2.10`, `FR-2.11`, and `NFR-2.12` added; `FR-4.3` reworded to match. Everything in that revision is marked ✧. The total moved from 35 to 37. **No identifier was reused, reassigned, or renumbered** — the three retired identifiers stay retired and stay named, so a reader of the earlier version can find what became of them.

## On restating a problem after analysis closed

This is the more serious of the two revisions, and it should be read as such. `FR-6.3` added a requirement to a problem the matrix already carried. `CR-01` removed a problem's answer and wrote a different one.

Three things make it defensible, and all three must remain visible in Chapter I:

1. **It is a client decision, not a design-review finding.** The scope of a capstone system is the client's to set. What the analysis owes is an accurate record of what was decided, when, and what it cost — not a matrix that reads as though the accounting office was always going to compute.
2. **The restated P2 is still the client's own problem.** It was elicited from the same account of the same process. The original P2 named the spreadsheet as the problem; the restated P2 names what happens to the spreadsheet's output, which is a step the client described and which no requirement previously covered end to end. Nothing was invented to fill the gap the decision opened.
3. **The cost is recorded rather than absorbed.** [CR-01](./change-request-cr-01.md) states plainly what the system stopped claiming: it no longer computes, the Excel formulas remain uncontrolled, its accuracy is bounded by a spreadsheet it cannot inspect, and Chapter IV cannot report a computational accuracy result. A revision that quietly kept `OBJ 2 — Automated computation` standing over an import module would have made this document a record of the system instead of a record of the analysis.

**The honest summary for the panel.** The system's contribution is control, verification, and traceability over a payroll computed elsewhere — not the automation of the computation. That is a smaller claim than the one this matrix carried on August 30, 2026 at version 1.1, and it is the true one.
