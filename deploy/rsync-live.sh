#!/usr/bin/env bash
set -euo pipefail

ROOT_DIR="$(cd "$(dirname "$0")/.." && pwd)"
LIVE_HOST="${LIVE_HOST:-5.189.168.13}"
LIVE_USER="${LIVE_USER:-zulors}"
LIVE_PORT="${LIVE_PORT:-22}"
LIVE_PATH="${LIVE_PATH:-/var/www/zulors/data/www/zulors.com}"
LIVE_SSH_KEY="${LIVE_SSH_KEY:-$HOME/.ssh/zulors_live_deploy}"
LIVE_URL="${LIVE_URL:-https://zulors.com}"
SHARED_STORAGE_PUBLIC="${SHARED_STORAGE_PUBLIC:-${LIVE_PATH}.shared/storage/app/public}"

if [ ! -f "$LIVE_SSH_KEY" ]; then
	echo "Missing SSH key: $LIVE_SSH_KEY"
	echo "Set LIVE_SSH_KEY or create the deploy key first."
	exit 1
fi

echo "Running local deployment preflight..."
git -C "$ROOT_DIR" diff --check
if [ "${ALLOW_DIRTY_DEPLOY:-0}" != "1" ] && [ -n "$(git -C "$ROOT_DIR" status --porcelain --untracked-files=normal)" ]; then
	echo "Live deployment refused: the workspace has uncommitted changes."
	echo "Commit and push the intended changes first, or set ALLOW_DIRTY_DEPLOY=1 only for an emergency deployment."
	exit 1
fi
if command -v php >/dev/null 2>&1; then
	find "$ROOT_DIR/app" "$ROOT_DIR/bootstrap" "$ROOT_DIR/config" "$ROOT_DIR/database" "$ROOT_DIR/routes" "$ROOT_DIR/public" \
		-type f -name '*.php' -print0 | xargs -0 -n1 php -l >/dev/null
else
	echo "PHP is not installed locally; remote PHP preflight remains mandatory."
fi

SSH_OPTS=(
	-i "$LIVE_SSH_KEY"
	-p "$LIVE_PORT"
	-o BatchMode=yes
	-o StrictHostKeyChecking=no
	-o UserKnownHostsFile=/dev/null
)

DEPLOY_ID="${GITHUB_SHA:-$(git -C "$ROOT_DIR" rev-parse --short HEAD 2>/dev/null || date +%s)}-$(date +%Y%m%d%H%M%S)"
REMOTE_RELEASE="${LIVE_PATH}.release-${DEPLOY_ID}"
REMOTE_BACKUP="${LIVE_PATH}.backup-${DEPLOY_ID}"

RSYNC_EXCLUDES=(
	--exclude='.git/'
	--exclude='.github/'
	--exclude='.env'
	--exclude='.env.*'
	--exclude='auth.json'
	--exclude='node_modules/'
	--exclude='vendor/'
	--exclude='public/build/'
	--exclude='public/hot'
	--exclude='public/storage/'
	--exclude='database/*.sqlite'
	--exclude='testing'
	--exclude='storage/app/'
	--exclude='storage/frontend/build.num'
	--exclude='storage/logs/'
	--exclude='storage/framework/down'
	--exclude='storage/framework/cache/'
	--exclude='storage/framework/sessions/'
	--exclude='storage/framework/views/'
	--exclude='bootstrap/cache/*.php'
	--exclude='deploy/backups/'
	--exclude='.DS_Store'
	--exclude='**/.DS_Store'
)

RSYNC_PERMISSIONS=(
	--no-owner
	--no-group
	--no-perms
	--no-times
	--delay-updates
	--chmod=Du=rwx,Dgo=rx,Fu=rw,Fgo=r
)

