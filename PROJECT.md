# PROJECT.md (agent-editable, facts only)

Stack: Laravel 13 (PHP 8.3+) backend in `testbackend/`, React 19 + TypeScript + Vite 8
frontend in `testfrontend/`. Reason: pre-scaffolded in the repo.

Added deps: `laravel/sanctum` (backend, token auth for SPA-over-separate-port) and
`react-router-dom` (frontend, two portals in one app). Reason: neither was present but
both are required by CONTRACT.md/ASSUMPTIONS.md #2/#3.

Database: MySQL for dev/seed, per `.env.example` (`DB_CONNECTION=mysql`, db `testbackend`,
host `127.0.0.1:3306`, user `root`). Reason: user directive — MySQL must be running
locally before `migrate`/`seed`. Tests still use in-memory SQLite (`phpunit.xml`, unchanged)
so the suite has no external dependency.

Auth: Sanctum personal access tokens, `Authorization: Bearer <token>`. Single `users` table
with a `role` enum (`admin`/`customer`). Reason: ASSUMPTIONS.md #3/#4.

Layout: two top-level folders, `testbackend/` (API) and `testfrontend/` (SPA), run
independently (`php artisan serve` on :8000, `vite` on :5173), CORS allowed from the Vite
origin. Reason: pre-existing split, no monorepo tooling present.

Run:
- Backend: MySQL must be running with a `testbackend` db reachable per `.env` first, then
  `cd testbackend && composer install && php artisan migrate:fresh --seed && php artisan serve`
- Frontend: `cd testfrontend && npm install && npm run dev`
- Backend tests: `cd testbackend && php artisan test` (or `vendor/bin/pest`, or a single file/`--filter`)
- Smoke: seeded admin + customer credentials are printed by the seeder (see slice 5).

Conventions: PHP — curly braces always, constructor property promotion, explicit return
types (per `testbackend/AGENTS.md`/Boost rules already in the repo). Run
`vendor/bin/pint --dirty --format agent` after PHP edits. API response shape and discount
math are fixed in CONTRACT.md — the frontend must not recompute discounts, only display
what the API returns.
