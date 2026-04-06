#!/usr/bin/env bash
set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
cd "$ROOT_DIR"
export TERM="${TERM:-xterm-256color}"

if ! command -v docker >/dev/null 2>&1; then
  echo "[run_tests.sh] docker is required but was not found in PATH."
  exit 1
fi

wait_for_container() {
  local service="$1"
  local check_cmd="$2"
  local label="$3"
  local attempts=120
  local delay=2

  echo "[run_tests.sh] Waiting for ${service} to be ready (${label})..."

  for ((i=1; i<=attempts; i++)); do
    if docker compose exec -T "$service" sh -c "$check_cmd" >/dev/null 2>&1; then
      echo "[run_tests.sh] ${service} is ready (${label})."
      return 0
    fi

    local status
    status=$(docker compose ps --format '{{.State}}' "$service" 2>/dev/null || echo "unknown")
    if [ "$status" = "exited" ] || [ "$status" = "dead" ]; then
      echo "[run_tests.sh] ERROR: ${service} container has stopped (state: ${status})."
      docker compose logs --tail=40 "$service" || true
      return 1
    fi

    echo "[run_tests.sh] ${service} not ready yet (attempt ${i}/${attempts}), retrying in ${delay}s..."
    sleep "$delay"
  done

  echo "[run_tests.sh] ERROR: ${service} did not become ready after $((attempts * delay))s."
  echo "[run_tests.sh] Last ${service} logs:"
  docker compose logs --tail=80 "$service" || true
  return 1
}

wait_for_backend_ready() {
  wait_for_container "backend" \
    "php -r '\$ctx = stream_context_create([\"http\" => [\"timeout\" => 2]]); \$body = @file_get_contents(\"http://127.0.0.1:8000/up\", false, \$ctx); exit(\$body === false ? 1 : 0);'" \
    "http server"
}

wait_for_frontend_ready() {
  wait_for_container "frontend" \
    "test -d node_modules" \
    "npm install"
}

echo "[run_tests.sh] Building and starting containers..."
docker compose up --build -d mysql backend scheduler frontend

wait_for_backend_ready
wait_for_frontend_ready

echo "[run_tests.sh] Running backend tests..."
docker compose exec -T backend php artisan test --compact

echo "[run_tests.sh] Running frontend tests..."
docker compose exec -T frontend npm run test

echo "[run_tests.sh] All test commands completed."
