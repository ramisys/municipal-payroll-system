# Change Request CR-01 — Payroll computation retained by the accounting office

**Project:** Payroll Management System
**Document:** Change request against Baseline B1
**Version:** 1.0
**Date:** August 30, 2026
**Status:** ✅ **Approved and applied — Baseline B2, August 30, 2026.** All six baseline documents revised, verification re-run, 0 failed. See [baseline.md](./baseline.md).
**Procedure:** [baseline.md](./baseline.md) §5

> **Applied with OI-12 and OI-13 still open.** §9 recommended answering both before the rewrite. The client directed that the revision proceed, so the design was shaped to make each answer **configuration rather than rework**: `AD-17` puts the register's column layout in an `IMPORT_COLUMN_MAP` row, and `FR-2.3` is retained in reduced form on the assumption that the register carries no employer shares — the recoverable direction of that bet. Both remain open in FRS §11 and baseline §4. **A sample register file is still required before FR-2.8 can be finished.**
>
> `FR-2.11` was included, per §10. `OI-14` was resolved by assumption — 13th-month pay computed by the accounting office and imported as a distinct run type — and `BR-21` retired on that basis; if the client says otherwise, `BR-21` returns.

| | |
|---|---|
| Requested by | Client |
| Request | The accounting office continues to perform the payroll computation; the system does not compute |
| Intake method confirmed | Import of a completed payroll register produced by the accounting office |
| Actor | No new actor — the accounting office operates the system as the existing Payroll Officer |
| Baseline documents affected | **6 of 6** |
| Requirements retired | 4 · reworded 3 · added 5 |
| Objectives requiring restatement | **OBJ 2** (and P2, the problem it answers) |

---

## 1. What is being changed

The client has decided that payroll computation stays with the accounting office. The accounting office will continue to compute in Excel and will hand the system a **completed payroll register** — one row per employee, carrying earnings, deductions, and net pay as final figures. The system will validate that register, store it, route it for approval, generate payslips and reports from it, and anchor it for tamper-evidence.

**The system derives no monetary figure.** Net pay, gross pay, statutory deductions, and premium pay all arrive as values rather than being produced.

This is not a configuration change or a deferral. It removes the module that Baseline B1 treats as the system's centre of gravity, and it invalidates one of the six specific objectives as currently written. The purpose of this document is to state exactly what that costs and exactly what must change, so the decision is made with the cost visible rather than discovered during the rewrite.

### 1.1 Which document is authoritative for this change

Baseline §5 step 1 requires this to be answered before anything is edited, and the answer here is unusual.

**The [Problem-to-Requirements Matrix](./problem-requirements-matrix.md) is authoritative.** This is a Chapter I change, not a Chapter III one. The matrix reads in one direction — a problem produces requirements — and the change does not remove a requirement, it removes a **problem**: `P2 — Dependence on Excel for computation` is no longer a problem this system solves. Every other document then follows from the restated matrix.

Attempting this change from the FRS downward would leave the matrix asserting that the system replaces steps F1–F5 of the client's workflow, which it will not. That contradiction is exactly the class of defect the baseline exists to prevent.

---

## 2. What the system becomes

| | Baseline B1 | Under CR-01 |
|---|---|---|
| **Core claim** | The system computes payroll correctly | The system makes an externally computed payroll **controlled, verifiable, and documented** |
| **Boundary of automation** | Inputs → computation → outputs | Inputs → *(external computation)* → intake → outputs |
| **Strongest evidence** | 100% agreement with manual computation over 30 employees × 3 periods | Transcription fidelity, reconciliation integrity, and the approval / audit / anchor chain |
| **What Excel remains** | Nothing — replaced | The computation medium, permanently |

The revised process boundary:

```
  System                    Accounting office              System
┌──────────────────┐      ┌───────────────────┐      ┌──────────────────────────┐
│ employee data    │      │                   │      │ import register          │
│ attendance       │─────▶│ compute in Excel  │─────▶│ reconcile arithmetic     │
│ leave            │      │                   │      │ validate exceptions      │
│ (input worksheet)│      │                   │      │ approve · lock · anchor  │
└──────────────────┘      └───────────────────┘      │ payslips · reports       │
        FR-2.11                                      └──────────────────────────┘
     (see §6, R2)                                       FR-2.8 · 2.9 · 2.10
```

