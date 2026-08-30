# Baseline B1

**Project:** Payroll Management System
**Baseline:** B1 — frozen August 30, 2026
**Scope:** Problem Matrix → FRS → Use Case Model → Behavioral Diagrams → Data Model → System Architecture
**Verification:** 30 chain checks + 22 architecture checks, **0 failed**

---

## What a frozen baseline means here

Every document below is internally consistent, consistent with every other document, and verified mechanically. From this point a change to any one of them is a change to the baseline: it needs a reason, it needs the other five checked against it, and it needs the version and change log updated.

This is not a claim that the design is finished. It is a claim that the design is **coherent** — that no two documents contradict each other, no identifier dangles, and no count is wrong. One design question remains open (OI-11) and is recorded below with what it does and does not affect.

---

## 1. Contents

| Document | Version | Words | Lines | SHA-256 (first 16) |
|---|:---:|---:|---:|---|
| [Problem-to-Requirements Matrix](./problem-requirements-matrix.md) | 1.1 | 3,253 | 207 | `225eb0e8b8cde855` |
| [Functional Requirements Specification](./functional-requirements-specification.md) | 1.1 | 15,184 | 1,376 | `e0fd4334af4365a2` |
| [Use Case Model](./use-case-model.md) | 1.1 | 15,278 | 1,903 | `378e7b640f9e2a61` |
| [Behavioral Diagrams](./behavioral-diagrams.md) | 1.1 | 5,583 | 866 | `b038365c1524ff0e` |
| [Data Model](./data-model.md) | 1.1 | 6,998 | 1,024 | `44262692b39d80fd` |
| [System Architecture](./system-architecture.md) | 1.1 | 8,838 | 871 | `deb4ff4eb092a0ff` |

The hashes are here so that a later reader can tell whether a document has moved since the freeze. Recompute with `sha256sum` and compare the first sixteen characters.

**Supporting records, not part of the baseline:** two consistency audit rounds — 22 findings then 8 — were run against this set and resolved in full. Their findings are recorded in the change log of the documents they corrected and in §3 below; they are not carried as separate documents, because the baseline supersedes them.

**Derived from the baseline, not part of it:** the [Implementation Plan](./implementation-plan.md), which fixes build order only. It adds no requirement and changes no design decision; where the two disagree, this baseline is authoritative.

---

## 2. The numbers this baseline fixes

Every figure below was extracted from the documents themselves, not transcribed.

### Requirements

| | |
|---|---|
| Matrix | 26 FR + 8 NFR + 1 DR = **35 requirements** |
| FRS | **30 FR** (26 matrix-traced + 4 foundation `FR-0.x`) |
| | **8 gated NFR**, plus 4 ungated quality expectations (NFR-7.1–7.4) |
| | **5 DR** (DR-1.6 structural + DR-2.1–2.4) |
| **Traceable requirement items** | **43** — identical sets in FRS Table 8 and use-case Table 3 |
| Business and computation rules | **36** (BR-01 … BR-36, contiguous) |
| Acceptance criteria | **135** |
| Exception rules | **10** (EX-01 … EX-10, 5 blocking / 5 warning) |

### Model

| | |
|---|---|
| Use cases | **31 primary + 6 included** |
| Actors | 4 human + 1 supporting (`System Clock`) |
| Modules | 7 (M1 – M7) |
| Entities | **37** in 6 subject areas (8 + 7 + 6 + 9 + 2 + 5) |
| Relationships | **50** in the master ERD |
| Architecture components | **35** across 4 layers |
| Architectural decisions | **16** (AD-01 … AD-16) |
| Diagrams | 8 use case · 8 behavioral · 8 data · 6 architecture = **30** |

### Stack, as fixed by architecture §9

Ubuntu Server 24.04 LTS · Nginx 1.24 · PHP 8.3 with BCMath · Laravel 11 · Blade + Alpine 3 · MySQL 8.4 LTS · DomPDF · PhpSpreadsheet · Hyperledger Besu 24.x with QBFT · PHPUnit.

---

## 3. What is verified

