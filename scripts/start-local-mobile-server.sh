#!/usr/bin/env bash
set -euo pipefail

cd "$(dirname "$0")/.."

php artisan optimize:clear

vite_pid=""
cleanup() {
	if [ -n "$vite_pid" ] && kill -0 "$vite_pid" 2>/dev/null; then
		kill "$vite_pid" 2>/dev/null || true
		wait "$vite_pid" 2>/dev/null || true
	fi
}
trap cleanup EXIT
trap 'exit 130' INT
trap 'exit 143' TERM

echo "Starting Vite with live source updates at http://127.0.0.1:5173"
npm run dev -- --host 127.0.0.1 &
vite_pid=$!

ANDROID_ADB="${ANDROID_HOME:-$HOME/Library/Android/sdk}/platform-tools/adb"
if [ -x "$ANDROID_ADB" ]; then
	connected_devices="$("$ANDROID_ADB" devices | awk 'NR > 1 && $2 == "device" { print $1 }')"
	if [ -n "$connected_devices" ]; then
		while IFS= read -r device_serial; do
			[ -n "$device_serial" ] || continue
			"$ANDROID_ADB" -s "$device_serial" reverse tcp:8001 tcp:8001
			"$ANDROID_ADB" -s "$device_serial" reverse tcp:5173 tcp:5173
		done <<EOF
$connected_devices
EOF
		echo "Forwarded Laravel and Vite to the connected Android device(s)."
	else
		echo "For Android WebView preview, connect a device before starting this server; ports 8001 and 5173 will be forwarded."
	fi
fi

echo "Starting Laravel at http://127.0.0.1:8001"
echo "Open http://127.0.0.1:8001/explore/posts in your browser (sign in if prompted)."
php artisan serve --host=127.0.0.1 --port=8001