The left-hand box is the part of this change most easily missed, and §6 R2 explains why it is not optional.

---

## 3. Requirement disposition

Dispositions are carried in a column **adjacent** to the identifier, never inside the identifier cell — baseline §5 and verification check 15.

### 3.1 The P2 cluster

**Table 1.** *Disposition of every requirement in the P2 cluster*

| ID | Title | Disposition | Consequence |
|---|---|---|---|
| FR-2.1 | Basic pay computation | **Retire** | Basic pay arrives as an imported column |
| FR-2.2 | Additional pay computation | **Retire** | Overtime, night differential, and premiums arrive as imported columns |
| FR-2.3 | Statutory deduction reference tables | **Retire — conditional** | Depends on OI-13 (§7). If the register omits **employer shares**, the remittance reports of FR-5.3 cannot be produced without retaining part of this requirement |
| FR-2.4 | Adjustments and other deductions | **Reword — reduced** | Ceases to *apply* deductions; retains *recording* an adjustment as an auditable line and decrementing a loan balance against it |
| FR-2.5 | Net pay determination | **Reword — inverted** | Becomes a net pay **integrity check** on the imported row. `AC-2.5.1` ("no interface path exists by which net pay can be hand-entered") reverses outright — net pay is now necessarily a received value |
| FR-2.6 | Payroll run lifecycle | **Reword — retained** | Lifecycle, one-run-per-period-and-type rule, population scope, and audit all survive. Behavior step 2 changes from "executes the computation of FR-2.1 through FR-2.5" to "loads one payroll line per row of the imported register" |
| NFR-2.7 | Computational accuracy | **Retire — replaced** | Replaced by NFR-2.12 (Table 2). The system has no computation to be accurate about |

`FR-2.5` is the single most consequential entry in this table. `AC-2.5.1` is currently one of the design's strongest controls — it is the guarantee that a net pay figure cannot be typed by a human. Under CR-01, every net pay figure in the system is typed by a human, in Excel, outside the system's reach. The replacement control is arithmetic reconciliation (FR-2.9), which is a genuine control but a different and weaker one: it proves the row is internally consistent, not that it is right.

### 3.2 Requirements to be added

Identifiers continue the P2 sequence. None is reused and none is renumbered — `NFR-2.7` occupies `.7`, so new items begin at `.8`.

**Table 2.** *New requirements required by CR-01*

| ID | Title | Why it is required |
|---|---|---|
| FR-2.8 | Computed payroll register import | The intake path itself: template definition, column mapping, parse, per-row validation, error report, atomic load. Nothing enters the system without it |
| FR-2.9 | Import reconciliation and arithmetic integrity | Row level: gross = Σ earnings; total deductions = Σ deductions; net = gross − total deductions. File level: control totals in the file agree with the totals of the loaded rows. Employee number matched against FR-1.1; an unmatched number is blocking. This is the whole of the system's remaining accuracy claim |
| FR-2.10 | Import versioning and supersession | Every import retained with its file hash, importing user, and timestamp; a corrected re-import supersedes but does not erase. Without this, FR-6.3 can anchor a run but cannot prove **which file produced it** |
| FR-2.11 | Payroll input worksheet export | The system exports employee, compensation, attendance, and leave data in the form the accounting office computes on. See §6 R2 — without this, P1 partially returns and OBJ 1 is undermined |
| NFR-2.12 | Transcription fidelity | Replaces NFR-2.7 as the measurable accuracy claim: every stored value equals the source-file value to the centavo, and a stored run re-exports identically to what was imported. Verified over the same ≥ 30 employees × 3 periods, so the Chapter IV sample design survives |

`FR-2.11` is the requirement most likely to be omitted as "not what the client asked for", and the one whose omission does the most damage. It is included here as a recommendation, not an assumption — OI-15 (§7) puts it to the client.

### 3.3 Requirements affected outside the P2 cluster

**Table 3.** *Collateral effects on requirements the change does not target*

