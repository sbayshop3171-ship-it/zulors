#!/usr/bin/env bash
set -euo pipefail

cd "$(dirname "$0")/.."

MESSAGE="${1:-Update Zulors $(date '+%Y-%m-%d %H:%M:%S')}"

if ! git rev-parse --is-inside-work-tree >/dev/null 2>&1; then
	echo "This folder is not a Git repository."
	exit 1
fi

git add -A

BLOCKED_FILES="$(git diff --cached --name-only | grep -E '(^|/)(\\.env|auth\\.json|database/database\\.sqlite|testing)$|\\.sqlite$|\\.sql$|\\.key$' || true)"

if [ -n "$BLOCKED_FILES" ]; then
	echo "Blocked sensitive/runtime files from being committed:"
	echo "$BLOCKED_FILES"
	exit 1
fi

if git diff --cached --quiet; then
	echo "No local changes to commit."
else
	echo "Committing changes..."
	git commit -m "$MESSAGE"
fi

echo "Pushing to GitHub..."
git push origin main

if [ "${SKIP_DEPLOY:-0}" = "1" ]; then
	echo "SKIP_DEPLOY=1, live deployment skipped."
	exit 0
fi

echo "Deploying to live server..."
bash deploy/rsync-live.sh
