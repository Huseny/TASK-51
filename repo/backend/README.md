# RoadLink Backend (Laravel API)

> **All setup and execution must be done via Docker. No local PHP, Composer, or MySQL
> installation is required on the host machine.**
>
> See the root `README.md` for the full verification checklist and quick-start steps.

## Running Tests

From `repo/`:

```bash
# Run backend tests via Docker (SQLite in-memory, no MySQL needed)
docker-compose exec backend php artisan test
```

Or use the all-in-one wrapper:

```bash
./run_tests.sh
```

Tests use the `phpunit.xml` environment overrides:

- `DB_CONNECTION=sqlite`
- `DB_DATABASE=:memory:`
- `QUEUE_CONNECTION=sync`

## Report Export Destination Semantics

- `directory_id` in `POST /api/v1/reports/export` must be selected from approved export roots (`GET /api/v1/reports/export-directories`).
- The selected directory resolves to a configured local relative root (`config/reports.php`), not arbitrary user-provided filesystem paths.
- Stored path remains rooted under local storage and signed download authorization is unchanged.
- Unknown directories, unsafe characters, and traversal-like root config are rejected.

## Security Notes

- Report downloads require: valid signed URL + `auth:sanctum` + non-expired token + role (`admin`/`fleet_manager`) + ownership authorization.
- Media downloads require signed URL and object-level owner/admin checks.
- Group chat endpoints require active membership (`left_at IS NULL`) for read, receipt, and DND operations.
- Follower notification scenarios are authorized only by persisted `user_follows` relationships, not by notification subscriptions.
- Fleet operations are exposed under `/api/v1/fleet/*` for `fleet_manager` / `admin` dispatch workflows.

## Auth + Notification Config

- `SANCTUM_TOKEN_EXPIRATION` defaults to `720` minutes (12-hour bearer token window).
- Notification channels are selected via `ROADLINK_NOTIFICATION_CHANNELS` (default `in_app`).
- SMS delivery adapter can be listed as a channel, but is disabled by default unless `ROADLINK_SMS_ENABLED=true`.
- `POST /api/v1/auth/register` and `POST /api/v1/auth/login` return bearer-token payloads: `user`, `token`, `token_type`, `expires_at`.
- Protected routes use `auth:sanctum` and expect `Authorization: Bearer <token>`.
- `POST /api/v1/auth/logout` revokes the current access token.
- Stateful session auth may still exist in some local setups, but it is deprecated for API clients.
- Recommendation policy is epsilon-greedy via `ROADLINK_RECOMMENDATION_EPSILON` (default `0.10`) and `ROADLINK_RECOMMENDATION_MAX_PER_SELLER` (default `2`).
- Recommendation runs persist structured feature sets and feature values by version for deterministic replay.

## Notification Scenarios

- `POST /api/v1/notifications/events` can publish explicit in-app scenarios for comment, reply, mention, follower, moderation, and announcement events.
- Moderation and announcement scenarios are restricted to `admin` / `fleet_manager` actors.
- Notification aggregation keeps unread group dedupe (e.g. grouped replies become `N new replies`).

## Readiness + Schema Drift Recovery

- `GET /api/v1/readiness` reports whether runtime schema is ready for ride completion notifications.
- If `notification_frequency_type_column` is `false`, ride completion still degrades safely, but you should migrate immediately.
- Application startup logs a warning when `notification_frequency_logs.type` is missing.

To recover from schema drift:

```bash
docker-compose exec backend php artisan migrate
docker-compose exec backend php artisan optimize:clear
docker-compose exec backend php artisan test
```