| ID | Effect | Detail |
|---|---|---|
| FR-4.1 | **Rewrite exception rules** | `EX-05` (no statutory schedule in force) retires with FR-2.3. `EX-03` and `EX-04` become checks on imported data rather than computed data — and become **more** load-bearing, since they are now the primary defence. New exception codes are needed for import-specific failures: unmatched employee, missing column, non-numeric value, row total mismatch, duplicate employee row |
| FR-4.1 | **Dangling acceptance criterion** | `AC-4.1.4` measures its 15% threshold "over the parallel-run set of NFR-2.7". When NFR-2.7 retires, this reference dangles — verification check 1 would fail. It must be re-anchored to NFR-2.12's set |
| FR-4.3 | **Degraded** | "Targeted correction and recomputation" cannot recompute what it did not compute. A wrong figure now round-trips to the accounting office and returns as a corrected import or an adjustment line. The `H → I → F` loop the requirement was written to replace is **partially restored, outside the system**. OBJ 4's claim narrows accordingly |
| FR-5.3 | **At risk** | The per-agency remittance reports (SSS, PhilHealth, Pag-IBIG, BIR) require employer shares under BR-20. Employer shares affect no net pay and so may well be absent from the accounting office's register. If absent, they must either be added to the register or computed by a retained fragment of FR-2.3. **Unresolved until OI-13** |
| FR-6.3 | **Claim narrows** | Ledger anchoring continues to work unchanged, but what it attests to changes: it proves the integrity of *what was imported and approved*, not of *what was computed correctly*. Chapter IV must state the narrower claim |
| DR-2.2 | **Reword** | "Computed lines reference their versions" — the statutory schedule version disappears; the import version (FR-2.10) and the compensation profile version take its place |

---

## 4. Business rule disposition

Of 36 rules, the computation rules are the ones that go. The precision, records, audit, and access rules are untouched.

**Table 4.** *Business rules by disposition*

| Disposition | Rules | Note |
|---|---|---|
| **Retire** | BR-02, BR-05, BR-11, BR-13, BR-15, BR-16, BR-17, BR-19, BR-21, BR-22 | Rate derivation, semi-monthly basic pay, absence/tardiness/undertime formulas, computation order, premium and overtime multipliers, night differential, taxable compensation base, 13th-month formula, recurring deduction application. Each states a formula the system no longer evaluates |
| **Retire — conditional on OI-13** | BR-14, BR-20 | Statutory schedule effectivity, and employer share computation. Both survive only if FR-2.3 is partially retained for remittance reporting |
| **Reword — from derivation to verification** | BR-18, BR-23, BR-25 | `BR-18` (gross = sum of earning lines) becomes an **import integrity check** rather than a summation the system performs. `BR-23` (loan amortization) becomes recording and balance decrement, not amount determination. `BR-25` (net pay floor) becomes an import validation |
| **Retain unchanged, elevated in importance** | BR-01 | Two decimal places, half-up rounding, no binary floating point. **This rule becomes more critical, not less** — see §6 R5 |
| **Retain unchanged** | BR-03, BR-04, BR-06 – BR-10, BR-12, BR-24, BR-26 – BR-36 | Attendance derivation, employee data, leave, taxability flags, finalized-run immutability, and the entire audit / access / integrity set |
| **Add** | 4–6 new rules | Import reconciliation tolerance (exact, not approximate), employee matching, file hash retention, import supersession, and column mapping stability |

`BR-13` deserves a specific note. It fixes the computation order and states *why*: withholding tax depends on the mandatory contributions computed before it. That reasoning was the design's clearest statement of why payroll computation cannot be reordered casually. It leaves the system entirely under CR-01 — and it leaves with no replacement, because the ordering now happens in a spreadsheet the system cannot inspect.

---

## 5. Model, data, and architecture impact

**Table 5.** *Impact by baseline document*

