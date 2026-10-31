# User Media Upload Audit

Audit date: 2026-10-30. This records the repository flow before the current
direct-upload hardening work. Production was not contacted.

## Existing routes and storage paths

| Category | Entry point | Current bytes path | Notes |
| --- | --- | --- | --- |
| Post/reel images | `routes/api/user/post_editor.php` → `PostImageController::uploadImage` | Browser multipart → Laravel/PHP upload temp → `ImageUploadService`/GD → configured storage disk | Image conversion and disk write run on the app server. Reel publishing shares post/video media. |
| Post/reel video | `media/video/direct/create` → `DirectMediaUploadController` | Supported clients PUT/multipart to signed R2 or Cloudflare Stream; completion verifies R2; queued post-video job downloads the R2 original to VPS scratch and runs FFmpeg, then uploads the result | `MEDIA_VIDEO_MAX_DURATION_SECONDS` defaults to 600. The raw and multipart proxy endpoints still exist. Proxy budgets depend on `direct_only`; deployment notes say that flag is off in production. |
| Legacy post video | `media/video/upload` → `PostVideoController` | Multipart through Laravel and the app upload service when direct-only is disabled | Direct-only mode returns 409, but the endpoint remains a server upload path when that flag is false. |
| Story image/video | `media/upload` → `StoryMediaController::uploadMedia` | Image multipart goes through Laravel, GD and storage. Video is rejected when direct-only is enabled; the dedicated direct-video endpoints PUT/multipart to R2 | Story video processing later uses the VPS story-video job/FFmpeg. |
| Chat image/audio/document | `chats/{id}/send` and `/audio/{id}/upload` | Multipart through Laravel, then `WithMediaUpload`/audio processing jobs and configured storage | Voice notes also use a two-step API, but the second step sends the full audio body through Laravel. |
| Chat video | `media/video/direct/*` APIs for video; `/send` remains the generic attachment endpoint | Supported video path uploads to R2, then the chat-video processing job can download and encode on VPS. Generic legacy video is server-uploaded when direct-only is off. | The Android upload queue is a local preview subsystem and is not proof that the production WebView path uses native device encoding. |
| Profile avatar/cover and onboarding avatar | `account/avatar/update`, `account/cover/update`, `avatar/update` | Multipart through Laravel; image crop/compression and storage happen in PHP/GD | Existing media URL fields remain paths on the configured static disk. |
| Group avatar | `groups/avatar/update` | Multipart through Laravel; image crop/compression and storage happen in PHP/GD | Authenticated group update action. |
| Ads/business | `routes/api/ads/ad.php` contains ad read/engagement endpoints, not an advertiser media-upload route. | No user ad/business-media upload receiver was found in the API routes/controllers audited. | Admin/import workflows are separate from user-generated upload flows. |
| Post document/audio | `media/document/upload`, `media/audio/upload` | Multipart through Laravel and the respective upload services | Documents are stored without media compression; audio may be converted by a queued job. |

Other relevant code: `R2DirectUploadService` signs single PUT and multipart URLs,
but also exposes raw/part application-server proxy methods. The app can retry
small direct-upload failures through those endpoints when fallback budgets are
nonzero. `PublicationMediaProcessor` downloads direct R2 publication objects to
`storage/app/tmp/publications`, uses GD/FFmpeg and writes optimized outputs.
Temporary-bucket lifecycle cleanup exists, but it is not a substitute for
persisting a completed original into the final bucket.

## Architecture gap

The selected R2-only path prepares eligible images on-device, sends videos and
documents unchanged through short-lived, object-scoped R2 single-PUT or
multipart sessions, and has the API check owner, operation, declared and actual
size, MIME policy, quota and R2 object metadata before it stores the R2 key and
completes the post/story/chat/profile association. The app server does not
receive media bodies, create media temp files or run GD/FFmpeg on user media.
Multipart retries resume the same object and idempotency key; incomplete
uploads and abandoned temp objects expire.

## Implemented architecture

| Category | Current client/API path | Server behavior |
| --- | --- | --- |
| Post/reel image, video, audio and document | Publication editor/outbox → `/api/media-publications` create/resume/complete | Signed R2 PUT/multipart; verify and server-side promote. |
| Story image and video | Story editor/outbox → `/api/media-publications` with `kind=story` | Same direct R2 path; no story FFmpeg worker. |
| Chat image, video, audio and document | Chat store/editor → `/api/media-publications` with `kind=chat` | Same direct R2 path; old chat `/send` multipart and `/audio/{id}/upload` are blocked. |
| Profile avatar, cover and onboarding avatar | Profile/onboarding UI → `/api/media-uploads/create` and `/complete` | Signed R2 single or multipart upload, verified and associated with the user. |
| Group avatar | Group editor → `/api/media-uploads/create` and `/complete` with group ID | Participant authorization, signed R2 upload, verified and associated with group. |
| Advertisement/business media | No user advertiser upload API was found | No user upload receiver to migrate in this repository. |
| GIFs | Existing provider/embed flow; GIF is excluded from file upload selection | Not uploaded through this media pipeline. |

