#!/usr/bin/env bash
set -euo pipefail

cd "$(dirname "$0")/.."

INSTALL_DEPS="${INSTALL_DEPS:-1}"
BUILD_ASSETS="${BUILD_ASSETS:-1}"
RUN_MIGRATIONS="${RUN_MIGRATIONS:-1}"
SHARED_STORAGE_PUBLIC_PATH="${SHARED_STORAGE_PUBLIC_PATH:-}"
SHARED_STORAGE_SESSIONS_PATH="${SHARED_STORAGE_SESSIONS_PATH:-}"
MEDIA_GUARD_MIN_USER_FILES="${MEDIA_GUARD_MIN_USER_FILES:-0}"
APP_RUNTIME_USER="${APP_RUNTIME_USER:-$(stat -c '%U' .env 2>/dev/null || id -un)}"
APP_RUNTIME_GROUP="${APP_RUNTIME_GROUP:-www-data}"

if [ ! -f ".env" ]; then
	echo "Missing .env on live server. Deployment stopped."
	exit 1
fi

if grep -q '^APP_ENV=local' .env; then
	echo "Live deployment refused because APP_ENV=local."
	exit 1
fi

count_user_media_files() {
	local media_root="$1"

	if [ ! -d "$media_root" ]; then
		echo 0
		return
	fi

	{
		find "$media_root/uploads/users/avatars" "$media_root/uploads/users/covers" -type f 2>/dev/null || true
	} | wc -l | tr -d ' '
}

ensure_shared_public_storage() {
	if [ -z "$SHARED_STORAGE_PUBLIC_PATH" ]; then
		return
	fi

	mkdir -p "$(dirname "$SHARED_STORAGE_PUBLIC_PATH")" "$SHARED_STORAGE_PUBLIC_PATH" storage/app

	local shared_target=""
	local current_target=""
	shared_target="$(readlink -f "$SHARED_STORAGE_PUBLIC_PATH" 2>/dev/null || true)"
	current_target="$(readlink -f storage/app/public 2>/dev/null || true)"

	if [ -d storage/app/public ] && [ "$current_target" != "$shared_target" ]; then
		echo "Migrating existing public uploads into shared media storage..."
		rsync -a storage/app/public/ "$SHARED_STORAGE_PUBLIC_PATH/"
	fi

	if [ "$current_target" != "$shared_target" ]; then
		rm -rf storage/app/public
		ln -s "$SHARED_STORAGE_PUBLIC_PATH" storage/app/public
	fi

	local shared_user_files
	shared_user_files="$(count_user_media_files "$SHARED_STORAGE_PUBLIC_PATH")"

	if [ "$MEDIA_GUARD_MIN_USER_FILES" -gt 0 ] && [ "$shared_user_files" -lt "$MEDIA_GUARD_MIN_USER_FILES" ]; then
		echo "Deployment stopped: shared media guard expected at least ${MEDIA_GUARD_MIN_USER_FILES} user media files, found ${shared_user_files}."
		exit 1
	fi
}

ensure_shared_sessions_storage() {
	if [ -z "$SHARED_STORAGE_SESSIONS_PATH" ]; then
		return
	fi

	mkdir -p "$(dirname "$SHARED_STORAGE_SESSIONS_PATH")" "$SHARED_STORAGE_SESSIONS_PATH" storage/framework

	local shared_target=""
	local current_target=""
	shared_target="$(readlink -f "$SHARED_STORAGE_SESSIONS_PATH" 2>/dev/null || true)"
	current_target="$(readlink -f storage/framework/sessions 2>/dev/null || true)"

	if [ -d storage/framework/sessions ] && [ "$current_target" != "$shared_target" ]; then
		echo "Migrating existing login sessions into shared session storage..."
		rsync -a storage/framework/sessions/ "$SHARED_STORAGE_SESSIONS_PATH/"
	fi

	if [ "$current_target" != "$shared_target" ]; then
		rm -rf storage/framework/sessions
		ln -s "$SHARED_STORAGE_SESSIONS_PATH" storage/framework/sessions
	fi
}

link_public_storage() {
	mkdir -p public storage/app

	if [ -L public/storage ]; then
		unlink public/storage
	elif [ -e public/storage ]; then
		echo "Deployment stopped: public/storage exists and is not a symlink."
		exit 1
	fi

	ln -s ../storage/app/public public/storage
}

normalize_permissions() {
	chmod 755 . || true
    chown -R "${APP_RUNTIME_USER}:${APP_RUNTIME_GROUP}" storage bootstrap/cache 2>/dev/null || true
    chmod -R ug+rwX storage bootstrap/cache 2>/dev/null || true
    find storage bootstrap/cache -type d -exec chmod 2775 {} + 2>/dev/null || true
    find storage bootstrap/cache -type f -exec chmod 664 {} + 2>/dev/null || true
    chmod -R a+rX public bootstrap config database deploy lang resources routes scripts services var vendor 2>/dev/null || true
    chmod -R a+rX storage/app/public 2>/dev/null || true
}

mkdir -p bootstrap/cache storage/app storage/frontend storage/framework/cache storage/framework/sessions storage/framework/views storage/logs
if [ -z "$SHARED_STORAGE_PUBLIC_PATH" ]; then
	mkdir -p storage/app/public
fi
ensure_shared_public_storage
ensure_shared_sessions_storage
link_public_storage
if [ ! -s storage/frontend/build.num ]; then
	date +%s > storage/frontend/build.num
fi
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

ensure_shared_public_storage
ensure_shared_sessions_storage
link_public_storage
php artisan livewire:publish --assets || true
php artisan optimize:clear
php artisan settings:discover
php artisan optimize

normalize_permissions
chmod -R ug+rwX storage bootstrap/cache public/build 2>/dev/null || true
ensure_shared_public_storage
ensure_shared_sessions_storage
link_public_storage

echo "Deployment finished."