| Document | What changes |
|---|---|
| **Problem-to-Requirements Matrix** | `P2` restated (§8); `OBJ 2` restated; Table 2 rewritten; traceability grid re-derived; the scope table's "18 workflow steps absorbed" reduced — steps **F1–F5 return to the accounting office**; the closing counts all move |
| **Functional Requirements Specification** | §1.2 scope — "Payroll computation" moves from **In scope** to **Out of scope**; module **M4** renamed from *Payroll Computation* to *Payroll Intake* and rewritten; §5 NFR table; §7 rules per Table 4; §8.1 entity inventory; §9 Table 8 re-derived; §10 acceptance plan — the "Unit" row ("individual computations against BR-01 through BR-25") loses most of its content and the "Parallel run" row is replaced; §11 open items per §7 below |
| **Use Case Model** | `UC-18` retitled *Compute payroll run* → *Import computed payroll register*, flows rewritten. `UC-I5` (apply statutory schedule) retires. `UC-05` (maintain statutory schedule) retires conditionally. `UC-19` reduced. `UC-22` reworked — correction is by re-import or adjustment line, not recomputation. New use cases for input worksheet export and import history. Figures 2, 4, 6, and the main activity flow redrawn; Table 3 must remain identical to FRS Table 8 (verification check 3) |
| **Behavioral Diagrams** | The computation sequence diagram is replaced by an import sequence. **The run state machine survives intact** — six states, six transitions, unchanged; it is the most heavily verified artifact in the baseline and this change does not touch it. §1.4's component naming updates with the architecture |
| **Data Model** | `STATUTORY_SCHEDULE` and `STATUTORY_BRACKET` retire, conditional on OI-13. New `PAYROLL_IMPORT` entity: file hash, version, importing user, timestamp, row count, control totals. `PAYROLL_LINE` gains an import reference; its columns keep their shape but change meaning from *derived* to *received*. `DEDUCTION_LINE.statutory_schedule_id` gives way to import provenance. Subject-area counts, the master ERD, and §8's requirement accounting all re-derive |
| **System Architecture** | `ComputationEngine` retires, replaced by `RegisterImportService`. `StatutoryScheduleService` and `StatutoryRepository` retire conditionally. §6.1 (C-01 — statutory logic without recompile) retires with them. **`AD-04` and `AD-05` must be re-argued** — see below. `AD-07` (BCMath) is retained and reframed. §5.1, §5.2, §12 traceability, and §13's "write the parallel-run harness early" all move |
| **Implementation Plan** | Derived, not baseline. Rebuilt after the six above are settled — the M4 build phase changes character entirely |

**On AD-04 and AD-05.** These two architectural decisions exist for a reason the change removes, and they say so explicitly. `AD-04` (four layers) states that **"the decisive factor is NFR-2.7: the engine must be drivable by a test harness with no HTTP and no database"** — CR-01 retires both NFR-2.7 and the engine, so the decisive factor is gone in full, not weakened. `AD-05` (repository interfaces over Eloquent) is justified *entirely* by keeping `ComputationEngine` free of persistence concerns so the parallel run can drive it with fixtures. With the engine gone, both rationales evaporate. Neither decision is necessarily wrong afterward — a layered design and testable import services are still defensible — but **both must be re-argued from new grounds rather than carried forward silently.** An architectural decision whose stated rationale no longer holds is a defect regardless of whether the decision itself is sound.

### 5.1 Projected counts

These are projections, not verified figures. Baseline §5 step 3 requires the §3 verification suite to be re-run during the rewrite; the figures below are what it should produce.

**Table 6.** *Count deltas*

| Figure | B1 | Projected | Δ |
|---|---:|---:|---:|
| Matrix requirements | 35 | 36 | +1 |
| FRS functional requirements | 30 | 31 | +1 |
| Gated NFR | 8 | 8 | 0 |
| Data requirements | 5 | 5 | 0 |
| Traceable requirement items | 43 | 44 | +1 |
| Business rules | 36 | ~30 | −6 |
| Acceptance criteria | 135 | ~125 | ~−10 |
| Exception rules | 10 | ~13 | +3 |
| Use cases (primary + included) | 31 + 6 | ~31 + 5 | −1 |
| Entities | 37 | ~36 | −1 |
| Architecture components | 35 | ~33 | −2 |
| Architectural decisions | 16 | 16 | 0 (two re-argued) |
| Problems | 6 | 6 | 0 (one restated) |

The requirement count rising while the system does materially less is not an anomaly. Computation was seven requirements doing a great deal; intake and reconciliation is more requirements doing less, because a boundary the system used to own must now be defended at its edge.

---

## 6. What this costs, and the risks it introduces

Stated plainly, because these are the grounds on which the decision should be made.

**Table 7.** *Costs and risks*

