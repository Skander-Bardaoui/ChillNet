#!/bin/sh
set -eu

cd /var/www/html

# Runtime directories must exist and be writable by the Apache worker.
mkdir -p storage/framework/cache storage/framework/sessions storage/framework/views storage/logs bootstrap/cache
chown -R www-data:www-data storage bootstrap/cache

php artisan storage:link || true

# The environment is only known at container start, so caches are built here
# rather than at image build time.
php artisan config:cache
php artisan route:cache || true
php artisan view:cache || true

exec "$@"
