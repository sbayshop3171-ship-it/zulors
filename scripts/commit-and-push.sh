#!/usr/bin/env bash
set -euo pipefail

cd "$(dirname "$0")/.."

MESSAGE="${1:-Update Zulors $(date '+%Y-%m-%d %H:%M:%S')}"

if ! git rev-parse --is-inside-work-tree >/dev/null 2>&1; then
	echo "This folder is not a Git repository."
	exit 1
fi

CURRENT_BRANCH="$(git branch --show-current)"
if [ "$CURRENT_BRANCH" != "main" ]; then
	echo "Switch to the main branch before committing and pushing."
	exit 1
fi

git add -A

BLOCKED_FILES="$(git diff --cached --name-only | awk '
{
	path = $0
	base = path
	sub(/^.*\//, "", base)
	if (base == ".env" || (base ~ /^\.env\./ && base != ".env.example") ||
		base == "auth.json" || path == "database/database.sqlite" || path == "testing" ||
		base == "google-services.json" || path ~ /\.(sqlite|sql|key|pem|p12|pfx|jks|keystore)$/) {
		print path
	}
}')"

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

echo "Push complete. GitHub Actions will run CI only; live deployment remains manual."
echo "When you are ready to publish the latest main commit, run: bash scripts/deploy-live.sh"
