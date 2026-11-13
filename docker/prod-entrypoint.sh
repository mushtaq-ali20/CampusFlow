#!/bin/sh
# Prepares the production container on each start: applies new migrations and caches the configuration,
# routes and views from the environment variables set on the container app, then starts FrankenPHP.
set -e

cd /app

if [ "${AUTO_MIGRATE:-true}" = "true" ]; then
    echo "[prod-entrypoint] Running database migrations"
    php artisan migrate --force
fi

echo "[prod-entrypoint] Caching configuration, routes and views"
php artisan optimize

exec docker-php-entrypoint "$@"
