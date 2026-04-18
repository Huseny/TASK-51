Project Type: fullstack

# RoadLink Mobility Commerce Platform

Offline-capable mobility + commerce platform built with Laravel + Vue.

## Authentication

- Primary API auth is Laravel Sanctum bearer tokens.
- `POST /api/v1/auth/register` and `POST /api/v1/auth/login` return `{ user, token, token_type, expires_at }`.
- Protected requests must send `Authorization: Bearer <token>`.
- The Vue SPA persists the token in memory with `localStorage` fallback and rehydrates the user via `GET /api/v1/auth/me`.
- Legacy cookie/session auth may still work for some stateful environments, but it is deprecated and no longer the primary contract.

## Security Highlights

- Chat access is limited to active participants only. Users with `left_at` set cannot read messages, mark reads, or update DND.
- Follower notifications require a real `user_follows` relationship. User-created notification subscriptions cannot spoof follower events.
- Fleet managers now have a dedicated ride-operations surface for dispatch, reassignment, cancellation, and active-trip monitoring.

## Architecture

```
┌─────────────────────────────────────────────────────────────────┐
│  Browser                                                        │
│                                                                 │
│   Vue 3 SPA (localhost:3000)                                    │
│   ├── Router (vue-router) — role-based guards, auth redirect    │
│   ├── Stores (Pinia) — auth, user session                       │
│   ├── Services                                                  │
│   │   ├── api.js — Axios wrapper, Bearer token, offline queue   │
│   │   └── offlineQueue.js — IndexedDB pending actions           │
│   └── Service Worker (Workbox) — shell cache, ride/chat cache   │
└──────────────────────┬──────────────────────────────────────────┘
                       │ HTTP  (Bearer token)
                       ▼
┌─────────────────────────────────────────────────────────────────┐
│  Laravel 11 API  (localhost:8000)                               │
│                                                                 │
│   Routes: /api/v1/*                                             │
│   ├── Auth        — register, login, logout, /me                │
│   ├── Rides       — rider orders, driver queue, fleet dispatch  │
│   ├── Vehicles    — CRUD + media upload                         │
│   ├── Products    — catalog, recommendations, purchases         │
│   ├── Notifications — inbox, unread count, subscriptions        │
│   ├── Reports     — distribution, templates, export (CSV/XLSX)  │
│   └── Chat        — group chat per ride, DND settings           │
│                                                                 │
│   Middleware: Sanctum auth, role guard, idempotency, token TTL  │
│   Scheduler: ride timers, recommendation batch (schedule:work)  │
└──────────────────────┬──────────────────────────────────────────┘
                       │
                       ▼
┌───────────────────────────────┐
│  MySQL 8  (localhost:3306)    │
│  Database: roadlink           │
└───────────────────────────────┘
```

**Key request flows:**

| Flow | Sequence |
|------|----------|
| Login | `POST /auth/login` → Sanctum issues opaque token → SPA stores in localStorage |
| Auth guard | Vue Router `beforeEach` → calls `authStore.initialize()` → validates token via `/auth/me` |
| Offline mutation | `navigator.onLine=false` → `api.js` queues action in IndexedDB → replays on reconnect |
| Role access | Router meta `roles[]` → checked in guard → unauthorized → redirect to `/dashboard` |

## Environment Variables

### Backend (`backend/.env`)

| Variable | Default | Description |
|----------|---------|-------------|
| `APP_ENV` | `local` | Laravel environment (`local`, `production`, `testing`) |
| `APP_KEY` | *(generated)* | 32-byte encryption key — auto-generated on first boot |
| `APP_URL` | `http://localhost:8000` | Base URL used in generated links |
| `DB_CONNECTION` | `mysql` | Database driver (`mysql` for runtime, `sqlite` for unit tests) |
| `DB_HOST` | `mysql` | Database host (Docker service name inside compose) |
| `DB_DATABASE` | `roadlink` | Database name |
| `DB_USERNAME` | `roadlink` | Database username |
| `DB_PASSWORD` | `roadlink` | Database password |
| `QUEUE_CONNECTION` | `sync` | Queue driver (`sync` runs jobs inline, no worker needed) |
| `SANCTUM_STATEFUL_DOMAINS` | *(optional)* | Comma-separated domains for cookie-based Sanctum sessions |

### Frontend (`frontend/.env` or Docker environment)

