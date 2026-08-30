# Baseline B2

**Project:** Payroll Management System
**Baseline:** B2 — frozen August 30, 2026
**Supersedes:** B1 — frozen August 30, 2026
**Cause:** [CR-01](./change-request-cr-01.md) — payroll computation retained by the accounting office
**Scope:** Problem Matrix → FRS → Use Case Model → Behavioral Diagrams → Data Model → System Architecture
**Verification:** 34 chain checks + 22 architecture checks, **0 failed**

---

## What a frozen baseline means here

Every document below is internally consistent, consistent with every other document, and verified mechanically. From this point a change to any one of them is a change to the baseline: it needs a reason, it needs the other five checked against it, and it needs the version and change log updated.

This is not a claim that the design is finished. It is a claim that the design is **coherent** — that no two documents contradict each other, no identifier dangles, and no count is wrong.

**B2 is the result of that procedure being exercised in earnest.** B1 was frozen on August 30, 2026 and the client's decision arrived the same day: the accounting office continues to perform the payroll computation, and the system receives its result instead. That removed the module B1 treated as its centre of gravity. §7 records what the change cost and what it did not touch.

---

## 1. Contents

| Document | Version | Words | Lines | SHA-256 (first 16) |
|---|:---:|---:|---:|---|
| [Problem-to-Requirements Matrix](./problem-requirements-matrix.md) | 1.2 | 4,345 | 235 | `fb64aaf83afb0223` |
| [Functional Requirements Specification](./functional-requirements-specification.md) | 1.2 | 21,253 | 1,582 | `e6c947cb12cfc302` |
| [Use Case Model](./use-case-model.md) | 1.2 | 19,206 | 2,092 | `c60adce4e32efc1d` |
| [Behavioral Diagrams](./behavioral-diagrams.md) | 1.2 | 6,565 | 865 | `d071b4e9707d8f8b` |
| [Data Model](./data-model.md) | 1.2 | 8,787 | 1,086 | `8b18e0deac1c761c` |
| [System Architecture](./system-architecture.md) | 1.2 | 11,458 | 953 | `be6bf9fd4e7d6988` |

The hashes are here so that a later reader can tell whether a document has moved since the freeze. Recompute with `sha256sum` and compare the first sixteen characters.

**These hashes are over LF-terminated files**, as `.gitattributes` pins them. A working copy with CRLF line endings will not reproduce them — which is not hypothetical: the B2 rewrite produced CRLF endings in five of the six documents on a Windows machine, and the first hash table written for this baseline was wrong for exactly that reason. Verification check 25 now catches it.

**Supporting records, not part of the baseline:** [CR-01](./change-request-cr-01.md), the change request that caused this baseline, which records the impact analysis, the costs accepted, and the alternatives considered. It is retained rather than superseded, because the reasoning behind a scope reduction is not recoverable from the result. Two earlier consistency audit rounds — 22 findings then 8 — were run against B1 and resolved in full; their findings live in the change logs of the documents they corrected.

**Derived from the baseline, not part of it:** the [Implementation Plan](./implementation-plan.md), which fixes build order only. It adds no requirement and changes no design decision; where the two disagree, this baseline is authoritative.

---

## 2. The numbers this baseline fixes

Every figure below was extracted from the documents themselves, not transcribed. Figures that moved from B1 are marked ✧.

### Requirements

| | | |
|---|---|:---:|
| Matrix | 28 FR + 8 NFR + 1 DR = **37 requirements** | ✧ |
| FRS | **32 FR** (28 matrix-traced + 4 foundation `FR-0.x`) | ✧ |
| | **8 gated NFR**, plus 4 ungated quality expectations (NFR-7.1–7.4) | |
| | **5 DR** (DR-1.6 structural + DR-2.1–2.4) | |
| **Traceable requirement items** | **45** — identical sets in FRS Table 8 and use-case Table 3 | ✧ |
| Business and computation rules | **31 live**, 10 retired and retained by number (FRS §7.8) | ✧ |
| Acceptance criteria | **156** | ✧ |
| Exception rules | **13** (8 blocking / 5 warning), `EX-09` retired | ✧ |

### Model

| | | |
|---|---|:---:|
| Use cases | **33 primary + 7 included** | ✧ |
| Actors | 4 human + 1 supporting (`System Clock`) | |
| Modules | 7 (M1 – M7), **M4 renamed *Payroll Intake*** | ✧ |
| Entities | **39** in 6 subject areas | ✧ |
| Relationships | **53** in the master ERD | ✧ |
| Architecture components | **38** across 4 layers | ✧ |
| Architectural decisions | **18** (AD-01 … AD-18), two of them re-argued | ✧ |
| Diagrams | 8 use case · 8 behavioral · 8 data · 6 architecture = **30** | |

