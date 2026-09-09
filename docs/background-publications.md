# Background Publications

## Scope

Background publications let a user select media, press Publish or Send, leave the
editor, and watch progress from Home, Chat, or the native Android notification. The
server does not expose the post, story, or message until every attachment is uploaded,
processed, and attached in the final publish transaction.

This release is additive and flag gated. It uses the existing R2 temp/final buckets,
Redis/Horizon queues, and FFmpeg workers. It does not add iOS native background upload,
new paid infrastructure, Cloudflare Stream, audio/document upload changes, or a
separate GIF workflow.

As of the 2026-09-09 live deployment, the code, migration, routes and scheduler entry
are deployed to https://zulors.com, but `MEDIA_PUBLICATIONS_ENABLED=false` keeps new
admission off until production canary and Samsung ADB acceptance are complete.

## Feature Flags

```dotenv
MEDIA_PUBLICATIONS_ENABLED=false
MEDIA_PUBLICATIONS_ALLOWED_USER_IDS=
MEDIA_PUBLICATIONS_PER_USER_LIMIT=2
MEDIA_PUBLICATIONS_VIDEO_LIMIT=20
MEDIA_PUBLICATIONS_MAX_QUEUE_AGE_SECONDS=900
MEDIA_PUBLICATIONS_IMAGE_MAX_BYTES=20971520
MEDIA_PUBLICATIONS_TIMEOUT=21600
```

`MEDIA_PUBLICATIONS_ENABLED=false` keeps new background-publication admission off while
the code is deployed. Already admitted publications can still resume, complete, cancel
and reconcile during a rollback. Use `MEDIA_PUBLICATIONS_ALLOWED_USER_IDS` for the
first production canary accounts.

The current canary API only accepts `privacy=all`. Existing restricted post/story
flows remain on the legacy routes until a larger privacy enforcement change is made.

## API

Authenticated user routes are under `/api/media-publications`:

- `GET /capabilities`
- `POST /`
- `GET /`
- `GET /{publication}`
- `POST /{publication}/items/{item}/resume`
- `POST /{publication}/items/{item}/complete`
- `POST /{publication}/retry`
- `DELETE /{publication}`

Create requires a client-generated `client_uid`. Repeating the same request returns
the same publication. Reusing the same `client_uid` with different content returns
409. Attachments also carry client IDs so Android and browser queues can reconcile
the same work without duplicate posts.

Publication status moves through `uploading`, `processing`, `publishing`, and
`published`, with `waiting`, `failed`, and `cancelled` for non-happy paths. Raw
uploads remain private in `r2_temp`. Completed public media is written to `r2_final`
only by server-side processing.

## Android

The Android WebView bridge accepts messages only from the trusted HTTPS main frame and
stores selected files in app-private storage before the editor leaves. Large files are
not sent through the JavaScript bridge as base64.

Android 14+ uses a user-initiated data transfer `JobService`. Older supported Android
versions use foreground `WorkManager`. One transfer runs per device, with at most two
multipart parts in flight. Upload progress updates one ongoing notification at most
once per second. If the user force-stops the app or powers off the device, active
transfer continuity is not guaranteed; completed server-side processing continues.

Production APKs must be built with the existing signing identity. A debug APK can
verify compilation and local behavior, but it may not update an installed production
build if the signatures differ.

## Browser

The web path uses an IndexedDB-backed upload manager and a tab lease so multiple tabs
do not upload the same publication at the same time. Navigation inside the site keeps
uploads alive. Closing the browser is not guaranteed to keep uploading; on reopen the
manager reconciles and resumes when the File/Blob reference is still available. If the
browser cannot persist the selected file, the user must reselect it before retry.

Home shows owner-only progress rows. Chat shows sender-only pending bubbles. Upload
percentage and server processing status are separate states.

## Processing Profile

New publications store `balanced_v1` at creation time, so future tuning does not change
already accepted work.

Images are auto-oriented, stripped of unnecessary metadata, converted to WebP quality
84, and capped to a 2048px long edge for Post and Chat. Story images fit into the
existing vertical story presentation. GIF and animated PNG/WebP are intentionally
rejected from this new path.

Videos are H.264 MP4 with AAC audio up to 128 kbps, CRF 24, `veryfast`, max 30 fps, and
a WebP poster. Post/Story video fits within 1080x1920 without upscaling. Chat keeps the
existing 720px square policy.

Compression is server-side. Original and optimized byte counts are recorded, but no
specific final MB size is guaranteed for every file.

## Rollout Checks

1. Deploy with `MEDIA_PUBLICATIONS_ENABLED=false`.
2. Confirm migrations, Horizon, scheduler, CORS, lifecycle, and existing legacy uploads.
3. Enable `MEDIA_PUBLICATIONS_ENABLED=true` for one test account via
   `MEDIA_PUBLICATIONS_ALLOWED_USER_IDS`.
4. Test Post image/video first, then Story and Chat, using Wi-Fi and cellular.
5. Test minimize, screen lock, app reopen, retry, cancel, auth expiry, and network
   interruption on a real Android device.
6. Run one representative 500 MB+ browser/app video and at most two simultaneous uploads.
7. Watch oldest queued video age, failure rate, web latency, FFmpeg child RSS, CPU, and
   scratch disk. Stop rollout if queue age approaches 15 minutes or web p95 latency is
   double baseline.

Rollback is config-first: set `MEDIA_PUBLICATIONS_ENABLED=false`, clear Laravel config
cache, and gracefully restart Horizon. Existing admitted rows stay recoverable or
cancellable through the status/resume/cancel endpoints.
