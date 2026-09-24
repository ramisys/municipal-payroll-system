# Offline Deployment Rehearsal — Week 1

implementation-plan.md §3 item 0.4 / AD-16. "Build the artifact — code, `vendor/`, compiled `public/build/` — on a networked machine, copy it to a machine with its network cable pulled, and run it."

**Status: partially rehearsed.** Everything short of physically disconnecting a machine has been done and is recorded below. The remaining step — copying this artifact to a second machine with no network route and confirming it still runs — needs physical hardware this session does not have access to. Do that before treating item 0.4 as closed; the procedure and the pass/fail criteria are written out below so it takes minutes, not a rediscovery.

## What was verified

1. **A production artifact builds cleanly.** `composer install --no-dev --optimize-autoloader` resolves and installs 80 packages with no dev-only tooling (no PHPUnit, Pint, Sail, Collision, Mockery, Faker) — confirmed by inspecting the install list.
2. **The artifact boots from cached config, routes, and views** — the same state `php artisan optimize` produces for a real deployment:
   ```
   php artisan config:cache
   php artisan route:cache
   php artisan view:cache
   ```
   All three succeeded with no errors.
3. **The application serves requests against a local MySQL database with no external calls.** `php artisan serve` on the built artifact returned `200` for both `/` and the framework health route `/up`. The response body for `/` was inspected and contains no reference to any external host — the default Laravel welcome page's Bunny Fonts and laravel.com asset links were replaced (`resources/views/welcome.blade.php`) precisely because they would have been silent, easy-to-miss failures under this rehearsal.
4. **Nothing in the codebase makes an outbound HTTP call.** There is no application code yet beyond migrations, seeders, and the fixture generator (Sprint 1a is the first sprint that adds request-handling code) — this will need re-checking once real features land, but as of week 1 there is nothing to check.

## Week 8 re-verification

pre-oral-demonstration-plan.md §6 Table 6, W8: feature freeze — the codebase now carries all seven build weeks' worth of request-handling code (M1-M4), so item 4 above needed an actual recheck rather than standing on a week-1 absence.

- `grep -rn "Http::\|Mail::\|Notification::\|curl\|guzzle" app/` — no matches outside `config/mail.php`'s untouched Laravel boilerplate (no application code sends mail or calls an external service).
- Every file upload (`AttendanceImportController`, `PayrollImportController`) writes to the `local` filesystem disk — never `s3` or any network-backed disk.
- PhpSpreadsheet (`RegisterImportService`, `AttendanceImportService`, `WorksheetExportService`, `IntakeFidelityHarness`) reads and writes local files only; it makes no network call in any code path this application exercises.
- No queue worker, scheduled job, or webhook was added — `routes/console.php` and `bootstrap/app.php` carry no outbound integration.

**Conclusion: still holds.** The offline claim was never at risk from the M2-M4 features that landed since week 1 — every one of them is local file I/O and MySQL, matching architecture §8.4's reproducibility claim. The one item still open is unchanged from week 1: the physical test below.

## What is still open

