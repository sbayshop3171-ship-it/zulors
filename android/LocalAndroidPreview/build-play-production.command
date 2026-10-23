#!/bin/zsh
set -euo pipefail

cd "$(dirname "$0")"

EXTERNAL_APP_DIR="${EXTERNAL_APP_DIR:-../../../LocalAndroidPreview}"
EXTERNAL_ENV="$EXTERNAL_APP_DIR/.env.play-upload-key"

if [ -f "$EXTERNAL_ENV" ]; then
  set -a
  source "$EXTERNAL_ENV"
  set +a
else
  printf "Missing release signing env file.\n" >&2
  printf "Expected the canonical config at:\n  %s\n" "$EXTERNAL_ENV" >&2
  printf "\nPress Enter to close..."
  read -r _
  exit 1
fi
export EXTERNAL_APP_DIR

DEFAULT_VERSION_CODE="${VERSION_CODE:-$(date +%s)}"
DEFAULT_VERSION_NAME="${VERSION_NAME:-1.0.9}"

if [ -t 0 ]; then
  printf "Play versionCode [%s]: " "$DEFAULT_VERSION_CODE"
  read -r VERSION_CODE_INPUT

  printf "Play versionName [%s]: " "$DEFAULT_VERSION_NAME"
  read -r VERSION_NAME_INPUT
else
  VERSION_CODE_INPUT=""
  VERSION_NAME_INPUT=""
fi

export VERSION_CODE="${VERSION_CODE_INPUT:-$DEFAULT_VERSION_CODE}"
export VERSION_NAME="${VERSION_NAME_INPUT:-$DEFAULT_VERSION_NAME}"
export APP_MODE=production
export BUILD_TYPE=release
export MIN_SDK="${MIN_SDK:-24}"
export ENABLE_PLAY_FLEXIBLE_UPDATES=true
export ENABLE_FIREBASE_MESSAGING=true
export CREATE_RELEASE_KEYSTORE=false
export GOOGLE_SERVICES_JSON="${GOOGLE_SERVICES_JSON:-$EXTERNAL_APP_DIR/firebase/google-services.json}"
export RELEASE_KEYSTORE="${RELEASE_KEYSTORE:-$EXTERNAL_APP_DIR/keystores/zulors-upload.jks}"

printf "\nBuilding Zulors production AAB for Play Console...\n"
ARTIFACT_TYPE=bundle RUN_UPLOAD_TESTS=true ./build-local.sh

printf "\nBuilding matching signed APK for device sanity testing...\n"
ARTIFACT_TYPE=apk RUN_UPLOAD_TESTS=false ./build-local.sh

printf "\nDone.\n"
printf "Play upload AAB:\n%s/build/latest/zulors-production-%s-%s.aab\n" "$(pwd)" "$VERSION_NAME" "$VERSION_CODE"
printf "Install-test APK:\n%s/build/latest/zulors-production-%s-%s.apk\n" "$(pwd)" "$VERSION_NAME" "$VERSION_CODE"

if command -v shasum >/dev/null 2>&1; then
  printf "\nSHA-256:\n"
  shasum -a 256 \
    "build/latest/zulors-production-${VERSION_NAME}-${VERSION_CODE}.aab" \
    "build/latest/zulors-production-${VERSION_NAME}-${VERSION_CODE}.apk"
fi

if [ -t 0 ]; then
  printf "\nPress Enter to close..."
  read -r _
fi
