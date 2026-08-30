# Pre-Oral Demonstration Plan

**Project:** Payroll Management System
**Version:** 1.0
**Date:** August 31, 2026
**Built on:** Baseline B2 — see [baseline.md](./baseline.md)
**Subordinate to:** [implementation-plan.md](./implementation-plan.md)
**Status:** Working document. **Not part of the frozen baseline.**

---

# 1. About this plan

The pre-oral defense requires a working slice of the system — conventionally *40%* — demonstrated to the panel alongside the approved chapters. This document fixes **which 40%**, **why that 40% and not another**, **how it is built in the weeks available**, and **how it is demonstrated and defended**.

It adds no requirement and changes no design decision. It selects a subset of what [implementation-plan.md](./implementation-plan.md) already sequences, and re-cuts that sequence for a demonstration deadline rather than for a delivery deadline. Where this plan and the implementation plan disagree on *order*, this one is the pre-oral schedule and the other is the project schedule; where either disagrees with **B2, B2 is right and both are stale.**

**One rule governs everything below.** A demonstrated 40% is not 40% of the screens. It is 40% of the *requirement set*, ending at a point where the system does something end to end that no spreadsheet does. A slice that stops halfway through the payroll cycle demonstrates a shell; a slice that goes all the way through one arc of it demonstrates a system.

---

# 2. What "40%" means here, stated as a number the panel can check

A percentage without a denominator is not a claim. Four denominators exist in the baseline, and the slice below is measured against all of them so the figure survives being asked about.

**Table 1.** *Coverage of the pre-oral slice*

| Denominator | B2 total | In the slice | Share |
|---|---:|---:|---:|
| **Primary use cases** | 33 | **14** | **42.4%** |
| Included use cases | 7 | 4 | 57.1% |
| All use cases | 40 | 18 | 45.0% |
| **Requirement items** (32 FR + 8 NFR + 5 DR) | 45 | **21 complete**, 2 partial | **46.7%** complete · 51.1% touched |
| Modules delivered whole | 7 | 2 (M1 less FR-6.3, and M2) | 28.6% |
| Entities carrying data | 39 | 29 (all 39 migrated) | 74.4% |

**The headline figure is 42.4% of primary use cases**, cross-checked at 46.7% of requirement items. Both clear the 40% floor without inflating it. Quote the use-case figure first: it is the one the panel can count off Table 1 of the [use case model](./use-case-model.md) in a few seconds, and a figure a panel can verify itself is worth more than a larger figure it has to take on trust.

**On the entity figure.** All 39 migrations are written in Sprint 0 and exist from week one — that is implementation plan §3 item 0.2, not an achievement of this slice. What the slice earns is that **29 of the 39 hold real data** by demonstration day. Do not present "39 of 39 tables" as functionality; it is schema, and a panel that catches the conflation will discount every other number on the slide.

---

# 3. The slice

## 3.1 The principle behind the selection

The slice follows **one complete arc of the payroll cycle, from an empty system to a reconciled payroll register**, and stops there.

That arc is chosen because it is where this project's contribution lives. After [CR-01](./change-request-cr-01.md), the accounting office computes payroll in Excel and the system does not. What the system contributes is the **governed intake boundary**: it supplies the data the computation is performed on, receives the result, and proves that result is internally consistent and complete before letting it become the record. FRS §1.2 and the [matrix](./problem-requirements-matrix.md) scope section both say so, and OBJ 2 is written against it.

A demonstration that stops before the import is a demonstration of an employee database. The import — and specifically the **refusal** of a defective register — is the moment the panel sees a system doing something a spreadsheet cannot do to itself. That moment is the deliverable, and everything else in the slice exists to make it reachable.

## 3.2 Use cases in the slice

**Table 2.** *The fourteen primary use cases*

