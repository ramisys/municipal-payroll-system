# Municipal Payroll System

A local, offline payroll administration system for a municipal government office. It replaces an Excel-based payroll process for everything **around** the computation itself — employee records, attendance, the paperwork trail between the payroll office and the accounting office, approval, payslips, and record-keeping — while leaving the actual peso computation with the accounting office, where it already happens today.

Built as an academic capstone project. The full requirements, design, and traceability documentation lives in [`docs/`](docs/) — this file is an orientation, not a replacement for it.

## What it does — and does not do

**The system does not compute pay.** This is a deliberate scope decision ([CR-01](docs/change-request-cr-01.md)), not a missing feature. The workflow is:

1. The Payroll Officer prepares employee, attendance, and compensation data in the system and exports an **input worksheet**.
2. The accounting office computes the payroll off-system, as it already does, and returns a completed **payroll register**.
3. The Payroll Officer imports that register. The system re-derives every total from the row-level figures and refuses the import if anything doesn't reconcile — arithmetic, control totals, completeness, or a mismatch against what it expects — naming the exact row and defect rather than silently accepting bad data.
4. Once a register is in, the system owns it: exception review, approval, payslips, search, and reporting all run against the imported, reconciled record.

What it *does* do, in the order a payroll cycle actually needs it:

| Module | Covers |
|---|---|
| **M1 — System Administration** | Sign-in, users & roles, organization profile, payroll calendar, reference data, audit log |
| **M2 — Employee Management** | Employee master file, compensation profile, employment status |
| **M3 — Attendance & Leave** | Daily time record import, exception encoding, leave filing and balances |
| **M4 — Payroll Intake** | Input worksheet export, register import, reconciliation, import versioning, payroll runs, statutory reference data |
| **M5 — Validation & Approval** | Exception report, register review, correction, approval workflow, period lock |
| **M6 — Payslip** | Generation, layout, batch export, reprint |
| **M7 — Records & Reporting** | Storage, search, report generation, backup |

As of this writing (week 8 of the build), **M1–M4 and the audit log are implemented and routed**; M5's approval workflow, M6 (payslips), and full M7 reporting are documented and modeled but not yet wired into the application — see [`docs/pre-oral-demonstration-plan.md`](docs/pre-oral-demonstration-plan.md) §6 for the build schedule and [`docs/evidence-pack.md`](docs/evidence-pack.md) for what's verified so far.

## How it works

- **Stack:** Laravel 11 (PHP 8.3+), MySQL 8.4, server-rendered Blade views styled with Tailwind CSS, PhpSpreadsheet for `.xlsx` import/export, BCMath for money.
- **Deployment target:** a single LAN server inside the client's premises, no internet route, any number of workstations on the same network hitting it through a browser. See [`docs/system-architecture.md`](docs/system-architecture.md) for the full component and deployment diagrams. The front end is a local Tailwind build — it fetches no CDN script and no webfont, so it renders correctly on a machine with no network access at all.
- **Money is never a float.** Every monetary value is `DECIMAL(13,2)` in the database and is parsed and handled as a decimal string (BCMath) end to end — a spreadsheet cell never round-trips through a PHP `float`. `tests/Unit/NoFloatParseProofTest.php` proves this directly.
- **Roles and permissions** (Administrator, Payroll Officer, Approver, Viewer) are a data-driven permission matrix (`RoleSeeder`), checked explicitly by `AuthorizationService` at every controller/service entry point — not just hidden in the UI. A Payroll Officer cannot approve the run they submitted (separation of duty), and a role without a grant is refused even if it calls the action directly.
- **Every change is audited.** `AuditService` writes an append-only, hash-chained log row in the same database transaction as the change it records, so a tampered row breaks the chain and is detectable.
- **Imports are versioned, not overwritten.** A superseding register import supersedes the prior one rather than mutating it, so a historical payroll run stays reproducible even after a later rate or data correction.

## Setup

Requires PHP 8.3+, Composer, Node.js, and a running MySQL 8.4 instance.

```
composer install
npm install
npm run build
cp .env.example .env
php artisan key:generate
# create the database named in .env (DB_DATABASE, default municipal_payroll)
php artisan migrate --seed
```

`npm run build` is not optional — every screen pulls its stylesheet through `@vite`, which throws if `public/build/manifest.json` is missing. While actively working on the UI, run `npm run dev` instead for hot reload.

Then serve it:

```
php artisan serve
```

...or use `composer dev`, which runs the app server, queue listener, log tailer, and Vite dev server together.

### Default accounts

`php artisan migrate --seed` creates one demo account per role, each with the password `ChangeMe!123` and forced to change it on first sign-in:

| Username | Role |
|---|---|
| `admin` | Administrator |
| `payroll.officer` | Payroll Officer |
| `approver` | Approver |
| `viewer` | Viewer |

## Testing

```
php artisan test
vendor/bin/pint --test   # coding standard check (laravel preset / PSR-12)
```

`tests/Fixtures/` holds committed `.xlsx` fixtures generated by `php artisan fixtures:generate` — regenerate and commit the result if a fixture needs to change; don't hand-edit the binaries.

## Documentation

Full requirements, design, and traceability live in [`docs/`](docs/):

- [`functional-requirements-specification.md`](docs/functional-requirements-specification.md) — what the system must do
- [`use-case-model.md`](docs/use-case-model.md) — who does what, and in what order
- [`data-model.md`](docs/data-model.md) — the schema, 39 entities across 6 subject areas
- [`system-architecture.md`](docs/system-architecture.md) — components, deployment topology, and the 18 architectural decisions behind them
- [`behavioral-diagrams.md`](docs/behavioral-diagrams.md) — sequence diagrams for the modeled use cases
- [`deployment-rehearsal.md`](docs/deployment-rehearsal.md) — the offline deployment proof

See [`CONTRIBUTING.md`](CONTRIBUTING.md) for the branch model, coding standard, and a known environment issue when developing from a WSL-mounted repository path.