The web flow prepares eligible images with `createImageBitmap` and
`OffscreenCanvas`; it applies orientation, preserves transparency, removes
private metadata and keeps the original if WebP would not save at least 1 KiB.
Video files are not re-encoded on the device: the original goes to R2 so the
phone does not spend time, battery or RAM on video processing. PDF, Office and
other documents remain byte-for-byte unchanged.

The publication outbox stores upload state locally, prepares before sending,
and uploads with signed R2 PUT or multipart URLs. Parts are retried and their
ETags are persisted for resume. The API accepts metadata and completion calls,
checks authorization, declared/actual size and stored content type, then
promotes objects using R2 server-side copy. The publication completes
synchronously after this metadata-only promotion; it does not dispatch a VPS
media-processing job. A publication UUID and per-item generation make retry
idempotent. Profile, cover, group and onboarding avatars use the same signed
R2 pattern, including multipart for larger images and cached idempotency keys.

Legacy image/video/audio/document, story, profile/group avatar and chat-audio
upload receivers now return a conflict/gone response from Laravel. The Docker
and example deployment Nginx configs also reject those old multipart endpoints
before PHP can make an upload temporary file. Chat text remains available at
`/api/messenger/send`; multipart attachment requests there are rejected before
PHP. Onboarding's `/api/tips/avatar/update` rejects multipart bodies while
still accepting the non-upload skip action. Rejected request lengths are written to
`/var/log/nginx/media_upload_rejected.log`; successful R2 promotions emit the
structured `media.r2_object_promoted` event with R2 byte count,
`vps_media_body_bytes=0` and source/target disk names, without keys, tokens or
private URLs.

Legacy profile/group media URL resolution remains supported. New profile and
group rows store the R2 disk alongside the key; rows without a disk continue to
use the existing static storage disk. No advertiser upload endpoint was found.

## Release requirements and limits

The code has an 8 GiB and 3,600-second video policy. This is a maximum, not an
upload-time promise; actual transfer time depends on the user's upstream
connection. Video bytes are not compressed by this R2-only path, so the video
storage component is not reduced by encoding. The API validates the declared
duration and R2 object size/content type but does not parse video bytes to
verify codec or true duration. The Android build source includes a native
publication bridge that streams file parts to signed R2 URLs; it was not built
or run on a physical device during this verification.

R2 direct upload is intentionally off by default in `.env.example`. Before
enabling it in staging/live, configure separate temp and final R2 buckets at
the same R2 endpoint, enable both disks and direct uploads, set CORS to the
actual app origins, and use shared Redis/cache across app instances for upload
locks/idempotency. Use the provided expiry command on the dedicated temp bucket:

```bash
php artisan media:configure-r2-temp-lifecycle --days=3 --apply
```

The lifecycle expires abandoned temp objects after at most three days and
aborts incomplete multipart sessions after one day. Apply the additive
`2026_10_30_100000_add_profile_asset_disks` migration before deploying the new
profile/group association code. Keep the Nginx guards in the live virtual host;
the repo's Docker and example virtual-host configs are updated, but an external
VPS Nginx config does not change until separately deployed. Check the Nginx
rejection log and `media.r2_object_promoted` byte counts alongside VPS
`upload_tmp_dir`/disk metrics during staging.

Do not enable the direct-only frontend until valid R2 configuration, migration,
Nginx guards and lifecycle rules have passed staging. With R2 disabled, old
body upload endpoints intentionally cannot serve as fallback. The profile disk
migration is additive, but rolling back after new R2 avatar/cover paths have
been saved requires migrating those paths back before dropping the disk
columns. Old media URLs were not rewritten.

New video upload requests stay on R2 even if legacy Cloudflare Stream settings
are present. Existing media rows on other disks keep their current URL
resolution; this code does not migrate old media or change its billing/storage.
Cloudflare Stream is not configured as a new upload/transcoding destination.
R2 does not encode video, and no separately billed cloud transcoder is
configured, so this implementation cannot claim a 90% video-size or total-cost
reduction. Any such optimization requires selecting and benchmarking a worker
that reads/writes R2 while keeping video bytes off the VPS and end-user device.

No production deployment, production credentials, real user files, real R2
transfer, browser-device run, Android run or network-disconnection run was
performed for this change. The repository tests use R2 fakes and simulated
browser transfer APIs; real staging verification is still required.

No production deployment is included in this change. See
[`deployment.md`](deployment.md) for the manual production-release gate.
