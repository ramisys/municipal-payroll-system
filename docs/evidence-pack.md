# Evidence Pack — Week 8

pre-oral-demonstration-plan.md §6 Table 6, W8: "Evidence pack complete; two full rehearsals on the staging machine." §8 names eight rows this pack must cover. Each row below states what the evidence is, where it lives, how to reproduce it on demand (a panel member can ask to see it run), and its status as of 2026-08-31.

**How to use this during the defense.** Every "Reproduce" command is meant to be run live if asked — none of this evidence is a claim taken on faith. `php artisan test --filter=<Name>` reruns exactly the test named.

---

## 1. The no-float proof

**Claim** — BR-40/AD-18: a monetary cell is never read through a PHP float.
**Lives in** — `tests/Unit/NoFloatParseProofTest.php`, against `tests/Fixtures/no_float_proof.xlsx`.
**Reproduce** — `php artisan test --filter=NoFloatParseProofTest`
**Status** — Done (W1). Two tests: reading the fixture's `0.10`/`0.20` cells as floats and summing them fails to equal the decimal sum (the textbook IEEE-754 case); reading the same cells through `RegisterImportService`'s decimal-string path holds exactly.

## 2. Reconciliation refusal suite output

**Claim** — FR-2.9: every seeded defect is refused, named by row/column/defect.
**Lives in** — `tests/Unit/ReconciliationServiceTest.php` (row arithmetic, control totals, matching, completeness — E2-E5) and `tests/Unit/RegisterImportServiceTest.php` (structural E1), against `tests/Fixtures/register_defect_*.xlsx` and `register_malformed_missing_column.xlsx`.
**Reproduce** — `php artisan test --filter=ReconciliationServiceTest` and `--filter=RegisterImportServiceTest`
**Status** — Done (W3), now also exercised through the real write path (see row 3) and the live screen (`tests/Feature/PayrollImportControllerTest.php::test_a_defective_register_is_refused_at_preview`).

## 3. NFR-2.12 fidelity run

