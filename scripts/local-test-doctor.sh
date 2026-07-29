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

if command -v ipconfig >/dev/null 2>&1; then
	lan_ip="$(ipconfig getifaddr en0 2>/dev/null || true)"
	if [ -n "$lan_ip" ]; then
		echo "Phone Wi-Fi URL will be: http://${lan_ip}:8000"
	fi
fi

if command -v adb >/dev/null 2>&1; then
	echo "OK: adb found"
	adb devices || true
else
	echo "INFO: adb not found. USB reverse testing needs Android platform tools."
fi

if lsof -nP -iTCP:8000 -sTCP:LISTEN >/dev/null 2>&1; then
	echo "INFO: Port 8000 is already in use:"
	lsof -nP -iTCP:8000 -sTCP:LISTEN
else
	echo "OK: Port 8000 is free"
fi
