#!/usr/bin/env bash
set -euo pipefail

ROOT_DIR="$(cd "$(dirname "$0")/.." && pwd)"
LIVE_HOST="${LIVE_HOST:-5.189.168.13}"
LIVE_USER="${LIVE_USER:-zulors}"
LIVE_PORT="${LIVE_PORT:-22}"
LIVE_PATH="${LIVE_PATH:-/var/www/zulors/data/www/zulors.com}"
LIVE_SSH_KEY="${LIVE_SSH_KEY:-$HOME/.ssh/zulors_live_deploy}"

if [ ! -f "$LIVE_SSH_KEY" ]; then
	echo "Missing SSH key: $LIVE_SSH_KEY"
	echo "Set LIVE_SSH_KEY or create the deploy key first."
	exit 1
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
	--exclude='storage/framework/cache/'
	--exclude='storage/framework/sessions/'
	--exclude='storage/framework/views/'
	--exclude='bootstrap/cache/*.php'
	--exclude='.DS_Store'
	--exclude='**/.DS_Store'
)

RSYNC_PERMISSIONS=(
	--no-perms
	--delay-updates
	--chmod=Du=rwx,Dgo=rx,Fu=rw,Fgo=r
)

echo "Preparing remote release ${REMOTE_RELEASE}"
ssh "${SSH_OPTS[@]}" "${LIVE_USER}@${LIVE_HOST}" "set -e && \
	mkdir -p '$REMOTE_RELEASE' && \
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
	bash deploy/live-deploy.sh && \
	php artisan about --only=environment --no-ansi >/dev/null"

echo "Promoting staged release to live..."
ssh "${SSH_OPTS[@]}" "${LIVE_USER}@${LIVE_HOST}" "bash -s" -- "$LIVE_PATH" "$REMOTE_RELEASE" "$REMOTE_BACKUP" <<'REMOTE'
set -euo pipefail

LIVE_PATH="$1"
REMOTE_RELEASE="$2"
REMOTE_BACKUP="$3"

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
	--exclude='storage/framework/cache/'
	--exclude='storage/framework/sessions/'
	--exclude='storage/framework/views/'
	--exclude='bootstrap/cache/*.php'
	--exclude='.DS_Store'
	--exclude='**/.DS_Store'
)

rsync_permissions=(
	--no-perms
	--delay-updates
	--chmod=Du=rwx,Dgo=rx,Fu=rw,Fgo=r
)

restore_live() {
	status=$?

	echo "Promotion failed. Restoring previous live copy..."

	if [ -d "$REMOTE_BACKUP" ]; then
		rsync -a --delete "${rsync_permissions[@]}" "${rsync_excludes[@]}" "$REMOTE_BACKUP/" "$LIVE_PATH/"
		cd "$LIVE_PATH"
		INSTALL_DEPS=0 BUILD_ASSETS=0 RUN_MIGRATIONS=0 bash deploy/live-deploy.sh || true
	fi

	exit "$status"
}

trap restore_live ERR

test -d "$LIVE_PATH"
test -d "$REMOTE_RELEASE"

cp -al "$LIVE_PATH" "$REMOTE_BACKUP"

rsync -a --delete "${rsync_permissions[@]}" "${rsync_excludes[@]}" "$REMOTE_RELEASE/" "$LIVE_PATH/"

cd "$LIVE_PATH"
INSTALL_DEPS=0 BUILD_ASSETS=0 RUN_MIGRATIONS=0 bash deploy/live-deploy.sh
curl -fsSI https://zulors.com/admin/login >/dev/null
curl -fsSI https://zulors.com/auth/signup >/dev/null

trap - ERR
REMOTE

echo "Checking live site..."
curl -fsSI https://zulors.com/admin/login >/dev/null
curl -fsSI https://zulors.com/auth/signup >/dev/null

echo "Live deploy OK: https://zulors.com"
