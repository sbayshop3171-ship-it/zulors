#!/usr/bin/env bash
set -euo pipefail

cd "$(dirname "$0")/.."

php artisan optimize:clear

if command -v ipconfig >/dev/null 2>&1; then
	lan_ip="$(ipconfig getifaddr en0 2>/dev/null || true)"
	if [ -n "$lan_ip" ]; then
		echo "Open on phone over same Wi-Fi: http://${lan_ip}:8000"
	fi
fi

echo "Open on this computer: http://127.0.0.1:8000"
php artisan serve --host=0.0.0.0 --port=8000