| # | Item | Detail |
|---|---|---|
| **C1** | **OBJ 2 becomes false as written** | "Develop a computation module that determines basic pay, additional pay, adjustments, statutory deductions, and net pay **without the use of spreadsheets**." Spreadsheets remain the computation medium. The objective must be restated, not adjusted — §8 proposes wording |
| **C2** | **P2 is no longer a problem the system solves** | The unprotected, uncontrolled, per-period spreadsheet formulas remain exactly as Chapter I describes them. §8 recommends restating P2 to a problem the system *does* solve rather than deleting it — a deleted problem is harder to defend before a panel than a narrowed one |
| **C3** | **The strongest Chapter IV number is lost** | NFR-2.7's 100% agreement across 30 employees × 3 periods was the most quantitative claim in the baseline. NFR-2.12 (transcription fidelity) is verifiable and uses the same sample design, but it evidences faithful copying rather than correct computation |
| **C4** | **OBJ 4's claim narrows** | FR-4.3 targeted correction degrades (Table 3). A correction now leaves the system and comes back |
| **C5** | **The 18-of-20 workflow absorption claim drops** | Steps F1–F5 return to the accounting office. The matrix's scope figure and the Chapter I efficiency argument both move |
| **R1** | **Accuracy is now bounded by the spreadsheet** | FR-2.9 reconciliation proves a row is internally consistent. It **cannot** detect a figure that is wrong but consistent — a wrong overtime multiplier applied uniformly reconciles perfectly. This is the honest limit of the design under CR-01, and Chapter IV must state it rather than imply otherwise |
| **R2** | **P1 partially returns without FR-2.11** | If the system holds employee, rate, attendance, and leave data but does not export it, the accounting office re-keys it into Excel every period. That is the manual re-encoding P1 exists to eliminate, reintroduced at a new point in the cycle. **FR-2.11 is the mitigation and it is not optional in practice** |
| **R3** | **FR-6.3's guarantee attests to something narrower** | Anchoring still detects alteration of stored records. It no longer sits downstream of a computation the system performed, so it evidences the integrity of a received figure. The mechanism is unchanged; the claim is smaller |
| **R4** | **The import becomes a single point of failure** | The entire payroll now enters through one file parser. A column added, renamed, or reordered in the accounting office's workbook breaks the cycle. Column mapping must be configurable and versioned rather than fixed in code — this is the same argument `C-01` made for statutory rates, applied to a new surface |
| **R5** | **`BR-01` becomes the most fragile rule in the system, and loses the test that guards it** | Parsing decimal strings out of a spreadsheet is precisely where a binary float is introduced. Architecture §6.4 already names "a stray `(float)`" as the one implementation slip that breaks BR-01 — and states that **"the parallel run of 30 employees across three periods is the test that would catch it."** That parallel run is NFR-2.7, which CR-01 retires. So the slip moves from the computation path to the **parse path**, where it is easier to make and harder to notice, at the same moment its detection mechanism is removed. NFR-2.12 must be specified to close this gap deliberately, and `AD-07` (BCMath) reframed to cover parsing and storage, not just arithmetic. **This is the most easily overlooked consequence in this change request** |

---

## 7. Open items

### 7.1 Closed by this change

Three open items disappear with the computation module. All three were blocking the same module.

| ID | Question | Why it closes |
|---|---|---|
| OI-02 | Pay frequency — semi-monthly or monthly | Was needed for BR-05 and BR-19. Both retire. Still relevant to FR-0.3 period definition, but no longer a computation input |
| OI-03 | Which day factor — 261, 313, 365 | Was needed for BR-02 alone. BR-02 retires |
| OI-05 | Mix of pay bases across the workforce | Was needed for FR-2.1. FR-2.1 retires |

### 7.2 Opened by this change

| ID | Question | Affects | Priority |
|---|---|---|---|
| **OI-12** | What is the exact column layout of the accounting office's payroll register — every column, its meaning, its format, and whether the layout is stable across periods? **A sample file for a real period is required, not a description.** | FR-2.8, FR-2.9, R4 | **Blocking.** This is now the highest-priority open item in the project. FR-2.8 cannot be specified without it, and it replaces OI-03 and OI-05 as the item development waits on |
| **OI-13** | Does the register carry **employer shares** of SSS, PhilHealth, and Pag-IBIG? | FR-2.3, FR-5.3, BR-14, BR-20 | **High.** Decides whether FR-2.3 fully retires or partially survives. If the answer is no and no fragment is retained, **the remittance reports of FR-5.3 cannot be produced** — which would be a second, unintended reduction in scope |
| **OI-14** | Is 13th-month pay also computed by the accounting office, or is it expected from the system? | BR-21, FR-5.3 | Medium |
| **OI-15** | Should the system export the input worksheet the accounting office computes on (FR-2.11)? | FR-2.11, OBJ 1, R2 | **High.** See R2 — the answer decides whether OBJ 1 survives intact |
| **OI-16** | Who performs the import, and does the FR-6.2 permission matrix need an import permission distinct from the run-creation permission? | FR-6.2, FR-2.8 | Low — the actor decision (accounting office = Payroll Officer) is settled; only the permission granularity is open |