### Stack, as fixed by architecture §9

Ubuntu Server 24.04 LTS · Nginx 1.24 · PHP 8.3 with BCMath · Laravel 11 · Blade + Alpine 3 · MySQL 8.4 LTS · DomPDF · PhpSpreadsheet · Hyperledger Besu 24.x with QBFT · PHPUnit.

Unchanged by CR-01. PhpSpreadsheet, already present for attendance import, now also carries the register import and worksheet export.

---

## 3. What is verified

Run `python verify.py` from the repository root to reproduce.

| # | Check | Result |
|---|---|---|
| 1 | Every `FR`, `NFR`, `DR`, `AC`, `BR`, `EX`, `OI`, `UC`, `C`, `A`, `R`, `P`, `AD` reference resolves | ✅ 0 dangling |
| 2 | Matrix, FRS, and use-case arithmetic reconcile to the same 45 items | ✅ |
| 3 | FRS Table 8 and use-case Table 3 hold identical identifier sets | ✅ 45 = 45 |
| 4 | ✧ Business rule number line `BR-01`…`BR-41` complete — 31 live, 10 retired, no gap and no reuse | ✅ |
| 5 | Run lifecycle: every FR-4.4 transition is an arrow in Figure 7 | ✅ 6 of 6 |
| 6 | Six run states identical in FRS, state machine, and `run_status` enum | ✅ |
| 7 | Behavioral flow coverage matches the document's own claim | ✅ 28 of 32, 4 named as undrawn |
| 8 | Every cross-module trace marked († or ‡) | ✅ |
| 9 | No use case deferrable while its requirement is `Must` | ✅ |
| 10 | Every FR-6.2 grant has a use case path | ✅ |
| 11 | Master ERD entity names match §5.1 exactly | ✅ 39 = 39 |
| 12 | Subject-area counts sum to the master ERD | ✅ |
| 13 | Data model §8 accounts for all 45 requirement items | ✅ |
| 14 | Every `INTEGRITY_ANCHOR.scope_type` value has a foreign key | ✅ |
| 15 | No identifier cell carries a marker or strikethrough | ✅ |
| 16 | Prose citations of diagram step numbers in range and correct | ✅ |
| 17 | Markdown tables well-formed across all six documents | ✅ 0 malformed |
| 18 | Mermaid fences balanced, 30 diagrams | ✅ |
| 19 | Architecture: 38 components, 17 of them named verbatim in behavioral §1.4 | ✅ |
| 20 | Architecture quotations resolve to text that exists in a source document | ✅ |
| 21 | ✧ **No live reference to a retired requirement** — `FR-2.1`, `FR-2.2`, `NFR-2.7` | ✅ |
| 22 | ✧ **No `Rules` line cites a retired business rule** | ✅ |
| 23 | ✧ **No live reference to `EX-09` or to `ComputationEngine`** | ✅ |
| 24 | ✧ **Every architectural decision's stated rationale is still true** | ✅ AD-04, AD-05, AD-07 re-argued; see architecture §10.1 |
| 25 | ✧ **Every baseline document is LF-terminated and its §1 hash reproduces** | ✅ 6 of 6 |

Checks 14, 15, and 20 exist because each caught a real defect in B1: an enum value with no column able to hold it, a marker inside an identifier cell that silently dropped the row from every count, and a quotation of wording that had since been rewritten.

✧ **Checks 21 to 25 are new, and they exist for the same reason.** A change that retires identifiers creates a failure mode B1 never had: a requirement, rule, or component that no longer exists but is still cited somewhere as though it did. Checks 21 – 23 caught eight such references during the CR-01 rewrite — six `Rules` lines citing retired business rules, one acceptance criterion citing retired `FR-2.2`, and one layer-rule paragraph in architecture §4.1 still resting on `NFR-2.7`'s parallel run. Check 24 caught the two architectural decisions whose stated rationale CR-01 removed.

**Check 25 exists because the rewrite broke the thing `.gitattributes` was written to protect.** Editing five of the six documents on a Windows machine left them CRLF-terminated, so the hashes first recorded in §1 were computed over bytes that Git would normalize away on commit — every one of them would have failed to reproduce on a fresh clone. The hash table is only useful if it is verifiable, and it is only verifiable if the line endings are pinned in fact and not merely in intent.

**Verification is now four checks wider than the change strictly required.** That is the pattern worth carrying forward: each of these five was added because a specific defect got past a careful reading, and a check that has never caught anything is usually a check written before the defect it would have caught existed.

