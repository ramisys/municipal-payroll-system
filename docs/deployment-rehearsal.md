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

## What is still open

**The physical test.** Build the artifact on the development machine, copy the whole tree (application code, `vendor/`, `public/build/` if a front-end build exists yet, `.env` configured for the target's local MySQL) to the staging/target machine, disconnect that machine's network, and run the same three checks above (`/` returns 200, `/up` returns 200, page renders with no missing-external-asset console errors). Record the result here as the closing entry in this table:

| Rehearsed on | Date | Result |
|---|---|---|
| Development machine, network-connected, artifact built and served locally | 2026-08-31 | Pass — see above |
| Target/staging machine, network cable disconnected | *pending* | *pending* |

## Reproducing the build

```
composer install --no-dev --optimize-autoloader
cp .env.example .env    # then set DB_* for the target machine's local MySQL
php artisan key:generate
php artisan migrate --seed --force
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan serve
```

If the repository is being copied from a Windows machine with the source under a `\\wsl.localhost\...` UNC path, see [CONTRIBUTING.md](../CONTRIBUTING.md) for two known environment-specific workarounds needed only on that path shape — neither applies to a normal deployment target.
