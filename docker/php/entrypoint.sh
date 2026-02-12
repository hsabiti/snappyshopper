cat > docker/php/entrypoint.sh <<'EOF'
#!/usr/bin/env bash
set -euo pipefail

cd /app

# Ensure .env exists
if [ ! -f .env ]; then
  echo "No .env found, copying from .env.example"
  cp .env.example .env
fi

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

# Start server
echo "Starting Laravel on 0.0.0.0:8000"
exec php artisan serve --host=0.0.0.0 --port=8000
EOF