---

## 4. What remains open

### The two that gate work

| ID | Question | Why it is first |
|---|---|---|
| **OI-12** | ✧ **What is the exact column layout of the accounting office's payroll register?** Every column, its meaning, its format, and whether it is stable across periods. **A sample file for a real period is required, not a description.** | The entire payroll now enters through this one file. `AD-17` was designed so the answer is a configuration change rather than rework, and the assumption held while B2 was frozen is a canonical template plus a versioned column mapping. But FR-2.8 cannot be finished without seeing a real file — and if the register is not one row per employee, that is a design conversation, not a mapping row |
| **OI-13** | ✧ **Does the register carry employer shares of SSS, PhilHealth, and Pag-IBIG?** | They affect no employee's net pay (BR-20), so the accounting office may legitimately omit them. B2 assumes **not**, and retains `FR-2.3` in reduced form to derive them — because if the register does carry them, FR-2.3 is dropped cheaply, while the reverse recovery is not cheap. Get this wrong in the other direction and FR-5.3's remittance reports are unbuildable |

### OI-11 — the design question carried forward from B1

| | |
|---|---|
| **Question** | Who administers the ledger hosts, and how many validator nodes |
| **Settled** | Hyperledger Besu with QBFT (AD-14). On-premises, no internet route (AD-10). Hashes only, never payroll data (AD-11). Anchoring asynchronous via a transactional outbox (AD-12) |
| **Recommended, awaiting the client** | Four validators; ledger hosts administered by the external IT contact of FRS §2.3, with the payroll database account excluded |
| **Blocks the build?** | **No.** `LedgerGateway` is the only component that touches the ledger and it addresses a network endpoint |
| **What it does decide** | How strongly FR-6.3 may be claimed. ✧ CR-01 raised the stakes: the anchor now covers the imported file's hash as well as the payroll lines (FR-2.10), so it is the mechanism proving that a figure **the system did not compute** has not been altered since it was received |

**The single question to put to the client:** *can the payroll database administrator be kept off the ledger hosts?*

### Client confirmations still outstanding

`OI-01` employee count · `OI-04` timekeeping export format · `OI-06` bank transmittal layout · `OI-07` disbursement method · `OI-09` single or multi-level approval · `OI-10` retention period · ✧ `OI-14` 13th-month computation · `OI-15` worksheet layout · `OI-16` import permission granularity.

`OI-09` remains the one that would change the architecture rather than configure it.

✧ **Three open items closed or reduced by CR-01.** `OI-03` (day factor) closed outright — `BR-02` is retired and the system derives no rate. `OI-02` (pay frequency) and `OI-05` (pay-basis mix) reduced from computation inputs to a calendar setting and a worksheet column set respectively. `OI-08` was closed by the system architecture in B1.

### Optional improvements

| | |
|---|---|
| L-04 | Subject-area ERDs omit out-of-area parents. Area F carries stubs; the others do not |
| L-07 | `BR-14` sits out of numeric sequence in FRS §7 — now in §7.5, deliberately, beside `BR-20` |

✧ **`L-03` and `L-05` were resolved by CR-01.** L-05 noted that `EX-09` was defined and never referenced downstream; `EX-12` supersedes it with a stronger check and `EX-09` is retired. L-03's concern about range notation hiding individual rule citations is answered by FRS §7.8, which now lists every retired rule by its own identifier.

---

## 5. Changing the baseline

A change to any document in §1 is a change to B2. The procedure that kept this set coherent, and that will keep it coherent:

1. **State which document is authoritative for the change.** Every contradiction resolved during the audits came down to this question, and answering it first is what prevented six documents drifting in six directions.
2. **Make the change, then trace it outward.** A new requirement touches the matrix, the FRS, its traceability tables, at least one use case, usually a diagram, and often the data model.
3. **Re-run the verification in §3.** Counts are the cheapest defect to introduce and the cheapest to catch.
4. **Update the version and the change log**, and re-stamp the baseline.

**What not to do.** Do not put a marker inside an identifier cell, and do not strike an identifier through — both silently remove the row from every count that greps for it, which is how two defects survived a full audit round. Mark the adjacent column instead. Check 15 enforces this.

✧ **And do not reuse a retired identifier.** CR-01 retired three requirements, one exception rule, and ten business rules. All fourteen keep their numbers, are listed with their disposition, and are never reassigned — so a reader of B1 can find what became of each, and a stale citation resolves to a retirement notice rather than to the wrong thing. Checks 21 – 23 enforce this.

### 5.1 ✧ What CR-01 taught about step 1