| # | Check | Result |
|---|---|---|
| 1 | Every `FR`, `NFR`, `DR`, `AC`, `BR`, `EX`, `OI`, `UC`, `C`, `A`, `R`, `P` reference resolves | ✅ 0 dangling |
| 2 | Matrix, FRS, and use-case arithmetic reconcile to the same 43 items | ✅ |
| 3 | FRS Table 8 and use-case Table 3 hold identical identifier sets | ✅ |
| 4 | Business rules contiguous, no gaps or duplicates | ✅ 36 of 36 |
| 5 | Run lifecycle: every FR-4.4 transition is an arrow in Figure 7 | ✅ 6 of 6 |
| 6 | Six run states identical in FRS, state machine, and `run_status` enum | ✅ |
| 7 | Behavioral flow coverage matches the document's own claim | ✅ 24 of 28, 4 named as undrawn |
| 8 | Every cross-module trace marked († or ‡) | ✅ |
| 9 | No use case deferrable while its requirement is `Must` | ✅ |
| 10 | Every FR-6.2 grant has a use case path | ✅ |
| 11 | Master ERD entity names match §5.1 exactly | ✅ 37 = 37 |
| 12 | Subject-area counts sum to the master ERD | ✅ |
| 13 | Data model §8 accounts for all 43 requirement items | ✅ |
| 14 | Every `INTEGRITY_ANCHOR.scope_type` value has a foreign key | ✅ |
| 15 | No identifier cell carries a marker or strikethrough | ✅ |
| 16 | Prose citations of diagram step numbers in range and correct | ✅ |
| 17 | Markdown tables well-formed across all six documents | ✅ 0 malformed |
| 18 | Mermaid fences balanced, 30 diagrams | ✅ |
| 19 | Architecture: 35 components, 14 of them named verbatim in behavioral §1.4 | ✅ |
| 20 | Architecture quotations resolve to text that exists in a source document | ✅ |

Checks 14, 15, and 20 exist because each caught a real defect: an enum value with no column able to hold it, a marker inside an identifier cell that silently dropped the row from every count, and a quotation of wording that had since been rewritten.

---

## 4. What remains open

### OI-11 — the only outstanding design question

| | |
|---|---|
| **Question** | Who administers the ledger hosts, and how many validator nodes |
| **Settled** | Platform is Hyperledger Besu with QBFT (AD-14). On-premises, no internet route (AD-10). Hashes only, never payroll data (AD-11). Anchoring asynchronous via a transactional outbox (AD-12) |
| **Recommended, awaiting the client** | Four validators; ledger hosts administered by the external IT contact of FRS §2.3, with the payroll database account excluded |
| **Blocks the build?** | **No.** `LedgerGateway` is the only component that touches the ledger and it addresses a network endpoint. Node count, placement, and administrator identity are deployment configuration and appear in no component, table, or interface |
| **What it does decide** | How strongly FR-6.3 may be claimed. With administrative separation, FR-6.3 detects alteration by anyone including a database administrator. Without it, it detects accidental corruption, failed restores, and application defects. Both are real; Chapter IV must state whichever is true |

**The single question to put to the client:** *can the payroll database administrator be kept off the ledger hosts?*

### Client confirmations still outstanding

`OI-01` employee count · `OI-02` pay frequency · `OI-03` day factor · `OI-04` timekeeping export format · `OI-05` pay-basis mix · `OI-06` bank transmittal layout · `OI-07` disbursement method · `OI-09` single or multi-level approval · `OI-10` retention period.

`OI-09` is the one that would change the architecture rather than configure it: multi-level approval adds a state to FR-4.4 and a branch to the run lifecycle. The rest are parser formats, thresholds, and sizing.

`OI-08` was closed by the system architecture and is retained in FRS §11 with its answer recorded.

### Optional improvements, carried forward from the audits

| | |
|---|---|
| L-03 | Range notation (`BR-01 – BR-05`) hides individual rule citations from a `grep`. Matters only if traceability is generated mechanically |
| L-04 | Subject-area ERDs omit out-of-area parents. Area F now carries stubs; the others do not |
| L-05 | `EX-09` is defined and never referenced downstream. Confirm it is wanted |
| L-07 | `BR-14` sits out of numeric sequence in FRS §7 |

None is a defect. All four were assessed and deliberately left.

---

## 5. Changing the baseline

A change to any document in §1 is a change to B1. The procedure that kept this set coherent, and that will keep it coherent:

1. **State which document is authoritative for the change.** Every contradiction resolved during the audits came down to this question, and answering it first is what prevented six documents drifting in six directions.
2. **Make the change, then trace it outward.** A new requirement touches the matrix, the FRS, its traceability tables, at least one use case, usually a diagram, and often the data model. `FR-6.3` touched all six.
3. **Re-run the verification in §3.** Counts are the cheapest defect to introduce and the cheapest to catch.
4. **Update the version and the change log**, and re-stamp the baseline.

**What not to do.** Do not put a marker inside an identifier cell, and do not strike an identifier through — both silently remove the row from every count that greps for it, which is how two defects survived a full audit round. Mark the adjacent column instead. Check 15 now enforces this.

---

## 6. What this baseline is for

Chapter III can be written from it. Every design artifact the manuscript needs exists except the class model — derivable from the 35 components and 37 entities — and a data flow diagram if the department requires one.

Implementation can start from it. The stack is fixed, the schema is specified to the constraint level, the component boundaries are drawn, and the one open design question does not block any of it.

**Two things to do early, both from architecture §13.** Build the NFR-2.7 parallel-run harness in the first sprint — AD-04 and AD-05 exist to make `ComputationEngine` drivable without a browser or a database, and §6.4 names the one implementation slip, a stray `(float)`, that would break BR-01. And write the deployment runbook around AD-16 before installation day: a server with no internet route cannot run `composer install`, and that is the most common way a correct offline deployment fails.