echo "Preparing remote release ${REMOTE_RELEASE}"
ssh "${SSH_OPTS[@]}" "${LIVE_USER}@${LIVE_HOST}" "set -e && \
	mkdir -p '$REMOTE_RELEASE' && \
	mkdir -p '$SHARED_STORAGE_PUBLIC' && \
	if [ -d '$LIVE_PATH/storage/app/public' ] && [ \"\$(readlink -f '$LIVE_PATH/storage/app/public' 2>/dev/null || true)\" != \"\$(readlink -f '$SHARED_STORAGE_PUBLIC' 2>/dev/null || true)\" ]; then \
		rsync -a '$LIVE_PATH/storage/app/public/' '$SHARED_STORAGE_PUBLIC/'; \
	fi && \
	chmod 755 '$REMOTE_RELEASE' && \
	test -f '$LIVE_PATH/.env' && \
	cp '$LIVE_PATH/.env' '$REMOTE_RELEASE/.env'"

echo "Syncing source to staged release..."
rsync -az --delete \
	"${RSYNC_PERMISSIONS[@]}" \
	-e "ssh ${SSH_OPTS[*]}" \
	"${RSYNC_EXCLUDES[@]}" \
	"$ROOT_DIR/" "${LIVE_USER}@${LIVE_HOST}:${REMOTE_RELEASE}/"

echo "Building staged release..."
ssh "${SSH_OPTS[@]}" "${LIVE_USER}@${LIVE_HOST}" "set -e && \
	cp '$LIVE_PATH/.env' '$REMOTE_RELEASE/.env' && \
	cd '$REMOTE_RELEASE' && \
	SHARED_STORAGE_PUBLIC_PATH='$SHARED_STORAGE_PUBLIC' bash deploy/live-deploy.sh && \
	php artisan about --only=environment --no-ansi >/dev/null"

echo "Promoting staged release to live..."
ssh "${SSH_OPTS[@]}" "${LIVE_USER}@${LIVE_HOST}" "bash -s" -- "$LIVE_PATH" "$REMOTE_RELEASE" "$REMOTE_BACKUP" "$LIVE_URL" "$SHARED_STORAGE_PUBLIC" <<'REMOTE'
set -euo pipefail

LIVE_PATH="$1"
REMOTE_RELEASE="$2"
REMOTE_BACKUP="$3"
LIVE_URL="$4"
SHARED_STORAGE_PUBLIC="$5"

exec 9>"${LIVE_PATH}.deploy.lock"
if ! flock -n 9; then
	echo "Another live deployment is already running. Stopping safely."
	exit 1
fi

rsync_excludes=(
	--exclude='.env'
	--exclude='.env.*'
	--exclude='auth.json'
	--exclude='public/hot'
	--exclude='public/storage/'
	--exclude='database/*.sqlite'
	--exclude='testing'
	--exclude='storage/app/'
	--exclude='storage/logs/'
	--exclude='storage/framework/down'
	--exclude='storage/framework/cache/'
	--exclude='storage/framework/sessions/'
	--exclude='storage/framework/views/'
	--exclude='bootstrap/cache/*.php'
	--exclude='deploy/backups/'
	--exclude='.DS_Store'
	--exclude='**/.DS_Store'
)

rsync_permissions=(
	--no-owner
	--no-group
	--no-perms
	--no-times
	--delay-updates
	--chmod=Du=rwx,Dgo=rx,Fu=rw,Fgo=r
)

deployment_down=0

count_user_media_files() {
	local media_root="$1"

	if [ ! -d "$media_root" ]; then
		echo 0
		return
	fi

	find "$media_root/uploads/users/avatars" "$media_root/uploads/users/covers" -type f 2>/dev/null | wc -l | tr -d ' '
}

sync_live_public_storage_to_shared() {
	mkdir -p "$(dirname "$SHARED_STORAGE_PUBLIC")" "$SHARED_STORAGE_PUBLIC"

	if [ -d "$LIVE_PATH/storage/app/public" ] && [ "$(readlink -f "$LIVE_PATH/storage/app/public" 2>/dev/null || true)" != "$(readlink -f "$SHARED_STORAGE_PUBLIC" 2>/dev/null || true)" ]; then
		echo "Preserving live public uploads in shared media storage..."
		rsync -a "$LIVE_PATH/storage/app/public/" "$SHARED_STORAGE_PUBLIC/"
	fi
}

