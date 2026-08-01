# Test Content Engagement Pilot

This runbook is for the safe pilot of bulk reactions/comments using only active `.test` accounts and only active posts authored by `.test` accounts.

## Safety defaults

- Uses only active users whose email ends with `.test`
- Uses only active posts authored by `.test` users
- Does not call public APIs
- Does not use the normal controller notification flow
- Re-running the same campaign does not create duplicate comments or duplicate reactions from the same test account on the same post

## Two pilot types

### Logic pilot

Use this first when we only want to validate:

- `.test`-only account scoping
- `.test`-authored post scoping
- duplicate safety on rerun
- context-aware comment generation

Scope:

- Accounts: first `25` eligible active `.test` accounts
- Posts: first `10` active `.test`-authored posts after `after-id`
- Reactions per post: random deterministic range `10-25`
- Comments per post: random deterministic range `10-25`

Why this exists:

- a 25-account pilot cannot physically create `200-1000` unique reactions on the same post if each account may react only once per post

### Volume pilot

Use this only after the logic pilot is clean.

Scope:

- Accounts: all eligible active `.test` accounts, or at least `200`
- Posts: first `10` active `.test`-authored posts after `after-id`
- Reactions per post: random deterministic range `200-1000`
- Comments per post: random deterministic range `10-80`

## 1. Current counts

```bash
php artisan tinker --execute="
\$activeTestUsers = \App\Models\User::query()
    ->active()
    ->whereRaw('LOWER(email) LIKE ?', ['%.test'])
    ->count();

\$activeTestPosts = \App\Models\Post::query()
    ->active()
    ->whereHas('user', fn (\$q) => \$q->whereRaw('LOWER(email) LIKE ?', ['%.test']))
    ->count();

echo 'active_test_users='.\$activeTestUsers.PHP_EOL;
echo 'active_test_posts='.\$activeTestPosts.PHP_EOL;
"
```

## 2. Logic pilot dry run

```bash
php artisan test-content:bulk-engage \
  --campaign=pilot-test-engagement-logic-v1 \
  --users=25 \
  --posts=10 \
  --reaction-min=10 \
  --reaction-max=25 \
  --comment-min=10 \
  --comment-max=25 \
  --dry-run
```

Expected:

- `Eligible active .test accounts: ...`
- `Post scope: active .test-authored posts only`
- `Active posts in this batch: 10`
- Planned reaction/comment totals

Or use the helper script:

```bash
bash scripts/run-test-engagement-pilot.sh logic
```

## 3. Logic pilot write

```bash
php artisan test-content:bulk-engage \
  --campaign=pilot-test-engagement-logic-v1 \
  --users=25 \
  --posts=10 \
  --reaction-min=10 \
  --reaction-max=25 \
  --comment-min=10 \
  --comment-max=25 \
  --confirm=FULL_TEST_ENGAGEMENTS
```

Capture the final summary:

- `Posts completed`
- `Reactions added`
- `Comments added`
- `Already present`
- `Failed posts`

Or use the helper script:

```bash
bash scripts/run-test-engagement-pilot.sh logic --write
```

## 4. Logic pilot duplicate check

Run the exact same command a second time:

```bash
php artisan test-content:bulk-engage \
  --campaign=pilot-test-engagement-logic-v1 \
  --users=25 \
  --posts=10 \
  --reaction-min=10 \
  --reaction-max=25 \
  --comment-min=10 \
  --comment-max=25 \
  --confirm=FULL_TEST_ENGAGEMENTS
```

Expected on rerun:

- `Reactions added: 0`
- `Comments added: 0`
- non-zero `Already present`

That confirms the campaign is resume-safe and duplicate-safe.

## 5. Verify no non-test user was used

```bash
php artisan tinker --execute="
\$campaign = 'pilot-test-engagement-logic-v1';

\$nonTestCommentAuthors = \App\Models\TestContentEngagement::query()
    ->where('campaign_key', \$campaign)
    ->join('users', 'users.id', '=', 'test_content_engagements.user_id')
    ->whereRaw('LOWER(users.email) NOT LIKE ?', ['%.test'])
    ->count();

echo 'non_test_comment_authors='.\$nonTestCommentAuthors.PHP_EOL;
"
```

Expected:

- `non_test_comment_authors=0`

## 6. Verify no non-test-authored post was touched

```bash
php artisan tinker --execute="
\$campaign = 'pilot-test-engagement-logic-v1';

\$nonTestAuthoredPostsTouched = \App\Models\TestContentEngagement::query()
    ->where('campaign_key', \$campaign)
    ->join('posts', 'posts.id', '=', 'test_content_engagements.post_id')
    ->join('users as post_authors', 'post_authors.id', '=', 'posts.user_id')
    ->whereRaw('LOWER(post_authors.email) NOT LIKE ?', ['%.test'])
    ->distinct('posts.id')
    ->count('posts.id');

echo 'non_test_authored_posts_touched='.\$nonTestAuthoredPostsTouched.PHP_EOL;
"
```

Expected:

- `non_test_authored_posts_touched=0`

## 7. Verify pilot comment totals

```bash
php artisan tinker --execute="
\$campaign = 'pilot-test-engagement-logic-v1';

\$commentRows = \App\Models\TestContentEngagement::query()
    ->where('campaign_key', \$campaign)
    ->count();

\$distinctUsers = \App\Models\TestContentEngagement::query()
    ->where('campaign_key', \$campaign)
    ->distinct('user_id')
    ->count('user_id');

\$distinctPosts = \App\Models\TestContentEngagement::query()
    ->where('campaign_key', \$campaign)
    ->distinct('post_id')
    ->count('post_id');

echo 'comment_rows='.\$commentRows.PHP_EOL;
echo 'distinct_users='.\$distinctUsers.PHP_EOL;
echo 'distinct_posts='.\$distinctPosts.PHP_EOL;
"
```

## 8. Volume pilot

Only run this after the logic pilot is clean and only when at least `200` active `.test` accounts are available.

```bash
bash scripts/run-test-engagement-pilot.sh volume
```

Then, if the dry run looks correct:

```bash
bash scripts/run-test-engagement-pilot.sh volume --write
```

## Success criteria

- Pilot completes with `Failed posts: 0`
- Rerun creates `0` new comments and `0` new reactions
- `non_test_comment_authors=0`
- `non_test_authored_posts_touched=0`
- Output totals match the command summary for the campaign

## Full run only after approval

Do not start the full run until the pilot output is reviewed and approved.
