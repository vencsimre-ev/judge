#!/bin/sh
set -e

echo "Install Composer dependencies"
composer install --no-interaction --prefer-dist

echo "Prepare Laravel writable directories"
mkdir -p storage/app/public/score-sheets storage/framework/cache storage/framework/sessions storage/framework/views storage/logs bootstrap/cache
chown -R www-data:www-data storage bootstrap/cache

if [ -z "$APP_KEY" ]; then
  echo "APP_KEY is empty. Generate one with: docker compose exec app php artisan key:generate"
fi

echo "Run migrations"
php artisan migrate --force

echo "Create storage link"
php artisan storage:link || true

php artisan optimize:clear

apache2-foreground
