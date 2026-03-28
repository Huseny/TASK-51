# RoadLink Mobility Commerce Platform

Monorepo scaffold for an offline-capable commerce + mobility platform.

## Stack

- Backend: Laravel 11 (`repo/backend`)
- Frontend: Vue 3 + Vite + Pinia (`repo/frontend`)
- Database: MySQL 8
- Auth: Local username/password with Sanctum tokens

## Quick Start (Docker)

1. From `repo/`, run:

   ```bash
   make setup
   ```

2. Access apps:
   - Frontend: `http://localhost:3000`
   - Backend API: `http://localhost:8000/api/v1`

3. Stop services:

   ```bash
   make down
   ```

## Manual Start (without Docker)

Backend (`repo/backend`):

```bash
composer install
cp .env.example .env
php artisan key:generate
php artisan migrate
php artisan db:seed
php artisan serve --host=0.0.0.0 --port=8000
```

Frontend (`repo/frontend`):

```bash
npm install
cp .env.example .env
npm run dev -- --host 0.0.0.0 --port 3000
```

## Seeded Users

| Username | Password | Role |
| --- | --- | --- |
| admin01 | Admin12345! | admin |
| rider01 | Rider12345! | rider |
| rider02 | Rider12345! | rider |
| driver01 | Driver1234! | driver |
| driver02 | Driver1234! | driver |
| fleet01 | Fleet12345! | fleet_manager |

## Authentication Rules Implemented

- Username/password local auth only
- Password: minimum 10 chars, at least one letter + one number
- Lockout: 15 minutes after 5 failed attempts
- Session token expiry: 12 hours
- Sensitive fields hidden and encrypted at rest (`email`, `phone`)

## Running Tests

From `repo/`:

```bash
make test
```

Or individually:

```bash
docker compose run --rm backend php artisan test
docker compose run --rm frontend npm run test
```

## Key API Endpoints

- `POST /api/v1/auth/register`
- `POST /api/v1/auth/login`
- `POST /api/v1/auth/logout`
- `GET /api/v1/auth/me`
- `POST /api/v1/ride-orders` (rider)
- `GET /api/v1/ride-orders` (rider scoped)
- `GET /api/v1/ride-orders/{id}` (owner/admin via policy)
- `PATCH /api/v1/ride-orders/{id}/transition` (rider cancel)

## Ride Order Automation

- Auto-cancel unmatched rides (10m): `php artisan ride:auto-cancel-unmatched`
- Auto-revert no-show accepted rides (5m): `php artisan ride:auto-revert-no-show`
- Both commands are scheduled every minute in `routes/console.php`