| UC | Name | Module | Requirement | Why it is in the slice |
|---|---|:---:|---|---|
| UC-01 | Sign in | M1 | FR-0.1 | Nothing is demonstrable without an authenticated actor, and lockout plus session timeout are visible NFR-6.5 evidence |
| UC-02 | Manage user accounts | M1 | FR-0.2 | Four roles must exist for the permission refusal in §7 beat 2 to be real rather than staged |
| UC-03 | Configure organization profile and payroll calendar | M1 | FR-0.3 | A payroll run needs a defined period; BR-34 — no overlap, no gap — is checkable on screen |
| UC-04 | Maintain reference data | M1 | FR-0.4 | Carries `IMPORT_COLUMN_MAP` — the AD-17 evidence that a register layout change is configuration, not code |
| UC-06 | Review audit log | M1 | FR-6.1 | The hash-chained log answers "how do you know who did this", asked at every defense |
| UC-08 | Register employee | M2 | FR-1.1, FR-1.5 | Single-entry (P1, OBJ 1) is unshowable without it |
| UC-09 | Update employee record | M2 | FR-1.1, FR-1.5 | Effectivity dating is what makes a past run reproducible |
| UC-10 | Deactivate or reactivate employee | M2 | FR-1.1 | DR-2.4: deletion is deactivation. Cheap to build, and answers a records-integrity question directly |
| UC-11 | Maintain compensation profile | M2 | FR-1.2 | Supplies the rate and standing-deduction columns of the exported worksheet (BR-08) |
| UC-13 | Import attendance records | M3 | FR-1.3 | Populates the worksheet's attendance block; also the second demonstration of all-or-nothing file intake |
| UC-17 | Create payroll run | M4 | FR-2.6 | The container the arc terminates in |
| UC-32 | Export payroll input worksheet | M4 | FR-2.11 | The outbound half of the intake boundary — the reason the accounting office stops re-keying |
| UC-18 | Import computed payroll register | M4 | FR-2.5, FR-2.6, FR-2.8, FR-2.9 | **The centre of the demonstration.** Parse, reconcile, refuse, accept |
| UC-33 | Review import history | M4 | FR-2.10 | Proves a correction supersedes without erasing — provenance, and the answer to "what was this payroll built from" |

**Table 3.** *The four included use cases, which arrive with the above rather than as extra work*

| UC | Name | Owned by | Delivered in |
|---|---|---|---|
| UC-I1 | Validate data entry | `ValidationService` | Sprint 3 work (W5–W6) |
| UC-I2 | Record audit entry | `AuditService` | Sprint 1a work (W2–W3) |
| UC-I3 | Authorize action | `AuthorizationService` | Sprint 1a work (W2–W3) |
| UC-I7 | Reconcile imported register | `ReconciliationService` | Sprint 1b work (W2–W3), wired in W7 |

## 3.3 Requirement items in the slice

**Complete — 21 items.**

| Class | Items |
|---|---|
| Functional (14) | FR-0.1, FR-0.2, FR-0.3, FR-0.4, FR-1.1, FR-1.2, FR-1.5, FR-2.5, FR-2.6, FR-2.8, FR-2.9, FR-2.10, FR-6.1, FR-6.2 |
| Data (4) | DR-1.6, DR-2.2, DR-2.3, DR-2.4 |
| Non-functional (3) | NFR-2.12, NFR-6.4, NFR-6.5 |

**Partial — 2 items, declared as partial in §4.2 and on the slide.**

| Item | In the slice | Not in the slice |
|---|---|---|
| FR-1.3 Attendance intake | File import with preview and all-or-nothing commit (AC-1.3.1–1.3.3) | Manual exception encoding (UC-14), and the BR-03/BR-04 derived figures beyond hours worked and late minutes |
| FR-2.11 Worksheet export | Employee, compensation, attendance and standing-deduction columns; run and period header block (AC-2.11.1, AC-2.11.4, AC-2.11.5) | The approved-leave block, which depends on FR-1.4 (UC-15, UC-16) |

**NFR-2.12 is claimed complete deliberately.** The intake fidelity harness is Sprint 1b work and runs against fixture registers, not against finished screens. It is the one non-functional requirement that can be fully evidenced this early, and it is the single most valuable thing to have evidenced at pre-orals — see §8.

---

# 4. What is deliberately not in the slice

This is the section the panel presses on. A slice is defensible when every omission has a reason about **sequence or dependency**, not about difficulty.

## 4.1 Omitted, with the reason

**Table 4.** *The nineteen omitted primary use cases*

