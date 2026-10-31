# Comment threads rollout

1. Deploy the nullable `comments.thread_root_id` and `comments.edited_at` migration.
2. The live deployment runs `php artisan comments:backfill-thread-roots --chunk=500` immediately after migrations. The command can be rerun safely and exits quickly once roots are complete. It batches normal threads and repairs malformed legacy parent links.
3. Set `MEDIA_PUBLICATIONS_KINDS=post,story,chat,comment` on web and worker processes, then deploy the thread API and clients. Keep `/post/{hashId}/comments` available while older clients are in use.
4. CI runs the full PHPUnit suite, JavaScript unit checks, asset build, migration and backfill smoke checks. The Android upload build has a separate local build script.
5. Watch comment media publication failures, pending publication age, reply API pagination, and `posts.comments_count` discrepancies.

Comment images use the media publication image byte and pixel limits. The finalizer rechecks the post, reply target, edit owner, and 15-minute edit window before it creates or edits a comment.