CR-01 was the first change to exercise this procedure at full scale, and step 1 turned out to be the whole difficulty.

The instinctive reading was that removing a computation module is a design change, authoritative in the FRS. It is not. The matrix reads in one direction — a problem produces requirements — and CR-01 did not remove a requirement, it **removed a problem's answer**: `P2 — Dependence on Excel for computation` was no longer something the system solved, because the spreadsheet stayed. Had the rewrite started from the FRS, the matrix would have been left asserting that the system replaces steps F1–F5 of the client's workflow, which it does not, and Chapter I would have described a different system from Chapter III.

Starting from the matrix forced the restatement of `P2` and `OBJ 2` **before** any specification was touched, and everything downstream followed from wording that was already true. The general rule: **a change that alters what problem the system solves is authoritative in the matrix, however far downstream it appears to land.**

---

## 6. What this baseline is for

Chapter III can be written from it. Every design artifact the manuscript needs exists except the class model — derivable from the 38 components and 39 entities — and a data flow diagram if the department requires one. ✧ A DFD is worth more than it was: the system's boundary now has a round trip through it, and that is a shape a DFD renders well.

Implementation can start from it. The stack is fixed, the schema is specified to the constraint level, the component boundaries are drawn, and no open item blocks the first sprint.

**✧ Two things to do early, both from architecture §13.** Build the NFR-2.12 intake-fidelity harness and the FR-2.9 reconciliation-refusal suite in the first sprint — AD-04 and AD-05 exist, on re-argued grounds, to make `RegisterImportService` and `ReconciliationService` drivable without a browser or a database. And write the deployment runbook around AD-16 before installation day: a server with no internet route cannot run `composer install`, and that is the most common way a correct offline deployment fails.

---

## 7. ✧ What changed from B1, and what it cost

Recorded here rather than only in [CR-01](./change-request-cr-01.md), because a reader comparing the two baselines needs the summary in the baseline itself.

### What the system stopped doing

`FR-2.1` basic pay, `FR-2.2` additional pay, and `NFR-2.7` computational accuracy are retired. The accounting office computes every figure in Microsoft Excel and the system receives the result. **The system derives no employee's pay.**

### What replaced it

`FR-2.8` register import · `FR-2.9` reconciliation and completeness · `FR-2.10` import versioning and supersession · `FR-2.11` payroll input worksheet export · `NFR-2.12` transcription fidelity. `FR-2.3` through `FR-2.6` were reworded, `FR-2.5` inverted outright — `AC-2.5.1` once forbade a hand-entered net pay, and every net pay is now entered by hand, in a spreadsheet outside the system.

### What it cost, stated plainly

| | |
|---|---|
| **OBJ 2 was rewritten** | *Automated computation* became *Verified payroll intake*. The original objective — "without the use of spreadsheets" — became false the moment the client decided; it was restated rather than quietly kept |
| **P2 was restated** | From *Dependence on Excel for computation* to *Uncontrolled handling of computed payroll*. The spreadsheet formulas remain unprotected and uncontrolled, and the system does not change that |
| **The strongest Chapter IV number is gone** | NFR-2.7's 100% agreement against an independently verified manual computation. NFR-2.12 uses the same sample design but evidences faithful **carriage** of a figure, not correct derivation of one |
| **OBJ 4's claim narrowed** | A wrong computed figure now leaves the system and returns to the accounting office as a corrected import. FR-4.3 cannot recompute what it did not compute |
| **Workflow absorption fell from 18 steps to 13** | Steps E, F, and F1–F5 returned to the accounting office |
| **Accuracy is bounded by a spreadsheet the system cannot inspect** | FR-2.9 proves a register agrees with itself. A uniformly wrong multiplier reconciles perfectly. **Chapter IV must state this rather than imply otherwise** |

### What survived untouched

The approval workflow, period locking, the audit trail, role separation, payslip generation, records, reporting, and ledger anchoring — the larger half of the baseline by requirement count. **Figure 7, the payroll run state machine, is unchanged**: the same six states and six transitions, with two labels reworded. The lifecycle a payroll obeys does not depend on who computed it.

### The one risk to carry into implementation

Architecture §6.4 named a stray `(float)` cast as the single implementation slip that defeats BR-01, and identified NFR-2.7's parallel run as the test that would catch it. CR-01 retired that test — and moved the slip from the computation path to the **parse path**, where a spreadsheet library's default behaviour introduces it for you. `BR-40` states the prohibition, `AD-18` gives it a mechanism, and `NFR-2.12` tests for it with a seeded-alteration pass. **This is the most consequential implementation risk in B2, and it is more dangerous than its B1 equivalent, not less.**
