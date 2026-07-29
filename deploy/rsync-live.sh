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

echo "Syncing source to ${LIVE_USER}@${LIVE_HOST}:${LIVE_PATH}"
rsync -az --delete \
	-e "ssh ${SSH_OPTS[*]}" \
	--exclude='.git/' \
	--exclude='.github/' \
	--exclude='.env' \
	--exclude='.env.*' \
	--exclude='auth.json' \
	--exclude='node_modules/' \
	--exclude='vendor/' \
	--exclude='public/build/' \
	--exclude='public/hot' \
	--exclude='public/storage/' \
	--exclude='database/*.sqlite' \
	--exclude='testing' \
	--exclude='storage/app/' \
	--exclude='storage/frontend/build.num' \
	--exclude='storage/logs/' \
	--exclude='storage/framework/cache/' \
	--exclude='storage/framework/sessions/' \
	--exclude='storage/framework/views/' \
	--exclude='bootstrap/cache/*.php' \
	--exclude='.DS_Store' \
	--exclude='**/.DS_Store' \
	"$ROOT_DIR/" "${LIVE_USER}@${LIVE_HOST}:${LIVE_PATH}/"

echo "Running remote deploy script..."
ssh "${SSH_OPTS[@]}" "${LIVE_USER}@${LIVE_HOST}" "cd '$LIVE_PATH' && bash deploy/live-deploy.sh"

echo "Normalizing remote permissions..."
ssh "${SSH_OPTS[@]}" "${LIVE_USER}@${LIVE_HOST}" "cd '$LIVE_PATH' && \
	chmod 755 . && \
	find public bootstrap config database deploy lang resources routes scripts services var -type d -exec chmod 755 {} + 2>/dev/null || true && \
	find public bootstrap config database deploy lang resources routes scripts services var -type f -exec chmod 644 {} + 2>/dev/null || true && \
	chmod 775 bootstrap/cache storage storage/app storage/app/public storage/framework storage/framework/cache storage/framework/sessions storage/framework/views storage/logs 2>/dev/null || true"

echo "Checking live site..."
curl -fsSI https://zulors.com/admin/login >/dev/null
curl -fsSI https://zulors.com/auth/signup >/dev/null

echo "Live deploy OK: https://zulors.com"