| Omitted | Reason it is out |
|---|---|
| **UC-20 – UC-26** (M5 · validation and approval) | The approval workflow governs a payroll run *after* one exists. The slice ends at the moment a reconciled run first exists, which is the earliest point at which M5 has an input. Building M5 first means demonstrating a state machine over empty runs |
| **UC-27, UC-28** (M6 · payslips) | FR-3.1 refuses generation for a run that is not `Finalized`, and finalization is UC-25 in M5. M6 is unreachable before M5 by requirement, not by choice |
| **UC-29, UC-30** (M7 · records and reporting) | `ReportService` performs no computation of its own; it reads stored data. Implementation plan §2.1: before there is stored data, a report is a layout exercise — and a layout exercise is exactly what makes a demonstration look larger than the system is |
| **UC-31** (integrity verification) | Additive by construction: two tables, two columns, one component nothing else calls. Implementation plan §2.1 and §4.4 — it costs the same in Sprint 9 as in Sprint 2 and proves nothing earlier. It also anchors *finalized* runs, and this slice finalizes none |
| **UC-05** (statutory schedules) | Post-CR-01 these feed only the remittance reports of FR-5.3, and **OI-13 may retire FR-2.3 entirely.** Building them now risks building something the client's register makes unnecessary |
| **UC-07** (backup and restore) | Operational rather than functional. It protects M7's records, and there are no M7 records yet. The Sprint 0 offline-deployment rehearsal already covers the deployment claim a panel is likely to probe |
| **UC-12** (loan accounts) | The worksheet needs open loan *balances* as reference columns, which the slice supplies from seeded data. Loan movement is recorded through FR-2.4 adjustments, which are M5-adjacent and out |
| **UC-14** (attendance exceptions) | The manual-encoding half of FR-1.3. The import half carries the P1 claim; the encoding half is a CRUD screen over the same table |
| **UC-15, UC-16** (leave) | Leave reaches the slice only as a block of worksheet columns. Its absence is declared in §4.2 rather than hidden |
| **UC-19** (payroll adjustments) | FR-2.4 records movement *against* an imported payroll line and is consumed by M5's correction flow (FR-4.3). It belongs with the module that uses it |

**Table 5.** *The ten entities that stay empty*

`EXCEPTION_INSTANCE` · `RUN_TRANSITION` · `REVERSAL_RECORD` · `PAYSLIP_ISSUANCE` · `STATUTORY_SCHEDULE` · `STATUTORY_BRACKET` · `INTEGRITY_ANCHOR` · `INTEGRITY_VERIFICATION` · `LEAVE_APPLICATION` · `LEAVE_BALANCE`

All ten are **migrated and constrained** from Sprint 0. They hold no rows because the use cases that write them are out of the slice — a different statement from "not built yet", and one worth making out loud whenever the schema is shown.

## 4.2 The two declared partials

Say these before the panel finds them.

> *"Two requirements in this increment are partial, and we are naming them rather than counting them. FR-1.3 delivers attendance by import; manual exception encoding is Sprint 4 work. FR-2.11 exports every worksheet column except the approved-leave block, because leave administration is FR-1.4 and is not in this increment. Both are counted as partial in our coverage table, not as complete."*

A declared partial costs nothing. An undeclared one found by a panel member costs the credibility of every other figure on the slide.

---

# 5. The gate before week one

Nothing in §6 starts until these are done. Each is implementation plan §3, unchanged; the fifth matters most and is the one most often skipped.

| # | Item | Done when |
|---|---|---|
| 0.1 | Repository, coding standard, branch model | The repository builds from a clean clone |
| 0.2 | All 39 migrations, written at once — including the three `PAYROLL_LINE` reconciliation constraints (BR-37) | A fresh migrate runs clean on an empty database |
| 0.3 | Seeders: departments, positions, employment statuses, earning and deduction types, leave types, holidays, `SYSTEM_CONFIG`, **and one `IMPORT_COLUMN_MAP` row** against the canonical template | The demo state reproduces from seeders alone |
| 0.4 | Offline deployment rehearsal (AD-16) | The artifact runs on a machine with its network cable pulled |
| 0.5 | **The no-float parse proof, and the first fixture register** | A test reads a monetary cell from a real `.xlsx` as a float and fails, then reads it as a decimal string and holds — committed as the executable statement of BR-40 and AD-18 |

**Two client questions go out in week one.** Both are baseline §4 gating items, and neither can be answered by the team.

| Ask | Why in week one |
|---|---|
| **OI-12** — a **sample register file** for a real period. Not a description; an actual file | The whole payroll enters through this one file. AD-17 absorbs a *renamed or reordered column*; it does not absorb a structurally different one. If the register is not one row per employee, that is a design conversation, and it must happen in W2, not W7 |
| **OI-09** — single approver, or multi-level? | The only open item that changes design rather than configuration. M5 is out of this slice, so the answer costs nothing now and a rebuild later. Ask while it is free |

If OI-12 does not arrive, proceed on the canonical template of FR-2.8 with the seeded mapping row — that is what AD-17 was designed for — and say so on the slide. Proceeding on the documented default is a defensible engineering position; discovering in W7 that you needed the file is not.

