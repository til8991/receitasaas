#!/bin/sh
# Caches config/routes/views with the runtime environment (env vars come from docker compose).
set -e

cd /var/www/backend

if [ -z "${APP_KEY:-}" ]; then
  echo "APP_KEY não definido. Gere com: docker compose run --rm php php artisan key:generate --show" >&2
  exit 1
fi

php artisan config:cache >/dev/null
php artisan route:cache >/dev/null
php artisan view:cache >/dev/null
php artisan event:cache >/dev/null

exec "$@"
