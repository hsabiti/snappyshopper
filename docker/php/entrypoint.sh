#!/usr/bin/env bash
set -euo pipefail

cd /app

# Ensure .env exists
if [ ! -f .env ]; then
  echo "No .env found, copying from .env.example"
  cp .env.example .env

  # Force DB settings for Docker runtime (prevents local .env values breaking container)
    php -r '
    $env = file_exists(".env") ? file(".env", FILE_IGNORE_NEW_LINES) : [];
    $set = [
    "DB_CONNECTION" => getenv("DB_CONNECTION") ?: "mysql",
    "DB_HOST"       => getenv("DB_HOST") ?: "db",
    "DB_PORT"       => getenv("DB_PORT") ?: "3306",
    "DB_DATABASE"   => getenv("DB_DATABASE") ?: "snappy",
    "DB_USERNAME"   => getenv("DB_USERNAME") ?: "snappy",
    "DB_PASSWORD"   => getenv("DB_PASSWORD") ?: "snappy",
    "CACHE_DRIVER"  => getenv("CACHE_DRIVER") ?: "file",
    "API_KEY"       => getenv("API_KEY") ?: "change-me",
    ];

    $map = [];
    foreach ($env as $line) {
    if (preg_match("/^([A-Z0-9_]+)=(.*)$/", $line, $m)) $map[$m[1]] = $m[2];
    }
    foreach ($set as $k => $v) {
    $map[$k] = $v;
    }
    $out = [];
    foreach ($map as $k => $v) $out[] = $k."=".$v;
    file_put_contents(".env", implode(PHP_EOL, $out).PHP_EOL);
    '
fi

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

# Ensure DB settings (docker defaults)
php artisan config:clear || true

# Wait for DB
echo "Waiting for MySQL..."
for i in {1..60}; do
  if mysql -h "${DB_HOST:-db}" -u"${DB_USERNAME:-snappy}" -p"${DB_PASSWORD:-snappy}" -e "SELECT 1" "${DB_DATABASE:-snappy}" >/dev/null 2>&1; then
    echo "MySQL is up"
    break
  fi
  sleep 2
done

# Migrate + seed (seed will be added later)
echo "Running migrations..."
php artisan migrate --force

echo "Importing sample postcodes..."
php artisan import:postcodes || true


# Start server
echo "Starting Laravel on 0.0.0.0:8000"
exec php artisan serve --host=0.0.0.0 --port=8000