---

# 6. The build calendar

Eight weeks, three or four people, mapped onto the implementation plan's sprints. Sprint 2 compresses to one week because UC-07 is out of the slice; Sprint 4 reduces to its import half; Sprint 5 reduces to the arc that ends at a reconciled run.

**Table 6.** *Weeks*

| Week | Track A — domain / intake | Track B — application / persistence | Track C — presentation | Ends with |
|---|---|---|---|---|
| **W1** | Item 0.5: no-float proof, fixture register | Items 0.1, 0.2: repo, 39 migrations | Item 0.3: seeders, layout shell | Item 0.4 offline rehearsal passes; OI-12 and OI-09 sent |
| **W2** | `RegisterImportService` parse path — decimal strings end to end | `AuthorizationService`, `AuditService`, hash-chained `AUDIT_LOG` | Sign-in, lockout, session timeout (UC-01) | A signed-in action appears in `AUDIT_LOG` with a valid `prev_entry_hash` |
| **W3** | `ReconciliationService` — row arithmetic, control totals, matching, completeness. **Refusal suite: one seeded defect per register** | User and role administration; the FR-6.2 permission matrix | User screens (UC-02), audit log viewer (UC-06) | **Milestone P-A.** A fixture register imports to the centavo with no float in the path, and every seeded defective register is refused with the defect named |
| **W4** | NFR-2.12 fidelity harness — file→database and database→file | Organization profile, period generation (BR-34), reference data | UC-03 and UC-04 screens, including the `IMPORT_COLUMN_MAP` editor | Periods for a year generate with no overlap and no gap |
| **W5** | `WorksheetExportService` against fixtures | Employee, employment detail; `ValidationService` | UC-08, UC-09, UC-10 screens | An employee saves, and an invalid field is refused at entry |
| **W6** | `AttendanceImportService` — all-or-nothing commit | Compensation profile persistence, effectivity dating (BR-08) | UC-11 screen, UC-13 import preview | **Milestone P-B.** 30 employees exist with complete compensation profiles — the NFR-2.12 population |
| **W7** | Intake core wired to real repositories; import versioning and supersession | `PAYROLL_RUN`, `PAYROLL_IMPORT`, `PAYROLL_LINE` write path; atomic import | UC-17, UC-32, UC-18 preview and result, UC-33 history | **Milestone P-C.** Worksheet out, register in, reconciled, superseded — the arc closes |
| **W8** | — | Defect fixes only. **Feature freeze at W8 day 1** | Demo polish, error copy, empty states | Evidence pack complete; two full rehearsals on the staging machine |

**W8 is not a build week.** It is freeze, evidence capture, and rehearsal. A team that plans to be coding in W8 will be coding in W8 and rehearsing once, on a laptop, the night before.

## 6.1 If a week is lost

The 14 use cases of Table 2 are the **floor**: drop one and the 40% claim fails arithmetically. Absorb slippage by cutting **depth, never a use case**, in this order:

1. Reference data (UC-04) reduces to the `IMPORT_COLUMN_MAP` editor plus read-only lists for the rest — the mapping editor is the one carrying AD-17.
2. Employee update (UC-09) reduces to the fields the worksheet consumes.
3. Attendance import (UC-13) reduces to the CSV template only; the `.xlsx` path is deferred.
4. Audit log viewer (UC-06) reduces to a filtered table with no export.
5. Styling stops. A plain, consistent, unstyled screen defends better than an inconsistent styled one.

**Never cut:** the refusal suite, the fidelity harness, the audit hash chain, or the atomicity of either import. Those four are the evidence; the screens are only the vehicle.

---

# 7. The demonstration

Fifteen minutes, twelve beats, rehearsed twice on the staging machine from the artifact — not on a developer laptop with a development server. Reset to the seeded demo state before each run.

