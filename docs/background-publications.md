# Background Publications

> Current source note (2026-10-30): historical rollout notes below describe the
> earlier VPS FFmpeg implementation. The current source sends new videos
> unchanged to R2 and publishes after R2 verification; it does not run a VPS or
> device video encoder. See [upload-flow-audit.md](upload-flow-audit.md) for the
> current flow. A cloud transcoder has not been selected or configured.

## Scope

Background publications let a user select media, press Publish or Send, leave the
editor, and watch progress from Home or Chat. The server creates the post, story or
message after R2 verifies each uploaded object and stores its R2 key. A separate
video transcode step is not part of the current path.

The browser publication outbox uses the existing R2 temp/final buckets and shared
Redis/cache for idempotency. New uploads do not dispatch video FFmpeg jobs. It does
not add iOS native background upload, new paid infrastructure, Cloudflare Stream,
audio/document upload changes, or a separate GIF workflow.

As of the 2026-09-09 live deployment and follow-up activation, the code, migration,
routes and scheduler entry are deployed to https://zulors.com, and
`MEDIA_PUBLICATIONS_ENABLED=true` admits the browser/WebView-compatible background
publication path on the live site. Direct-only enforcement remains off for older
clients. Native Android notification/background transfer is not accepted until the
0.5.0 APK is installed and tested on a real Samsung device.

## Feature Flags

```dotenv
MEDIA_PUBLICATIONS_ENABLED=true
MEDIA_PUBLICATIONS_ALLOWED_USER_IDS=
MEDIA_PUBLICATIONS_PER_USER_LIMIT=2
MEDIA_PUBLICATIONS_VIDEO_LIMIT=20
MEDIA_PUBLICATIONS_MAX_QUEUE_AGE_SECONDS=900
MEDIA_PUBLICATIONS_IMAGE_MAX_BYTES=20971520
MEDIA_PUBLICATIONS_TIMEOUT=21600
```

`MEDIA_PUBLICATIONS_ENABLED=true` accepts new background-publication admissions.
Setting it back to `false` is the first rollback step; already admitted publications
can still resume, complete, cancel and reconcile during rollback. Use
`MEDIA_PUBLICATIONS_ALLOWED_USER_IDS` to narrow admission to canary accounts instead
of leaving it global.

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
uploads remain private in `r2_temp`; after verification, R2 promotes each object to
`r2_final` with a storage-side copy. No media body is sent through Laravel.

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

Eligible images are auto-oriented, stripped of unnecessary metadata, converted to
WebP quality 86, and capped to a 2048px long edge in a browser worker when that
reduces size. Otherwise, the original image is retained. GIF and animated PNG/WebP
are intentionally rejected from this path.

Videos and documents are unchanged. Video is stored and served from R2 as provided;
there is no H.264/AAC transcode or thumbnail generation in the current pipeline.
Playback depends on the original codec. R2 does not compress media. A background
cloud encoder that reads and writes R2 would incur compute charges and needs a
separate deployment and measurement before it can be added.

Compression is server-side. Original and optimized byte counts are recorded, but no
specific final MB size is guaranteed for every file.

## Rollout Checks

1. Deploy with `MEDIA_PUBLICATIONS_ENABLED=false`.
2. Confirm migrations, Horizon, scheduler, CORS, lifecycle, and existing legacy uploads.
3. Enable `MEDIA_PUBLICATIONS_ENABLED=true` for one test account via
   `MEDIA_PUBLICATIONS_ALLOWED_USER_IDS`.
4. The live site was later opened globally by leaving
   `MEDIA_PUBLICATIONS_ALLOWED_USER_IDS` empty.
5. Test Post image/video first, then Story and Chat, using Wi-Fi and cellular.
6. Test minimize, screen lock, app reopen, retry, cancel, auth expiry, and network
   interruption on a real Android device.
7. Run one representative 500 MB+ browser/app video and at most two simultaneous uploads.
8. Watch upload failure/resume rate, R2 temp/final object counts, R2 stored bytes,
   rejected PHP upload bytes, VPS media-temp disk use and web latency. Stop rollout
   if duplicate publication or unexpected VPS media-body traffic appears.

Rollback is config-first: set `MEDIA_PUBLICATIONS_ENABLED=false`, clear Laravel config
cache, and gracefully restart Horizon. Existing admitted rows stay recoverable or
cancellable through the status/resume/cancel endpoints.