`OI-11` (ledger administration) is unaffected and remains the sole outstanding *design* question.

---

## 8. Proposed restatement of P2 and OBJ 2

Offered as a starting point for the Chapter I revision, not as a decided wording.

**P2 as it stands** — *Dependence on Excel for computation. Payroll is computed in spreadsheets whose formulas are unprotected, uncontrolled, and copied or rebuilt each period.*

**P2 proposed** — *Uncontrolled handling of computed payroll. Payroll is computed in spreadsheets, and the resulting figures are then transcribed, circulated, approved, and filed by hand with no verification that the register was carried forward intact, no record of which version of it was approved, and no protection against later alteration.*

This keeps the problem inside the client's own account of their process, keeps it answerable by requirements the system will actually implement, and does not claim the spreadsheet has been replaced. It also preserves the six-problem structure, so the six-objective / six-conclusion correspondence that Chapter V depends on is not disturbed.

**OBJ 2 as it stands** — *Automated computation. Develop a computation module that determines basic pay, additional pay, adjustments, statutory deductions, and net pay without the use of spreadsheets.*

**OBJ 2 proposed** — *Verified payroll intake. Develop a module that imports the accounting office's computed payroll register, verifies its arithmetic integrity and completeness against the employee master file, and establishes it as the controlled system of record for approval, issuance, and reporting.*

Measurable, testable by NFR-2.12 and FR-2.9, and true of the system that will be built.

**A note on revising Chapter I twice.** The matrix already carries one post-analysis change — `FR-6.3`, marked ✦ — and defends it on the grounds that it answers a problem the matrix already identified and that its provenance is stated rather than absorbed. CR-01 is a different kind of change: it is a **client decision that narrows the system's scope**, not a design-review finding. That is the more defensible of the two kinds, and it should be recorded as such — dated, attributed to the client, and marked in the matrix the way ✦ is — rather than folded silently into a revised P2. A matrix that shows a scope decision and its date is a record of the analysis. One that reads as though the accounting office was always going to compute is a record of the system.

---

## 9. If this is approved

Sequence, following baseline §5:

1. **Answer OI-12 and OI-13 first.** FR-2.8 and FR-2.9 cannot be written without a sample register, and OI-13 decides whether two entities, one service, one repository, one use case, and an architecture section retire or survive. Rewriting before these are answered means rewriting twice.
2. **Restate P2 and OBJ 2 in the matrix** (§8), with the change dated and attributed. Everything else derives from this.
3. **Rewrite the FRS** — M4, §1.2 scope, §5, §7 rules, §9 Table 8, §10, §11.
4. **Rewrite the use case model** against the revised FRS, holding Table 3 identical to FRS Table 8 (verification check 3).
5. **Redraw the affected behavioral diagrams.** The run state machine is not among them.
6. **Revise the data model**, then the architecture — including re-arguing AD-04 and AD-05 from new grounds (§5).
7. **Re-run the §3 verification suite** — all 30 chain checks and 22 architecture checks — and reconcile against Table 6.
8. **Update every change log, re-stamp as Baseline B2**, and record CR-01 as its cause.

Steps 2 through 7 are one indivisible piece of work. A partial application would leave the matrix claiming the step F1–F5 automation that the FRS no longer specifies, which is precisely the six-documents-drifting-in-six-directions failure the baseline was frozen to prevent.

---

## 10. Recommendation

**Proceed, with FR-2.11 included and OI-12 and OI-13 answered first.**

The change is the client's to make and it is a reasonable one — an accounting office that owns payroll computation and is confident in its spreadsheets is a common arrangement, and a system that refuses to accept it is a system that will not be used. The design survives it: the approval workflow, period locking, audit trail, role separation, payslip generation, records, reporting, and ledger anchoring are all untouched, and they were always the larger half of the baseline by requirement count.