| # | Beat | What the panel is meant to see | Ties to |
|---:|---|---|---|
| 1 | Sign in as Payroll Officer | A named account and a real session | UC-01, NFR-6.5 |
| 2 | Open a screen the role lacks a grant for | **Refused.** Then show the same refusal coming from the database constraint, not only the UI | UC-I3, FR-6.2, AC-6.2.4 |
| 3 | Employee list — 30 employees | Data entered once, available to every succeeding period | OBJ 1, P1 |
| 4 | Open one employee; change a rate with an effectivity date | A past period keeps the rate that was in force | FR-1.2, BR-08 |
| 5 | Deactivate an employee; show the record still exists | Deletion is deactivation | DR-2.4 |
| 6 | Import an attendance file **with one bad row** | Nothing at all is committed; the reject is reported by row and reason | FR-1.3, AC-1.3.1–2 |
| 7 | Create a payroll run for the period | The run exists in `Draft` and **holds no figures** | FR-2.6, AC-2.6.4 |
| 8 | Export the input worksheet; open it | Every column the accounting office needs is populated; nothing is re-keyed | UC-32, AC-2.11.2 |
| 9 | Import a register **whose net pay is one centavo off** | **Refused in full. The report names the row, the column, and the defect** | UC-18, AC-2.9.2 — *the moment of the demonstration* |
| 10 | Import a register missing one active employee | Refused. The report names the employee | AC-2.9.5 |
| 11 | Import the corrected register | Accepted. Lines written atomically, reconciliation result stored, run totals displayed and derived — never stored | FR-2.8, FR-2.9, FR-2.5 |
| 12 | Open import history, then the audit log | Version 1 refused, version 2 current, version 1 retained. Every action attributed and hash-chained | UC-33, FR-2.10, FR-6.1 |

**Beat 9 is the demonstration.** Everything before it is setup and everything after it is corroboration. If time runs short, cut beats 4, 5, and 10 — in that order — and never 9, 11, or 12.

**Rehearse the failure of the demo, too.** Have the seeded database, the four register files (clean, centavo-off, missing-employee, corrected), and a screen recording of all twelve beats on the machine before you walk in. A recording is not as good as a live run, but it is very much better than a live run that will not start.

---

# 8. Evidence to capture during the build

Implementation plan §6 insists evidence is captured as it is earned rather than reconstructed at the end. At pre-oral scale that means one folder, filled as the weeks pass.

| Evidence | Captured in | What it supports |
|---|---|---|
| The no-float proof — failing float assertion, passing decimal assertion | W1 | C-02, BR-40, AD-18. Answers the precision question before it is asked |
| Reconciliation refusal suite output — one seeded defect per register, each refused with the defect named | W3 | FR-2.9, and the whole of the system's remaining accuracy claim |
| NFR-2.12 fidelity run — 30 employees, file→database and database→file, agreeing to the centavo | W6–W7 | The one NFR fully evidenced at pre-orals |
| Negative permission test output — one per FR-6.2 grant | W3 | Separation of duty as evidence rather than assertion |
| Offline deployment rehearsal note — artifact, no network route, running | W1 | AD-16, C-03 |
| `AUDIT_LOG` extract showing an unbroken `prev_entry_hash` chain | W3 | FR-6.1, BR-35 |
| Migration run from empty on the staging machine | W8 | DR-1.6, and architecture §8.4's reproducibility claim |
| The four demo register files, kept under version control | W7 | Reproducibility of the demonstration itself |

---

# 9. What the demonstration must not claim

This is the highest-consequence page in this document. CR-01 risks **C1** and **C3** name these exact overclaims, and implementation plan §9 lists "the system is described as computing payroll" as a documentation risk that is *the easiest of all of them to commit by accident* — in a slide, in a screen label, or in an unrehearsed sentence under panel pressure.

| Do not say | Say instead |
|---|---|
| "The system computes payroll" | "The accounting office computes payroll in Excel. The system supplies the data it is computed on, receives the result, and proves that result is internally consistent and complete before it becomes the record" |
| "The system is accurate" / "eliminates computational error" | "The system verifies that an imported register agrees with itself and covers every employee. It cannot establish that a figure is correct, and we state that in FRS §10 and in the import result itself" |
| "Payroll without spreadsheets" | "Payroll without **re-encoding**. The spreadsheet stays; the re-keying between it and the records does not" |
| "40% of the system is done" | "42% of the use cases and 47% of the requirement items, with two declared partials — here is the table" |
| A screen or button labelled **Compute** | Label it **Import register**, **Export worksheet**, **Reconcile** |

**Check every screen label, slide title, and chapter heading against this table before the defense.** FR-2.9 behavior 6 already requires the system itself to state the limitation in the import result — put that sentence on screen during beat 11 and let the panel read it. A team that names its own boundary is not conceding a weakness; it is demonstrating that it understands what it built, which is the thing being examined.

---

# 10. Questions to expect, and where the answer already lives

