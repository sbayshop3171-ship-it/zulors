#!/usr/bin/env bash
set -euo pipefail

ROOT_DIR="$(cd "$(dirname "$0")/.." && pwd)"
MODE="${1:-logic}"
WRITE_MODE="${2:-}"
XDG_CONFIG_HOME="${XDG_CONFIG_HOME:-${TMPDIR:-/tmp}/zulors-pilot-xdg}"
PSYSH_HISTORY_FILE="${PSYSH_HISTORY_FILE:-${TMPDIR:-/tmp}/zulors-pilot-psysh-history}"

mkdir -p "$XDG_CONFIG_HOME"
touch "$PSYSH_HISTORY_FILE"

export XDG_CONFIG_HOME
export PSYSH_HISTORY_FILE

cd "$ROOT_DIR"

case "$MODE" in
  logic)
    USERS=25
    POSTS=10
    REACTION_MIN=10
    REACTION_MAX=25
    COMMENT_MIN=10
    COMMENT_MAX=25
    CAMPAIGN="pilot-test-engagement-logic-v1"
    ;;
  volume)
    USERS=0
    POSTS=10
    REACTION_MIN=200
    REACTION_MAX=1000
    COMMENT_MIN=10
    COMMENT_MAX=80
    CAMPAIGN="pilot-test-engagement-volume-v1"
    ;;
  *)
    echo "Unknown mode: $MODE"
    echo "Usage: bash scripts/run-test-engagement-pilot.sh [logic|volume] [--write]"
    exit 1
    ;;
esac

echo "== Current counts =="
php artisan tinker --execute="
\$activeTestUsers = \App\Models\User::query()
    ->active()
    ->whereRaw('LOWER(email) LIKE ?', ['%.test'])
    ->count();

\$activeTestPosts = \App\Models\Post::query()
    ->active()
    ->whereHas('user', fn (\$q) => \$q->whereRaw('LOWER(email) LIKE ?', ['%.test']))
    ->count();

\$allActivePosts = \App\Models\Post::query()
    ->active()
    ->count();

echo 'all_active_posts='.\$allActivePosts.PHP_EOL;
echo 'active_test_users='.\$activeTestUsers.PHP_EOL;
echo 'active_test_posts='.\$activeTestPosts.PHP_EOL;
"

echo
echo "== Pilot mode: $MODE =="
echo "campaign=$CAMPAIGN"
echo "users=$USERS"
echo "posts=$POSTS"
echo "reaction_range=${REACTION_MIN}-${REACTION_MAX}"
echo "comment_range=${COMMENT_MIN}-${COMMENT_MAX}"

CMD=(
  php artisan test-content:bulk-engage
  "--campaign=${CAMPAIGN}"
  "--users=${USERS}"
  "--posts=${POSTS}"
  "--reaction-min=${REACTION_MIN}"
  "--reaction-max=${REACTION_MAX}"
  "--comment-min=${COMMENT_MIN}"
  "--comment-max=${COMMENT_MAX}"
)

if [[ "$WRITE_MODE" == "--write" ]]; then
  echo
  echo "== Live write =="
  "${CMD[@]}" --confirm=FULL_TEST_ENGAGEMENTS
else
  echo
  echo "== Dry run =="
  "${CMD[@]}" --dry-run
fi
