# RoadLink Mobility Commerce Platform

Offline-capable mobility + commerce platform built with Laravel + Vue.

## Project Structure

```text
repo/
├── backend/                    # Laravel 11 API, scheduler jobs, queues, tests
├── frontend/                   # Vue 3 SPA, PWA service worker, Vitest suite
├── docker-compose.yml          # One-click stack (mysql, backend, frontend, scheduler)
├── run_tests.sh                # Docker-based test runner (backend + frontend)
└── Makefile                    # Convenience wrappers

../docs/
├── api-spec.md                 # Endpoint catalog
├── design.md                   # Architecture + state machine diagrams
└── questions.md                # Ambiguity decisions from all sessions
```

## Quick Start (Docker Compose)

From `repo/`:

```bash
docker compose up --build
```

What starts automatically:

- `mysql` with persistent volume (`mysql_data`)
- `backend` on `http://localhost:8000`
  - runs `composer install`
  - creates `.env` from `.env.example` when missing
  - generates app key when needed
  - runs migrations and seeding when DB is empty
  - runs `php artisan storage:link`
- `frontend` on `http://localhost:3000`
  - runs `npm install`
  - runs Vite dev server
- `scheduler` service running `php artisan schedule:work`
  - drives ride timers and recommendation batch jobs

No external map/payment/recommendation APIs are required at runtime.

## Seed Data Credentials

| Username | Password | Role |
| --- | --- | --- |
| admin01 | Admin12345! | admin |
| rider01 | Rider12345! | rider |
| rider02 | Rider12345! | rider |
| driver01 | Driver1234! | driver |
| driver02 | Driver1234! | driver |
| fleet01 | Fleet12345! | fleet_manager |

## Testing Instructions

From `repo/`:

```bash
docker compose exec backend php artisan test
docker compose exec frontend npm run test
```

Docker all-in-one test run:

```bash
./run_tests.sh
```

### Backend Non-Docker Test Run (Local PHP)

If Docker is unavailable, see `backend/README.md` for the two explicit paths:

- Path A: test-only flow (default SQLite in-memory via `phpunit.xml`)
- Path B: local app runtime flow (MySQL-backed manual verification)

Frontend build check:

```bash
docker compose exec frontend npm run build
```

## Scheduler and Batch Jobs

- Backend scheduler is enabled via dedicated `scheduler` service and Laravel `schedule:work`.
- Recommendation engine runs from scheduled `ComputeRecommendations` job.
- Ride auto-cancel/revert/disband timers are scheduled in `backend/routes/console.php`.

## Docs Index

- API reference: `../docs/api-spec.md`
- Architecture and state machine: `../docs/design.md`
- Ambiguity decisions: `../docs/questions.md`