| Question | Answer | Source |
|---|---|---|
| "Why doesn't your payroll system compute payroll?" | A client decision, taken formally, traced through all six baseline documents, with the module renamed rather than left standing over a function it no longer performs | [CR-01](./change-request-cr-01.md), FRS §2.2 |
| "Then what does it contribute?" | A governed intake boundary: single-entry data out, computed result in, arithmetic and completeness proved, provenance retained, everything after it under approval and audit | OBJ 2, matrix §P2 |
| "How do you know the payroll is correct?" | We do not, and we say so. We know it is internally consistent and complete. A register that is wrong in the accounting office's spreadsheet and internally consistent passes every check we have | FR-2.9 behavior 6, FRS §10, CR-01 **R1** |
| "Why 40% and not more?" | The slice is one complete arc of the cycle. Everything omitted is omitted because its input does not exist yet — M5 governs a run, M6 needs a finalized run, M7 needs stored records | §4.1, Table 4 |
| "What if the accounting office changes its register format?" | A row in `IMPORT_COLUMN_MAP`, maintained by an Administrator. No source change. Here is the mapping editor | AC-2.8.4, AD-17, C-01 |
| "How do you handle centavo rounding?" | We do not round, and we allow no tolerance. Monetary values are read as decimal strings and never pass through a float. Here is the test that proves it | BR-37, BR-40, AD-18, architecture §6.4 |
| "Where is the blockchain?" | Sprint 9, and out of this increment by design: it is additive, nothing depends on it, and it anchors finalized runs — of which this increment produces none | §4.1, implementation plan §2.1 |
| "Can one person both submit and approve?" | No, and it is refused in two places — the authorization service and a database constraint. That test is in the evidence pack | BR-28, architecture §6.2 |

---

# 11. Traceability of the slice

**Table 7.** *Problems and objectives the slice reaches*

| | Reached by the slice | Status at pre-orals |
|---|---|---|
| **P1** Manual data entry | FR-1.1, FR-1.2, FR-1.5, FR-1.3 (partial), FR-2.11 (partial) | **Substantially demonstrated.** Data entered once and carried into the worksheet |
| **P2** Uncontrolled handling of computed payroll | FR-2.5, FR-2.6, FR-2.8, FR-2.9, FR-2.10 | **Demonstrated.** The intake boundary is the slice's centre |
| **P3** Manual payslip generation | — | Not started. M6, Sprint 7 |
| **P4** Manual verification | — | Not started. M5, Sprint 6 |
| **P5** Manual record management | DR-2.1 foundations only | Schema exists; retrieval and reporting are M7, Sprint 8 |
| **P6** Risk of human error | FR-6.1, FR-6.2, NFR-6.4, NFR-6.5 | **Demonstrated** except FR-6.3, which is Sprint 9 |
| **OBJ 1** Data management | FR-1.1, FR-1.2, FR-1.5, FR-1.3 (partial) | Demonstrable |
| **OBJ 2** Verified payroll intake | FR-2.5, 2.6, 2.8, 2.9, 2.10, 2.11 (partial) | Demonstrable — the arc closes |
| **OBJ 3, 4, 5** | — | Out of the increment; §4.1 gives the reason for each |
| **OBJ 6** Evaluation | NFR-6.4 and NFR-6.5 evidenced; NFR-6.6 not administered | The ISO/IEC 25010 instrument is Sprint 10 and belongs to the final defense |

**Two of six objectives are demonstrable at pre-orals, and they are OBJ 1 and OBJ 2** — the two carrying P1 and P2, which are the problems this project exists for. That is the right two to have, and it is worth saying so rather than letting "two of six" stand on its own.

---

# 12. After the pre-oral defense

The slice is a re-cut of the project sequence, not a detour from it. Resuming costs nothing:

| Next | Picks up | Effect |
|---|---|---|
| Finish Sprint 4 | UC-14, UC-15, UC-16 | Completes FR-1.3 and FR-1.4, and closes the FR-2.11 leave partial |
| Finish Sprint 5 | UC-19, UC-I4 | Adjustments and exception evaluation |
| Sprint 6 onward | M5, M6, M7, integrity layer, hardening | Unchanged from [implementation-plan.md](./implementation-plan.md) §4.1 |

**Carry the panel's comments through baseline §5, not through the code.** If the defense produces a change to what the system must do, the authoritative document is named first and the change is traced outward — the route CR-01 itself took. A gap found at a defense is a baseline change, not a commit.

---

# 13. Change control

This document is not baselined. Revise it whenever the pre-oral plan changes.

It is also **disposable**: after the pre-oral defense it has no further authority, and [implementation-plan.md](./implementation-plan.md) resumes as the single plan of record. Do not let a schedule written for a demonstration outlive the demonstration.