**Claim** — the FRS's own validation-set line: "≥ 30 employees across 3 payroll periods, covering regular, overtime, leave-affected, and loan-deducted cases. Pass = 100% agreement to the centavo on both directions ... includes a seeded-defect pass."
**Lives in** — `tests/Feature/IntakeFidelityValidationSetTest.php` (the full validation set, captured this week) plus `tests/Feature/IntakeFidelityHarnessTest.php` (the harness's own correctness, proven earlier against a 3-row fixture).
**Reproduce** — `php artisan test --filter=IntakeFidelityValidationSetTest`
**Status** — Done (harness proved W4, validation set run captured W8). Imports the real 30-employee demo population (`EmployeeDemoSeeder`) across three separately-scaffolded payroll periods (90 rows total), covering all four named cases by construction (employees 0-9 carry overtime every period, 10-19 carry a reduced BASIC standing in for an unpaid-leave day, 20-29 carry a loan deduction, all 30 carry the regular BASIC case), and asserts zero mismatch file→database and database→file in every period. A second test re-runs the seeded-defect pass (one centavo altered in a stored row) at this same 30-employee scale, not only the 3-row scale.

## 4. Negative permission test output

**Claim** — FR-6.2/BR-28/BR-29: a function absent from a role's grants is refused if invoked directly (AC-6.2.2), not merely hidden.
**Lives in** — `tests/Feature/AuthorizationServiceTest.php` (the matrix itself) plus a "no non-administrator role can reach X" or "a role without the grant is refused" test in every module's feature test file: `EmployeeManagementTest`, `UserManagementTest`, `ReferenceDataManagementTest`, `OrganizationProfileTest`, `AttendanceImportControllerTest`, `CompensationProfileManagementTest`, `ImportColumnMapControllerTest`, `PayrollRunControllerTest`, `PayrollImportControllerTest`.
**Reproduce** — `php artisan test --filter=AuthorizationServiceTest`
**Status** — Done, and current through W7: `AuthorizationServiceTest::test_payroll_officer_can_create_a_payroll_run_but_administrator_cannot` was written ahead of `PayrollRunController` even existing (W2/W3) and passed unmodified once the controller landed in W7 — the permission matrix was right before the screen was.

## 5. Offline deployment rehearsal note

**Claim** — AD-16/C-03: the built artifact runs with no network route.
**Lives in** — [deployment-rehearsal.md](./deployment-rehearsal.md).
**Reproduce** — the exact commands are in that document's "Reproducing the build" section.
**Status** — Partial, unchanged since W1 and re-verified this week. Everything short of physically disconnecting a machine is done and passing (artifact build, cached-config boot, local-only serving, and — new this week — a full re-scan of every controller/service added since W1 for outbound calls: none found). **The one remaining step needs physical hardware**: copy the built artifact to a second, network-disconnected machine and confirm it still serves `/` and `/up`. Do this before the first staging rehearsal below — it is the same machine, so the two checks combine into one trip.

## 6. `AUDIT_LOG` extract with an unbroken `prev_entry_hash` chain

**Claim** — FR-6.1/BR-35: every action is attributed and hash-chained; tampering is detectable.
**Lives in** — `tests/Feature/AuditServiceTest.php` (hash computation and chain-break detection) and `tests/Feature/AuditLogViewerTest.php` (the screen, including its own "Filter and verify chain" button).
**Reproduce** — `php artisan test --filter=AuditServiceTest` and `--filter=AuditLogViewerTest`. To see a live extract: sign in, perform a few actions, open **Audit log**, click **Filter and verify chain** — this is demo beat 12 itself, so the "extract" a panel asks for is a screen they have already been shown, not a separate artifact.
**Status** — Done. The tests prove the mechanism (a forged row is detected and the break located); a genuine extract with real entries is produced fresh by every rehearsal run, which is the more convincing form of this evidence than a static file would be.

## 7. Migration run from empty on the staging machine

**Claim** — DR-1.6, architecture §8.4: the schema is reproducible from nothing.
**Lives in** — [deployment-rehearsal.md](./deployment-rehearsal.md), "Migration run from empty" section.
**Reproduce** — `php artisan migrate:fresh --seed`
**Status** — Done on the development machine this week (all 45 migrations, all 12 seeders, verified row counts). **Still needs the same run repeated literally on the staging machine** as part of the physical rehearsal below — a development-machine pass is evidence the schema is correct, not evidence the staging machine's MySQL/PHP versions agree with it.

## 8. The four demo register files, kept under version control

**Claim** — reproducibility of the demonstration itself.
**Lives in** — `tests/Fixtures/register_clean.xlsx`, `register_defect_row_imbalance.xlsx` (§7 beat 9, "one centavo off"), `register_defect_omitted_employee.xlsx` (§7 beat 10, "missing one active employee"). Generated by `App\Console\Commands\GenerateTestFixtures` (`php artisan fixtures:generate`) and committed so no test or rehearsal depends on that command having been run.
**Status** — Three distinct files exist and are exercised by name in beats 9-10. **Open question, not resolved by this session**: §7's own rehearsal-prep paragraph lists four files by name — "clean, centavo-off, missing-employee, corrected" — but the beat table only calls for three payroll-register files (beat 11's "corrected register" is `register_clean.xlsx` reused, since it is the complete, arithmetically-correct set every defect fixture is a deliberately-broken variant of). **Confirm this reading before the first rehearsal**: if "corrected" is meant to be a fourth, textually distinct file from "clean" — e.g. one that visibly differs from the centavo-off or missing-employee fixture rather than being the shared base they were derived from — a new fixture needs generating. As built, importing `register_clean.xlsx` after either defect fixture is what beat 11 demonstrates, and every existing test already uses it that way.

---

## What this pack does not cover

**The two full rehearsals themselves.** Nothing above substitutes for actually running the twelve beats (§7) live, twice, on the staging machine, from the built artifact — that is a physical/human action, not a code artifact this session can produce. What this pack gives the rehearsal:

- A reset path: `php artisan migrate:fresh --seed` reproduces the exact seeded demo state (30 employees, 4 users, reference data) every time, so each rehearsal starts identically (§7's own instruction: "Reset to the seeded demo state before each run").
- The three register fixtures beats 9-11 need, already committed (row 8 above, with the one open question flagged).
- The attendance fixtures beat 6 needs: `tests/Fixtures/attendance_clean.xlsx`, `attendance_one_bad_row.xlsx`.
- A demo-account username per role from `UserSeeder` — sign in as the Payroll Officer for beats 1, 3-11; switch to a role without a grant for beat 2's refusal; an Approver/Administrator/Viewer account exists for beat 12's audit-log review.
- Every screen the twelve beats visit has been rendered by at least one feature test in this build (`php artisan test` — 136 passing, listed by file in each week's commit), so a rehearsal is very unlikely to hit an unrendered view or a 500 the test suite would already have caught.

**Record the two rehearsals' results here once run** (this session cannot perform them):

| Rehearsal | Date | Result | Notes |
|---|---|---|---|
| 1 | *pending* | *pending* | |
| 2 | *pending* | *pending* | |
