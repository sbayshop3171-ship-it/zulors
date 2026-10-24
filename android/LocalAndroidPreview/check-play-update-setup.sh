#!/usr/bin/env bash
set -euo pipefail

APP_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
MAIN_ACTIVITY="$APP_DIR/src/com/zulors/localpreview/MainActivity.java"
BUILD_SCRIPT="$APP_DIR/build-local.sh"

require_marker() {
	local file="$1"
	local marker="$2"
	local description="$3"

	if ! grep -Fq -- "$marker" "$file"; then
		printf 'Play update preflight failed: %s is missing from %s.\n' "$description" "$file" >&2
		exit 1
	fi
}

if [ "${ENABLE_PLAY_FLEXIBLE_UPDATES:-false}" != 'true' ]; then
	printf 'Play update preflight failed: ENABLE_PLAY_FLEXIBLE_UPDATES must be true.\n' >&2
	exit 1
fi

require_marker "$MAIN_ACTIVITY" 'AppUpdateManagerFactory.create(this)' 'Play update manager setup'
require_marker "$MAIN_ACTIVITY" 'appUpdateManager.getAppUpdateInfo()' 'available update checks'
require_marker "$MAIN_ACTIVITY" 'AppUpdateType.FLEXIBLE' 'flexible update flow'
require_marker "$MAIN_ACTIVITY" 'startUpdateFlowForResult(' 'user-visible update prompt'
require_marker "$MAIN_ACTIVITY" 'appUpdateManager.completeUpdate()' 'downloaded update installation'
require_marker "$BUILD_SCRIPT" "implementation 'com.google.android.play:app-update:\$PLAY_APP_UPDATE_VERSION'" 'Play update dependency'
require_marker "$BUILD_SCRIPT" "buildConfigField 'boolean', 'ENABLE_PLAY_FLEXIBLE_UPDATES'" 'update feature build flag'

printf 'Play flexible in-app updates: configured and enabled for this production build.\n'