| Variable | Default | Description |
|----------|---------|-------------|
| `VITE_API_URL` | `http://localhost:8000/api/v1` | Backend API base URL used by the Vite build |
| `VITEST_API_URL` | `http://backend:8000/api/v1` | Backend URL for real HTTP integration tests (Docker service name) |
| `E2E_API_URL` | `http://127.0.0.1:8000/api/v1` | Backend URL for Playwright E2E tests |
| `E2E_WEB_URL` | `http://127.0.0.1:3000` | Frontend URL for Playwright E2E tests |
| `E2E_ALLOW_SKIP_UNSUPPORTED` | *(unset)* | Set to `1` to skip E2E tests instead of failing when services are unavailable |

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

> **All setup and execution must be done via Docker. No local PHP, Node, or database
> runtime is required on the host machine.**

From `repo/`:

```bash
docker-compose up
```

Or, to rebuild images after a dependency change:

```bash
docker-compose up --build
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

## Verification

### API Verification (step-by-step)

**Step 1 — log in and capture the token:**

```bash
curl -s -X POST http://localhost:8000/api/v1/auth/login \
  -H "Content-Type: application/json" \
  -d '{"username":"rider01","password":"Rider12345!"}' | tee /tmp/login.json
```

Expected response shape:

```json
{
  "user":  { "id": 2, "username": "rider01", "role": "rider" },
  "token": "<opaque-sanctum-token>",
  "token_type": "Bearer",
  "expires_at": "2026-05-17T..."
}
```

**Step 2 — verify the token with `/auth/me`:**

```bash
TOKEN=$(cat /tmp/login.json | python3 -c "import sys,json; print(json.load(sys.stdin)['token'])")

curl -s http://localhost:8000/api/v1/auth/me \
  -H "Authorization: Bearer $TOKEN"
```

Expected response:

```json
{
  "user": { "id": 2, "username": "rider01", "role": "rider" }
}
```

**Step 3 — create a ride order:**

```bash
curl -s -X POST http://localhost:8000/api/v1/ride-orders \
  -H "Content-Type: application/json" \
  -H "Authorization: Bearer $TOKEN" \
  -d '{
    "origin_address": "123 Main St",
    "destination_address": "Airport Terminal 1",
    "rider_count": 1,
    "time_window_start": "2026-05-18 10:00",
    "time_window_end":   "2026-05-18 12:00"
  }'
```

Expected: HTTP 201, `order.status == "matching"`.

**Step 4 — confirm backend health:**

```bash
curl -s http://localhost:8000/api/v1/readiness
# Expected: { "status": "ready", ... }
```

---

### Web Verification (step-by-step)

**Step 1 — open the frontend:**

Navigate to `http://localhost:3000` in your browser.  
You should be redirected to `http://localhost:3000/login`.

**Step 2 — log in with a seed account:**

- [ ] Enter username `rider01` and password `Rider12345!`
- [ ] Click **Sign In**
- [ ] Confirm redirect to `/dashboard`
- [ ] Dashboard title shows "Welcome back, rider01"
- [ ] Role badge shows **rider**

**Step 3 — create a ride:**

- [ ] Click **+ New Trip** (floating button, bottom-right)
- [ ] Fill in Origin, Destination, Date, Start time, End time
- [ ] Click **Create Trip**
- [ ] New trip card appears in the list with status **matching**

**Step 4 — verify dashboard summary cards:**

- [ ] **Trips** card shows at least 1
- [ ] **Notifications** count reflects inbox state
- [ ] **Inventory** shows available catalog products

**Step 5 — verify role isolation:**

- [ ] Log out (Logout button in sidebar)
- [ ] Log in as `driver01` / `Driver1234!`
- [ ] Confirm role badge shows **driver**
- [ ] Confirm the **My Trips** link is absent (rider-only)
- [ ] Confirm **Available Rides** link is present (driver-only)

---

## Testing Instructions

> **All tests run via Docker — no local PHP or Node installation required.**

From `repo/`:

```bash
# Backend tests (PHPUnit, SQLite in-memory)
docker-compose exec backend php artisan test

# Frontend unit + integration tests (Vitest)
docker-compose exec frontend npm run test

# Full suite via wrapper script
./run_tests.sh
```

Frontend build check:

```bash
docker-compose exec frontend npm run build
```

## Scheduler and Batch Jobs

- Backend scheduler is enabled via dedicated `scheduler` service and Laravel `schedule:work`.
- Recommendation engine runs from scheduled `ComputeRecommendations` job.
- Ride auto-cancel/revert/disband timers are scheduled in `backend/routes/console.php`.

## Docs Index

- API reference: `../docs/api-spec.md`
- Architecture and state machine: `../docs/design.md`
- Ambiguity decisions: `../docs/questions.md`
