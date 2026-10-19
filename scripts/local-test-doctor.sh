#!/usr/bin/env bash
set -euo pipefail

cd "$(dirname "$0")/.."

echo "Zulors local test doctor"
echo "========================"

check_command() {
	if command -v "$1" >/dev/null 2>&1; then
		echo "OK: $1 found"
	else
		echo "MISSING: $1"
	fi
}

check_command php
check_command composer
check_command npm
check_command sqlite3

ANDROID_ADB="$(command -v adb 2>/dev/null || true)"
if [ -z "$ANDROID_ADB" ]; then
	ANDROID_ADB="${ANDROID_HOME:-$HOME/Library/Android/sdk}/platform-tools/adb"
fi

if [ -f ".env" ]; then
	echo "OK: .env exists"
else
	echo "MISSING: .env"
fi

if [ -f "public/build/manifest.json" ]; then
	echo "OK: Vite build manifest exists"
else
	echo "MISSING: public/build/manifest.json"
fi

if [ -f "public/pwa/manifest.json" ] && grep -q '"name": "Zulors"' public/pwa/manifest.json; then
	echo "OK: PWA manifest is branded as Zulors"
else
	echo "CHECK: PWA manifest branding"
fi

php artisan app:version >/tmp/zulors-version.txt
echo "OK: Laravel boots, version $(cat /tmp/zulors-version.txt)"

php artisan db:test

echo "Local browser URL: http://127.0.0.1:8001/explore/posts"

if [ -x "$ANDROID_ADB" ]; then
	echo "OK: adb found"
	"$ANDROID_ADB" devices || true
else
	echo "INFO: adb not found. USB reverse testing needs Android platform tools."
fi

if lsof -nP -iTCP:8001 -sTCP:LISTEN >/dev/null 2>&1; then
	echo "INFO: Laravel port 8001 is already in use:"
	lsof -nP -iTCP:8001 -sTCP:LISTEN
else
	echo "OK: Laravel port 8001 is free"
fi

if lsof -nP -iTCP:5173 -sTCP:LISTEN >/dev/null 2>&1; then
	echo "INFO: Vite port 5173 is already in use:"
	lsof -nP -iTCP:5173 -sTCP:LISTEN
else
	echo "OK: Vite port 5173 is free"
fi
