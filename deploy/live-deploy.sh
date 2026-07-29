#!/usr/bin/env bash
set -euo pipefail

cd "$(dirname "$0")/.."

if [ ! -f ".env" ]; then
	echo "Missing .env on live server. Deployment stopped."
	exit 1
fi

if grep -q '^APP_ENV=local' .env; then
	echo "Live deployment refused because APP_ENV=local."
	exit 1
fi

echo "Installing PHP dependencies..."
composer install --no-dev --prefer-dist --optimize-autoloader --no-interaction

echo "Installing Node dependencies..."
npm ci --no-audit --no-fund

echo "Building production assets..."
npm run build
rm -f public/hot

echo "Running Laravel deployment tasks..."
php artisan migrate --force
php artisan storage:link || true
php artisan livewire:publish --assets || true
php artisan optimize:clear
php artisan settings:discover
php artisan optimize

chmod -R ug+rwX storage bootstrap/cache public/build || true

echo "Deployment finished."