attach_shared_public_storage() {
	local app_path="$1"

	mkdir -p "$app_path/storage/app" "$SHARED_STORAGE_PUBLIC"
	rm -rf "$app_path/storage/app/public"
	ln -s "$SHARED_STORAGE_PUBLIC" "$app_path/storage/app/public"
}

assert_shared_media_ready() {
	local expected_min="$1"
	local actual_count

	actual_count="$(count_user_media_files "$SHARED_STORAGE_PUBLIC")"

	if [ "$expected_min" -gt 0 ] && [ "$actual_count" -lt "$expected_min" ]; then
		echo "Live deployment refused: shared media storage has ${actual_count} user media files, expected at least ${expected_min}."
		exit 1
	fi
}

assert_deploy_parent_writable() {
	live_parent="$(dirname "$LIVE_PATH")"

	if [ ! -w "$live_parent" ]; then
		echo "Live deployment refused: deploy user cannot write to ${live_parent}."
		echo "Fix ownership of ${live_parent} before deploying again."
		exit 1
	fi

	touch "$live_parent/.deploy-write-test"
	rm -f "$live_parent/.deploy-write-test"
}

put_live_down() {
	cd "$LIVE_PATH"
	php artisan down --retry=30 --no-ansi
	deployment_down=1
}

put_live_up() {
	if [ "$deployment_down" = "1" ]; then
		cd "$LIVE_PATH"
		php artisan up --no-ansi || true
		deployment_down=0
	fi
}

restore_live() {
	status=$?
	set +e

	echo "Promotion failed. Restoring previous live copy..."

	if [ -d "$REMOTE_BACKUP" ]; then
		rm -rf "$LIVE_PATH"
		mv "$REMOTE_BACKUP" "$LIVE_PATH"
		attach_shared_public_storage "$LIVE_PATH"
		cd "$LIVE_PATH"
		SHARED_STORAGE_PUBLIC_PATH="$SHARED_STORAGE_PUBLIC" INSTALL_DEPS=0 BUILD_ASSETS=0 RUN_MIGRATIONS=0 bash deploy/live-deploy.sh || true
	fi

	put_live_up

	exit "$status"
}

trap restore_live ERR

test -d "$LIVE_PATH"
test -d "$REMOTE_RELEASE"
assert_deploy_parent_writable
pre_media_count="$(count_user_media_files "$LIVE_PATH/storage/app/public")"
sync_live_public_storage_to_shared
assert_shared_media_ready "$pre_media_count"
attach_shared_public_storage "$REMOTE_RELEASE"

rm -rf "$REMOTE_BACKUP"

put_live_down
mv "$LIVE_PATH" "$REMOTE_BACKUP"
mv "$REMOTE_RELEASE" "$LIVE_PATH"

cd "$LIVE_PATH"
attach_shared_public_storage "$LIVE_PATH"
SHARED_STORAGE_PUBLIC_PATH="$SHARED_STORAGE_PUBLIC" MEDIA_GUARD_MIN_USER_FILES="$pre_media_count" INSTALL_DEPS=0 BUILD_ASSETS=0 RUN_MIGRATIONS=0 bash deploy/live-deploy.sh
put_live_up
curl -fsSL --max-time 20 "$LIVE_URL/" -o /dev/null
curl -fsSL --max-time 20 "$LIVE_URL/admin/login" -o /dev/null
curl -fsSL --max-time 20 "$LIVE_URL/auth/login" -o /dev/null
curl -fsSL --max-time 20 "$LIVE_URL/auth/signup" -o /dev/null

trap - ERR

rm -rf "$REMOTE_RELEASE"
find "$(dirname "$LIVE_PATH")" -maxdepth 1 -type d -name "$(basename "$LIVE_PATH").backup-*" | sort | head -n -3 | xargs -r rm -rf
REMOTE

echo "Checking live site..."
curl -fsSL --max-time 20 "$LIVE_URL/" -o /dev/null
curl -fsSL --max-time 20 "$LIVE_URL/admin/login" -o /dev/null
curl -fsSL --max-time 20 "$LIVE_URL/auth/login" -o /dev/null
curl -fsSL --max-time 20 "$LIVE_URL/auth/signup" -o /dev/null

echo "Live deploy OK: $LIVE_URL"
