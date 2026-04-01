#!/usr/bin/env bash
set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
cd "$ROOT_DIR"

if ! command -v docker >/dev/null 2>&1; then
  echo "[run_tests.sh] docker is required but was not found in PATH."
  exit 1
fi

echo "[run_tests.sh] Running backend tests..."
docker compose exec -T backend php artisan test --compact

echo "[run_tests.sh] Running frontend tests..."
docker compose exec -T frontend npm run test

echo "[run_tests.sh] All test commands completed."