Two conditions make the difference between a narrowed system and a diminished one:

- **FR-2.11 must be built** (OI-15). Without the input worksheet export, the accounting office re-keys from the system into Excel every period, and OBJ 1 — the objective this change does not touch — is quietly undermined anyway.
- **OI-13 must be answered before the rewrite.** If the register carries no employer shares and no fragment of FR-2.3 is retained, FR-5.3's remittance reports become unbuildable. That would be a second scope reduction, unrequested and discovered late.

What should not be done is to leave OBJ 2 standing and describe the import as "computation". The panel will read `AC-2.5.1` — *no interface path exists by which net pay can be hand-entered* — against a system whose net pay figures all come from a spreadsheet, and the contradiction will be found. Restating the objective honestly is both more defensible and, on the evidence of §8, still a real objective worth building.

---

## 11. Change log

| Version | Date | Author | Change |
|---|---|---|---|
| 1.0 | August 30, 2026 | — | Initial change request. Raised on the client's decision that the accounting office continues to perform payroll computation. Intake method confirmed as import of a completed payroll register; accounting office confirmed as the existing Payroll Officer actor. No baseline document modified. |
| 1.1 | August 30, 2026 | — | **Approved and applied as Baseline B2.** All six baseline documents revised per §9; implementation plan rebuilt afterward from the revised baseline. Verification re-run: 34 chain checks + 22 architecture checks, 0 failed, including four new checks (21–24) added to catch references to retired identifiers and architectural rationales invalidated by the change. Applied with `OI-12` and `OI-13` open, under the assumptions recorded in the status note above. Reconciliation against the Table 6 projections is in §11.1. |

## 11.1 Projections versus outcome

§5.1 stated its counts as projections to be reconciled when the verification suite was re-run. It was, and they were. Recorded here rather than quietly corrected, because a projection that is never checked is worth nothing.

| Figure | Projected | Actual | Why it differs |
|---|---:|---:|---|
| Matrix requirements | 36 | **37** | `FR-2.11` was recommended in §3.2 but not counted in the projection; it was built |
| FRS functional requirements | 31 | **32** | Same cause |
| Traceable requirement items | 43 → 44 | **45** | Same cause |
| Business rules | ~30 | **31 live** (+10 retired, retained by number) | Five new rules were needed rather than the four to six estimated; the retired ten keep their numbers, which the projection did not state |
| Acceptance criteria | ~125 | **156** | The projection was wrong by a wide margin and in the wrong direction. Verification of received data needs *more* criteria than derivation of it: every check that can refuse a register is a separate pass/fail, and FR-2.8, FR-2.9, and FR-2.10 alone carry nineteen |
| Exception rules | ~13 | **13** | Four added, `EX-09` retired |
| Use cases | ~31 + 5 | **33 + 7** | The projection assumed `UC-I5` and `UC-05` would retire with `FR-2.3`; both survived, and `UC-32`, `UC-33`, and `UC-I7` were added |
| Entities | ~36 | **39** | The projection assumed `STATUTORY_SCHEDULE` and `STATUTORY_BRACKET` would retire; they did not, and `IMPORT_COLUMN_MAP` was not foreseen |
| Architecture components | ~33 | **38** | Same two causes, plus `WorksheetExportService` and `ImportRepository` |
| Architectural decisions | 16 | **18** | `AD-17` and `AD-18` were needed to answer risks **R4** and **R5**, which the projection identified but did not cost |

**The pattern in the misses is worth naming.** Every projection that was wrong was wrong in the same direction: the change was assumed to shrink the system, and it did not. It shrank what the system *does* — one module's worth of derivation — while enlarging what the system must *check*, *record*, and *prove*. §5.1 anticipated this in one sentence and then under-applied it: *"a boundary the system used to own must now be defended at its edge."* Defending a boundary costs more artifacts than owning it, and the acceptance-criteria count is where that shows most sharply.

**The conditional retirements were the other systematic error.** Three projections assumed `FR-2.3` would retire and counted its entities, components, and use cases out. The decision under an unanswered `OI-13` was to retain it — the recoverable direction — so all of them stayed. A projection made while an open item is genuinely open should carry both figures, not the likelier one.
