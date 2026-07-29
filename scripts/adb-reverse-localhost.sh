#!/usr/bin/env bash
set -euo pipefail

if ! command -v adb >/dev/null 2>&1; then
	echo "adb not found. Install Android platform tools first."
	exit 1
fi

adb devices
adb reverse tcp:8000 tcp:8000

echo "USB reverse ready. Open this on the Android device: http://127.0.0.1:8000"
