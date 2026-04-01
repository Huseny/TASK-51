#!/usr/bin/env bash
set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
cd "$ROOT_DIR"

if ! command -v docker >/dev/null 2>&1; then
  echo "[run_tests.sh] docker is required but was not found in PATH."
  exit 1
fi

echo "[run_tests.sh] Waiting for backend to finish startup..."
MAX_WAIT=120
ELAPSED=0
until docker compose exec -T backend test -f /var/www/html/vendor/autoload.php 2>/dev/null; do
  if [ "$ELAPSED" -ge "$MAX_WAIT" ]; then
    echo "[run_tests.sh] Backend did not become ready in ${MAX_WAIT}s. Dumping logs:"
    docker compose logs backend
    exit 1
  fi
  sleep 2
  ELAPSED=$((ELAPSED + 2))
done
echo "[run_tests.sh] Backend is ready."

echo "[run_tests.sh] Running backend tests..."
docker compose exec -T backend php artisan test --compact

echo "[run_tests.sh] Running frontend tests..."
docker compose exec -T frontend npm run test

echo "[run_tests.sh] All test commands completed."