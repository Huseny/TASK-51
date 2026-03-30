# RoadLink Backend (Laravel API)

## Local Non-Docker Verification

### Path A: Run tests only (default SQLite in-memory)

Use this path for default automated verification.

`php artisan test` uses SQLite in-memory by default via `phpunit.xml`:

- `DB_CONNECTION=sqlite`
- `DB_DATABASE=:memory:`

From `repo/backend`:

```bash
cp .env.example .env
composer install
php artisan key:generate
php artisan test
```

MySQL is not required for this default test path.

### Path B: Run app locally with MySQL (manual runtime/API verification)

Use this path when you want to run the backend server and manually verify API behavior against a local MySQL instance.

Prerequisites:

- PHP 8.2+
- Composer 2+
- MySQL 8+
- PHP extensions: `pdo`, `pdo_mysql`, `mbstring`, `openssl`, `tokenizer`, `xml`, `ctype`, `json`

From `repo/backend`:

```bash
cp .env.example .env
composer install
php artisan key:generate
php artisan migrate:fresh --seed
php artisan serve --host=127.0.0.1 --port=8000
```

Optional: if you want MySQL-backed tests instead of default SQLite in-memory, override DB env vars for the test command, for example:

```bash
DB_CONNECTION=mysql DB_DATABASE=your_test_db DB_USERNAME=your_user DB_PASSWORD=your_password php artisan test
```

Or run the bundled verification script:

```bash
bash scripts/verify-local.sh
```

### Verification Checklist (Expected Pass Indicators)

1. `composer install` completes without dependency errors.
2. `php artisan key:generate` returns success and sets `APP_KEY`.
3. `php artisan migrate:fresh --seed` completes with all migrations/seeders applied.
4. `php artisan test` finishes with all tests passing.
5. Quick smoke endpoint checks:
   - `GET /api/v1/auth/me` without token returns `401` JSON.
   - `GET /api/v1/reports/trends` without token returns `401` JSON.

If any step fails, confirm DB connectivity (`DB_*` values in `.env`) and required PHP extensions.

## Report Export Destination Semantics

- `directory_id` in `POST /api/v1/reports/export` must be selected from approved export roots (`GET /api/v1/reports/export-directories`).
- The selected directory resolves to a configured local relative root (`config/reports.php`), not arbitrary user-provided filesystem paths.
- Stored path remains rooted under local storage and signed download authorization is unchanged.
- Unknown directories, unsafe characters, and traversal-like root config are rejected.

## Security Notes

- Report downloads require: valid signed URL + `auth:sanctum` + non-expired token + role (`admin`/`fleet_manager`) + ownership authorization.
- Media downloads require signed URL and object-level owner/admin checks.

## Session + Notification Config

- `SESSION_LIFETIME` defaults to `720` minutes (12-hour web session window).
- Notification channels are selected via `ROADLINK_NOTIFICATION_CHANNELS` (default `in_app`).
- SMS delivery adapter can be listed as a channel, but is disabled by default unless `ROADLINK_SMS_ENABLED=true`.
- `POST /api/v1/auth/register` and `POST /api/v1/auth/login` return user/session data only (no bearer token in JSON).
- Recommendation policy is epsilon-greedy via `ROADLINK_RECOMMENDATION_EPSILON` (default `0.10`) and `ROADLINK_RECOMMENDATION_MAX_PER_SELLER` (default `2`).

## Notification Scenarios

- `POST /api/v1/notifications/events` can publish explicit in-app scenarios for comment, reply, mention, follower, moderation, and announcement events.
- Moderation and announcement scenarios are restricted to `admin` / `fleet_manager` actors.
- Notification aggregation keeps unread group dedupe (e.g. grouped replies become `N new replies`).

## Readiness + Schema Drift Recovery

- `GET /api/v1/readiness` reports whether runtime schema is ready for ride completion notifications.
- If `notification_frequency_type_column` is `false`, ride completion still degrades safely, but you should migrate immediately.
- Application startup logs a warning when `notification_frequency_logs.type` is missing.

Local non-Docker recovery steps from `repo/backend`:

```bash
php artisan migrate
php artisan optimize:clear
php artisan test
```

For SQLite local verification (no MySQL driver):

```bash
DB_CONNECTION=sqlite DB_DATABASE="$(pwd)/database/database.sqlite" php artisan migrate
```
