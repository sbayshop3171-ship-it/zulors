#!/usr/bin/env bash
set -euo pipefail

# Upload the existing Play upload key and its passwords to GitHub Actions secrets.
# Secret values are streamed through stdin and are never printed or committed.
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
REPO_ROOT="$(cd "$SCRIPT_DIR/../.." && pwd)"
CONFIG_ROOT="$REPO_ROOT/LocalAndroidPreview"
ENV_FILE="$CONFIG_ROOT/.env.play-upload-key"
KEYSTORE="${RELEASE_KEYSTORE:-$CONFIG_ROOT/keystores/zulors-upload.jks}"

if ! command -v gh >/dev/null 2>&1; then
	echo "GitHub CLI (gh) is required. Install it and run 'gh auth login' first." >&2
	exit 1
fi

if ! gh auth status --hostname github.com >/dev/null 2>&1; then
	echo "GitHub CLI is not authenticated. Run 'gh auth login' first." >&2
	exit 1
fi

if [ ! -f "$ENV_FILE" ] || [ ! -f "$KEYSTORE" ]; then
	echo "The local signing env file or upload keystore is missing; no GitHub secrets were changed." >&2
	exit 1
fi

# This file is a local, shell-compatible file and is excluded from Git.
set -a
# shellcheck disable=SC1090
source "$ENV_FILE"
set +a

RELEASE_KEYSTORE_TYPE="${RELEASE_KEYSTORE_TYPE:-JKS}"
RELEASE_KEY_ALIAS="${RELEASE_KEY_ALIAS:-zulors-upload}"

for name in RELEASE_STORE_PASSWORD RELEASE_KEY_PASSWORD RELEASE_KEY_ALIAS RELEASE_KEYSTORE_TYPE; do
	if [ -z "${!name:-}" ]; then
		echo "Missing $name in $ENV_FILE; no GitHub secrets were changed." >&2
		exit 1
	fi
done

if ! command -v keytool >/dev/null 2>&1; then
	echo "Java keytool was not found; install a JDK 17 before configuring release secrets." >&2
	exit 1
fi

if ! command -v node >/dev/null 2>&1; then
	echo "Node.js is required to validate the Firebase config before changing GitHub secrets." >&2
	exit 1
fi

SIGNING_SHA1="$(keytool -list -v \
	-keystore "$KEYSTORE" \
	-storetype "$RELEASE_KEYSTORE_TYPE" \
	-alias "$RELEASE_KEY_ALIAS" \
	-storepass "$RELEASE_STORE_PASSWORD" \
	-keypass "$RELEASE_KEY_PASSWORD" 2>/dev/null | awk -F': ' '/SHA1:/ { print $2; exit }' | tr -d ':' | tr '[:upper:]' '[:lower:]')"
if [ -z "$SIGNING_SHA1" ]; then
	echo "Could not read the configured upload key; no GitHub secrets were changed." >&2
	exit 1
fi

node - "$CONFIG_ROOT/firebase/google-services.json" "$SIGNING_SHA1" <<'NODE'
const fs = require('fs');
const [file, expectedSha1] = process.argv.slice(2);
const config = JSON.parse(fs.readFileSync(file, 'utf8'));
const hasPackage = (config.client || []).some(client =>
  client.client_info?.android_client_info?.package_name === 'com.zulors.app'
);
const hashes = (config.client || []).flatMap(client => (client.oauth_client || [])
  .filter(oauth => oauth.client_type === 1)
  .map(oauth => oauth.android_info?.certificate_hash)
  .filter(Boolean)
  .map(value => value.replace(/:/g, '').toLowerCase())
);
if (!hasPackage || !hashes.includes(expectedSha1)) {
  console.error('Firebase config must include package com.zulors.app and the signing key SHA-1. No secrets were changed.');
  process.exit(1);
}
NODE

REPO_SLUG="$(cd "$REPO_ROOT" && gh repo view --json nameWithOwner --jq .nameWithOwner)"
if [ -z "$REPO_SLUG" ]; then
	echo "Could not identify the GitHub repository for this checkout." >&2
	exit 1
fi

echo "Saving the Zulors production signing credentials as Actions secrets for $REPO_SLUG..."
base64 < "$KEYSTORE" | tr -d '\r\n' | gh secret set ANDROID_UPLOAD_KEYSTORE_BASE64 --repo "$REPO_SLUG"
printf '%s' "$RELEASE_STORE_PASSWORD" | gh secret set RELEASE_STORE_PASSWORD --repo "$REPO_SLUG"
printf '%s' "$RELEASE_KEY_PASSWORD" | gh secret set RELEASE_KEY_PASSWORD --repo "$REPO_SLUG"
printf '%s' "$RELEASE_KEY_ALIAS" | gh secret set RELEASE_KEY_ALIAS --repo "$REPO_SLUG"
printf '%s' "$RELEASE_KEYSTORE_TYPE" | gh secret set RELEASE_KEYSTORE_TYPE --repo "$REPO_SLUG"

echo "Signing secrets saved. Values were not displayed. Verify the secret names in GitHub Settings > Secrets and variables > Actions."
