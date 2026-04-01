#!/usr/bin/env bash
set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"

cd "$ROOT_DIR"

if ! command -v docker >/dev/null 2>&1; then
  echo "[run.sh] docker is required but was not found in PATH."
  exit 1
fi

echo "[run.sh] Starting required Docker services (mysql, backend, frontend)..."
docker compose up -d --build mysql backend frontend

echo "[run.sh] Running backend tests in Docker..."
docker compose exec backend php artisan test --compact

echo "[run.sh] Running frontend tests in Docker..."
docker compose exec frontend npm run test

echo "[run.sh] All test commands completed."