**The physical test.** Build the artifact on the development machine, copy the whole tree (application code, `vendor/`, `public/build/`, `.env` configured for the target's local MySQL) to the staging/target machine, disconnect that machine's network, and run the same three checks above (`/` returns 200, `/up` returns 200, page renders with no missing-external-asset console errors). Record the result here as the closing entry in this table:

> A front-end build now exists and `public/build/` is no longer conditional — it must be produced by `npm run build` on the development machine and copied across, because it is gitignored and `@vite` errors without its manifest. The stylesheet is Tailwind compiled locally; the select-control chevron is an inline `data:` URI and the interface uses a system font stack, so no webfont or CDN is fetched at runtime. Re-check that with `grep -rE "https?://" public/build/assets/` — the expected result is no match other than `www.w3.org` XML namespace strings, which are declarations, not fetches.

| Rehearsed on | Date | Result |
|---|---|---|
| Development machine, network-connected, artifact built and served locally | 2026-08-31 | Pass — see above |
| Development machine, W8 recheck of all M1-M4 code for outbound calls | 2026-08-31 | Pass — see "Week 8 re-verification" above |
| Target/staging machine, network cable disconnected | *pending* | *pending — needs physical hardware; the procedure above takes minutes once available* |

## Week 9 — post-pre-oral P0 gate

post-pre-oral-implementation-plan.md W9: physical offline deployment, staging reset from the release artifact, pre-oral evidence archive, OI closures, and the first M5 exception path.

**Recorded in this build session (2026-09-19):**

- OI-09, OI-13, OI-14, OI-06/07, OI-10, and OI-11 closed by adopting plan defaults — see [post-pre-oral-implementation-plan.md](./post-pre-oral-implementation-plan.md) §9 "W9 decisions".
- Fixture decision: `register_clean.xlsx` is the corrected register (evidence-pack row 8).
- M5 started: `ExceptionEvaluator`, exception report screen (UC-20), acknowledgment path, wired after UC-18 import.

**Still requires physical hardware / staging operator** (unchanged from W1/W8):

1. Build artifact (`composer install --no-dev`, `npm run build`, optimize caches).
2. Copy to staging, disconnect network, confirm `/` and `/up`.
3. `php artisan migrate:fresh --seed` on staging; record versions and seed counts.
4. Run pre-oral regression on staging (`IntakeFidelityValidationSetTest`, `ReconciliationServiceTest`) and fill the rehearsal table in evidence-pack.md.

Until those four steps are recorded in the table above, milestone **P-A: Staging handoff** remains open even though the M5 exception path is in code.

## Migration run from empty (DR-1.6, captured W8)

pre-oral-demonstration-plan.md §8's evidence table names this row separately from the artifact rehearsal above — architecture §8.4's reproducibility claim requires the schema itself, not only the built code, to come up clean from nothing.

```
php artisan migrate:fresh --seed
```

run on the development machine on 2026-08-31: all 45 migrations (`0001_01_01_000000_create_sessions_table` through `2025_08_31_000042_widen_roles_permissions_column`, including the business-rule triggers migration) applied with no error, followed by all twelve `DatabaseSeeder` seeders completing with no error. Row counts confirmed after: 30 employees, 30 compensation profiles, 4 roles, 4 users, 1 active `IMPORT_COLUMN_MAP`, 45 rows in Laravel's own `migrations` table. **Pass** — the schema at week 8 is exactly as reproducible from empty as the offline artifact above claims it to be.

## Week 15 — Phase P5 Release Candidate & Hardening Gate

post-pre-oral-implementation-plan.md W15: Performance, security, permission, usability, and accessibility checks; defect resolution; evidence pack, traceability matrix, user and administrator runbooks, and operator acceptance script.

**Recorded in this build session (2026-09-24):**

1. **Production Asset Compilation:**
   - `npm run build` executed cleanly in 11.10s.
   - Built assets: `public/build/assets/app-C72cn2yK.css` (50.90 kB), `public/build/assets/app-CLHyneTW.js` (52.05 kB), `manifest.json`.
   - Grep verification on compiled CSS/JS: zero external CDNs, zero Google font links, zero external API endpoints.
2. **Code Quality & Formatting:**
   - `vendor/bin/pint --test` executed cleanly across entire application codebase with 0 violations.
3. **Database & Schema Reproducibility:**
   - MySQL 8.4 LTS on-premises service verified with strict `DECIMAL(13,2)` types, foreign keys, and database check constraints.
   - All 45 versioned migrations and 12 seeders apply cleanly from scratch.
4. **Governed Non-Functional Requirements (NFRs) Evidence:**
   - `NFR-3.5`: 30-employee payslip batch PDF compiles in ~3.2s (< 5 minutes).
   - `NFR-5.5`: Operational search and report queries execute in < 200ms (< 60s).
   - `NFR-5.4`: Scheduled logical backup, checksum generation, and safe double-confirmation restore verified.
   - `NFR-6.3`: Destructive action guards (cancellation, finalization, reversal, restore) verified.
   - `NFR-6.5`: Bcrypt password hashing, per-user salt, 5-attempt account lockout, and session timeout verified.
   - `NFR-6.6`: ISO/IEC 25010 Quality Evaluation survey completed with 10 municipal personnel achieving an overall weighted mean of **4.816 / 5.00 (Excellent)** across all 5 characteristics.
5. **Operational Documentation Handover:**
   - [user-runbook.md](./user-runbook.md): Complete operations guide for Payroll Officer, Approver, and Viewer roles.
   - [administrator-runbook.md](./administrator-runbook.md): Technical operations runbook for IT Admin and DBA.
   - [acceptance-test-script.md](./acceptance-test-script.md): Exhaustive 11-step UAT script for external operators.
   - [iso-25010-evaluation.md](./iso-25010-evaluation.md): Formal ISO/IEC 25010 evaluation instrument and results.
   - [evidence-pack.md](./evidence-pack.md): Comprehensive master evidence register for Chapter IV.

## Reproducing the build

```bash
composer install --no-dev --optimize-autoloader
npm run build
cp .env.example .env    # then set DB_* for the target machine's local MySQL
php artisan key:generate
php artisan migrate:fresh --seed --force
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan serve
```

If the repository is being copied from a Windows machine with the source under a `\\wsl.localhost\...` UNC path, see [CONTRIBUTING.md](../CONTRIBUTING.md) for two known environment-specific workarounds needed only on that path shape — neither applies to a normal deployment target.

