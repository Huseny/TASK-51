# RoadLink Frontend (Vue + Vite)

## What this app includes

- Vue 3 SPA with Pinia state management
- Role-based navigation/route guards
- PWA service worker + offline mutation queue
- Vitest suite for stores, services, and routing behavior

## Local Non-Docker Run

From `repo/frontend`:

```bash
npm install
npm run dev -- --host 0.0.0.0 --port 3000
```

Environment override (optional):

```bash
VITE_API_URL=http://localhost:8000/api/v1
```

## Build & Test

From `repo/frontend`:

```bash
npm run test
npm run build
```

### Windows PowerShell Troubleshooting

Symptom:

- `npm.ps1 cannot be loaded because running scripts is disabled`

Fallback (PowerShell) commands:

```bash
npm.cmd run test
npm.cmd run build
npm.cmd run test:e2e
```

This is fallback guidance only; default `npm run ...` commands remain the primary flow.

## Executable E2E (Playwright)

Critical lifecycle, cache-isolation, offline-replay, and notification-scenario flows are automated in `tests/e2e/ride-lifecycle-and-reports.e2e.spec.js`.

Recommended startup sequence (two terminals):

1) Backend (`repo/backend`)

```bash
php artisan serve --host=127.0.0.1 --port=8000
```

2) Frontend (`repo/frontend`)

```bash
npm run dev -- --host 127.0.0.1 --port 3000
```

Then run E2E:

```bash
npm run test:e2e
```

Expected behavior:

- exits `0` only when Playwright passes
- exits non-zero when Playwright fails or services are unreachable
- uses `E2E_WEB_URL` (default `http://127.0.0.1:3100`) and `E2E_API_URL` (default `http://127.0.0.1:8000/api/v1`)

Auth behavior:

- SPA auth uses secure cookie-backed session (`auth:sanctum`) with CSRF protection
- backend must expose `GET /sanctum/csrf-cookie` and allow credentials from frontend origin
- logout and account-switch flows purge auth-scoped service-worker caches (rides/chat) to prevent cross-user cached API leakage

If backend/runtime is unavailable in your environment, use:

```bash
npm run test:e2e:skip-if-unavailable
```

Playwright artifacts on failure are stored in:

- `repo/frontend/test-results/playwright/`
- includes retained traces, screenshots, and videos for failed tests.

## Docker Run

From `repo`:

```bash
docker compose up --build frontend
```

## Known Boundaries

- Full stack end-to-end verification depends on backend + MySQL running.
- Offline queue replay and service worker behavior are integration-tested at service/unit level in Vitest; browser-level SW assertions are not fully simulated in jsdom.

## Dashboard Summary Cards

- Dashboard cards now consume live API summaries (`/ride-orders` or `/driver/my-rides`, `/products`, `/notifications/unread-count`) instead of placeholder text.

## Report Export Directory Selection

- Reports export UI now uses approved backend directory choices (`/reports/export-directories`) instead of free-text destination input.

## Security Note (Session Auth)

- Frontend no longer persists access tokens in `localStorage`.
- Auth now relies on HttpOnly cookie sessions and CSRF-protected requests.
- Session clear/logout removes cached user, unread counters, toast state, and queued offline mutations.
