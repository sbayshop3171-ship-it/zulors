#!/usr/bin/env bash
set -euo pipefail

cd "$(dirname "$0")/.."

REPOSITORY="sbayshop3171-ship-it/zulors"
CURRENT_BRANCH="$(git branch --show-current)"

if [ "$CURRENT_BRANCH" != "main" ]; then
	echo "Live deployment can only be requested from the main branch."
	exit 1
fi

if [ -n "$(git status --porcelain --untracked-files=normal)" ]; then
	echo "Live deployment stopped: commit and push local changes first."
	exit 1
fi

git fetch origin main --quiet
LOCAL_MAIN_SHA="$(git rev-parse HEAD)"
REMOTE_MAIN_SHA="$(git rev-parse origin/main)"
if [ "$LOCAL_MAIN_SHA" != "$REMOTE_MAIN_SHA" ]; then
	echo "Live deployment stopped: local main does not match origin/main. Pull or push first."
	exit 1
fi

GH_BIN="${GH_BIN:-$(command -v gh || true)}"
if [ -z "$GH_BIN" ] && [ -x "$HOME/.local/bin/gh" ]; then
	GH_BIN="$HOME/.local/bin/gh"
fi
if [ -z "$GH_BIN" ] || [ ! -x "$GH_BIN" ]; then
	echo "GitHub CLI (gh) is required. Install it and sign in, then retry."
	exit 1
fi

if ! "$GH_BIN" auth status --hostname github.com >/dev/null 2>&1; then
	echo "Sign in to GitHub CLI before requesting a live deployment."
	exit 1
fi

echo "Requesting a checked live deployment of main commit $LOCAL_MAIN_SHA..."
DISPATCH_OUTPUT="$("$GH_BIN" workflow run zulors-ci.yml --repo "$REPOSITORY" --ref main --raw-field deploy_live=true)"
printf '%s\n' "$DISPATCH_OUTPUT"

RUN_ID="$(printf '%s\n' "$DISPATCH_OUTPUT" | sed -n 's#.*actions/runs/\([0-9][0-9]*\).*#\1#p' | tail -n 1)"
if [ -z "$RUN_ID" ]; then
	echo "Workflow dispatched, but its run ID was not returned. Check the Zulors CI workflow in GitHub Actions."
	exit 1
fi

echo "Waiting for GitHub Actions run $RUN_ID..."
"$GH_BIN" run watch "$RUN_ID" --repo "$REPOSITORY" --exit-status
