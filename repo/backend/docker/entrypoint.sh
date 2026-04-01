#!/bin/sh
set -e

if [ ! -f ".env" ] && [ -f ".env.example" ]; then
  cp .env.example .env
fi

set_env_var() {
  key="$1"
  value="$2"

  if [ -z "$value" ]; then
    return
  fi

  escaped_value=$(printf '%s' "$value" | sed 's/[&|]/\\&/g')

  if grep -q "^${key}=" .env; then
    sed -i "s|^${key}=.*|${key}=${escaped_value}|" .env
  else
    printf "\n%s=%s\n" "$key" "$value" >> .env
  fi
}

set_env_var "DB_CONNECTION" "${DB_CONNECTION:-}"
set_env_var "DB_HOST" "${DB_HOST:-}"
set_env_var "DB_PORT" "${DB_PORT:-}"
set_env_var "DB_DATABASE" "${DB_DATABASE:-}"
set_env_var "DB_USERNAME" "${DB_USERNAME:-}"
set_env_var "DB_PASSWORD" "${DB_PASSWORD:-}"

mkdir -p bootstrap/cache storage/framework/cache storage/framework/sessions storage/framework/views
chmod -R 775 bootstrap/cache storage/framework || true

composer install --no-interaction --prefer-dist

if ! grep -q "^APP_KEY=base64:" .env; then
  php artisan key:generate --force
fi

if [ "$DB_CONNECTION" = "mysql" ]; then
  echo "Waiting for MySQL..."
  for i in $(seq 1 60); do
    php -r "try { new PDO('mysql:host=' . getenv('DB_HOST') . ';port=' . getenv('DB_PORT') . ';dbname=' . getenv('DB_DATABASE'), getenv('DB_USERNAME'), getenv('DB_PASSWORD')); echo 'ok'; } catch (Throwable $e) { exit(1); }" >/dev/null 2>&1 && break
    sleep 2
  done
fi

rm -f bootstrap/cache/config.php
php artisan config:clear || true

php artisan migrate --force

php artisan storage:link || true

USER_COUNT=$(php artisan tinker --execute="echo Illuminate\\Support\\Facades\\Schema::hasTable('users') ? App\\Models\\User::count() : 0;" --no-interaction)

if [ "${USER_COUNT:-0}" -eq 0 ]; then
  php artisan db:seed --force
fi

exec "$@"
