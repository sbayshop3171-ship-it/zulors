#!/usr/bin/env bash
set -euo pipefail

ANDROID_ADB="$(command -v adb 2>/dev/null || true)"
if [ -z "$ANDROID_ADB" ]; then
	ANDROID_ADB="${ANDROID_HOME:-$HOME/Library/Android/sdk}/platform-tools/adb"
fi

if [ ! -x "$ANDROID_ADB" ]; then
	echo "adb not found. Install Android platform tools first."
	exit 1
fi

connected_devices="$("$ANDROID_ADB" devices | awk 'NR > 1 && $2 == "device" { print $1 }')"
if [ -z "$connected_devices" ]; then
	echo "No authorized Android device is connected. Connect it over USB and accept the debugging prompt."
	exit 1
fi

while IFS= read -r device_serial; do
	[ -n "$device_serial" ] || continue
	"$ANDROID_ADB" -s "$device_serial" reverse tcp:8001 tcp:8001
	"$ANDROID_ADB" -s "$device_serial" reverse tcp:5173 tcp:5173
done <<EOF
$connected_devices
EOF

echo "USB reverse ready. Open http://127.0.0.1:8001/explore/posts on the Android device."
