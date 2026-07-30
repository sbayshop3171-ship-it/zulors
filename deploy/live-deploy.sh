#!/usr/bin/env bash
set -euo pipefail

cd "$(dirname "$0")/.."

INSTALL_DEPS="${INSTALL_DEPS:-1}"
BUILD_ASSETS="${BUILD_ASSETS:-1}"
RUN_MIGRATIONS="${RUN_MIGRATIONS:-1}"

if [ ! -f ".env" ]; then
	echo "Missing .env on live server. Deployment stopped."
	exit 1
fi

if grep -q '^APP_ENV=local' .env; then
	echo "Live deployment refused because APP_ENV=local."
	exit 1
fi

normalize_permissions() {
	chmod 755 . || true
    chmod -R ug+rwX storage bootstrap/cache 2>/dev/null || true
    chmod -R a+rX public bootstrap config database deploy lang resources routes scripts services var vendor 2>/dev/null || true
    chmod -R a+rX storage/app/public 2>/dev/null || true
}

mkdir -p bootstrap/cache storage/frontend storage/framework/cache storage/framework/sessions storage/framework/views storage/logs
normalize_permissions

if [ "$INSTALL_DEPS" = "1" ]; then
	echo "Installing PHP dependencies..."
	composer install --no-dev --prefer-dist --optimize-autoloader --no-interaction

	echo "Installing Node dependencies..."
	npm ci --no-audit --no-fund
else
	echo "Skipping dependency installation."
fi

if [ "$BUILD_ASSETS" = "1" ]; then
	echo "Building production assets..."
	npm run build
	rm -f public/hot
else
	echo "Skipping asset build."
fi

echo "Running PHP syntax and route preflight..."
find app bootstrap config database routes public -type f -name '*.php' -print0 \
	| xargs -0 -n1 php -l >/dev/null
php artisan route:list --no-ansi >/dev/null

echo "Running Laravel deployment tasks..."
if [ "$RUN_MIGRATIONS" = "1" ]; then
	php artisan migrate --force
else
	echo "Skipping database migrations."
fi

php artisan storage:link || true
php artisan livewire:publish --assets || true
php artisan optimize:clear
php artisan settings:discover
php artisan optimize

normalize_permissions
chmod -R ug+rwX storage bootstrap/cache public/build 2>/dev/null || true

echo "Deployment finished."
