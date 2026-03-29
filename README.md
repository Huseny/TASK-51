# RoadLink Mobility Commerce Platform

Offline-capable mobility + commerce platform built with Laravel + Vue.

## Project Structure

```text
.
├── docker-compose.yml          # One-click local stack (mysql, backend, frontend, scheduler)
├── docs/
│   ├── api-spec.md             # Endpoint catalog
│   ├── design.md               # Architecture and state machine
│   └── questions.md            # Ambiguity decisions log
└── repo/
    ├── backend/                # Laravel 11 API + jobs + scheduler tasks
    ├── frontend/               # Vue 3 SPA + PWA service worker
    ├── docker-compose.yml      # Legacy compose for repo-local workflows
    └── README.md
```

## Quick Start (Docker Compose)

Run from repository root:

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

## Testing

From repository root:

```bash
docker compose exec backend php artisan test
docker compose exec frontend npm run test
```

Frontend build check:

```bash
docker compose exec frontend npm run build
```

## Operational Notes

- Backend scheduler is enabled via dedicated `scheduler` service and Laravel `schedule:work`.
- Recommendation engine runs from scheduled `ComputeRecommendations` job.
- Ride auto-cancel/revert/disband timers are scheduled in `repo/backend/routes/console.php`.
- PWA assets and service worker are configured in `repo/frontend/vite.config.js` and `repo/frontend/src/sw.js`.

## Documentation Index

- API reference: `docs/api-spec.md`
- Architecture and state machine: `docs/design.md`
- Ambiguity decisions: `docs/questions.md`
