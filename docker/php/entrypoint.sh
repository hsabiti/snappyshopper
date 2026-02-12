#!/usr/bin/env bash
set -euo pipefail

cd /app

# Ensure .env exists
if [ ! -f .env ]; then
  echo "No .env found, copying from .env.example"
  cp .env.example .env
fi

# Always trust docker-compose env and patch .env accordingly
set_env () {
  local key="$1"
  local val="$2"
  if grep -qE "^${key}=" .env; then
    sed -i "s|^${key}=.*|${key}=${val}|" .env
  else
    echo "${key}=${val}" >> .env
  fi
}

# Values from docker-compose environment (with safe defaults)
set_env DB_CONNECTION "${DB_CONNECTION:-mysql}"
set_env DB_HOST       "${DB_HOST:-db}"
set_env DB_PORT       "${DB_PORT:-3306}"
set_env DB_DATABASE   "${DB_DATABASE:-snappy}"
set_env DB_USERNAME   "${DB_USERNAME:-snappy}"
set_env DB_PASSWORD   "${DB_PASSWORD:-snappy}"
set_env CACHE_DRIVER  "${CACHE_DRIVER:-file}"
set_env API_KEY       "${API_KEY:-change-me}"

# Git safe dir (composer can call git)
git config --global --add safe.directory /app || true

# Ensure vendor deps
if [ ! -d vendor ]; then
  echo "Installing composer dependencies..."
  composer install --no-interaction --prefer-dist
fi

# Ensure app key
if ! grep -q "^APP_KEY=base64:" .env; then
  echo "Generating APP_KEY..."
  php artisan key:generate --force
fi

# Clear cached config so .env changes apply
php artisan config:clear || true
php artisan cache:clear || true

# Wait for DB (use env vars, not .env)
echo "Waiting for MySQL..."
for i in {1..60}; do
  if mysql -h "${DB_HOST:-db}" -u"${DB_USERNAME:-snappy}" -p"${DB_PASSWORD:-snappy}" -e "SELECT 1" "${DB_DATABASE:-snappy}" >/dev/null 2>&1; then
    echo "MySQL is up"
    break
  fi
  sleep 2
done

echo "Running migrations..."
php artisan migrate --force

echo "Importing sample postcodes..."
php artisan import:postcodes || true

echo "Starting Laravel on 0.0.0.0:8000"
exec php artisan serve --host=0.0.0.0 --port=8000
