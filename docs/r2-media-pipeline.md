# R2 Media Pipeline

## Live Rollout Status (2026-09-08)

Code commit `355a667` was pushed to main and deployed to https://zulors.com using
the staged-release deployment script. The live page checks passed and Horizon is
running. This supersedes the earlier read-only inspection of the legacy release.
No isolated staging environment was supplied or created.

**Direct browser uploads are temporarily disabled pending Cloudflare CORS setup.**
The deployed disk is `r2_temp`, but `R2_DIRECT_UPLOAD_ENABLED=false` is an intentional
operational hold. Post/Story/Chat use the existing server-upload fallback, which still
uses VPS upload bandwidth and is subject to proxy/PHP limits. The new FFmpeg processing
code remains deployed. Do not advertise 1 GB browser direct uploads as ready yet.

The real R2 test below verified storage and encoding, but both production origins
returned HTTP 403 to CORS preflight requests. The runtime object credential also
received `AccessDenied` when inspecting bucket CORS and lifecycle configuration.
The lifecycle `--apply` command was attempted but **did not apply a rule**. Existing
bucket rules could not be inspected; their presence or absence is not established.

## Implemented Flow

```text
Browser / mobile web client
  -> authenticated create endpoint (metadata only)
  -> signed PUT / multipart PUT directly to private r2_temp
  -> complete endpoint verifies object existence and actual byte size
  -> post/story publish or chat completion dispatches a Redis job
  -> FFmpeg worker streams the original to its local scratch disk
  -> validate actual video duration/size, encode H.264 MP4, create WebP poster
  -> upload optimized output to r2_final, save processed DB state
  -> delete original; queue cleanup retry if remote deletion fails
  -> public playback becomes available
```

The application web server does not receive video bytes in the direct path. The
media worker still downloads the full original and uploads the optimized file;
its network, CPU and scratch-disk costs remain part of operating this pipeline.
Wrapping, splitting, or renaming a video cannot make R2 bill fewer stored bytes.
CRF encoding is lossy; output size depends on content, duration and quality.
There is no universal 1 GB to 10-50 MB conversion guarantee.

Post upload creation enforces 1 GiB and a declared duration of at most 600 seconds.
Workers verify actual size, duration and video stream before encoding. Story clips
keep the existing clip-duration policy; chat video keeps the existing square format.
Image uploads keep the existing synchronous WebP conversion/resize behavior, with
database MIME and extension matching the stored bytes. AVIF and HLS are not enabled.

Post, Story and Chat direct create responses include media_id, uid, upload_type,
upload_id, part_size, parts, upload_concurrency and expires_at. Small objects can use
a single signed PUT; large objects use multipart. Raw originals are never published
by the completion endpoint. Legacy pending uploads already in final still require
transcoding, but an already-public legacy raw object needs a separate migration.

Story and Chat share the browser multipart uploader: bounded concurrency, per-part
retry, progress, and failed-upload state. Local Story preview stays on the uploader's
device. Chat shows a processing placeholder and receives the ready broadcast.
Existing server upload endpoints remain available. Android/iOS native clients that
do not load these Vue stores must adopt the direct endpoints separately.

## Deployment Configuration

Use the existing deployment procedure in deployment.md, preserving shared runtime
uploads. Deploy web and media workers with the same release and configuration.
The example below describes the target configuration after CORS acceptance, not
the current operational hold.

```dotenv
QUEUE_CONNECTION=redis
CACHE_STORE=redis
MEDIA_QUEUE_CONNECTION=redis
R2_DIRECT_UPLOAD_ENABLED=true
R2_DIRECT_UPLOAD_DISK=r2_temp
R2_TEMP_DISK=r2_temp
R2_FINAL_DISK=r2_final
MEDIA_VIDEO_MAX_BYTES=1073741824
MEDIA_VIDEO_MAX_DURATION_SECONDS=600
POST_VIDEO_MAX_SIZE=1048576
CHAT_MEDIA_MAX_SIZE=1048576
MEDIA_FFMPEG_THREADS=2
MEDIA_VIDEO_QUEUE=media-video-normal
MEDIA_VIDEO_NORMAL_QUEUE=media-video-normal
MEDIA_VIDEO_HIGH_QUEUE=media-video-high
MEDIA_VIDEO_MAX_PROCESSES=1
MEDIA_VIDEO_HIGH_MAX_PROCESSES=1
POST_VIDEO_PROCESSING_TIMEOUT=3600
MEDIA_VIDEO_TIMEOUT=3600
MEDIA_VIDEO_HIGH_TIMEOUT=3600
REDIS_QUEUE_RETRY_AFTER=3900
```

Configure the existing R2 access keys, endpoints and distinct bucket names through
the deployment secret store. Direct upload is disabled if temp/final are the same
disk or bucket. Keep temp private: no public custom domain or r2.dev access. Allow
the web/app origins in temp CORS, including PUT and exposed ETag. Use a cached custom
domain for final. The r2.dev development endpoint is not the production CDN endpoint.
See [Cloudflare public buckets](https://developers.cloudflare.com/r2/buckets/public-buckets/).

By default, the production Horizon profile has one worker per video queue.
For the shared 4-CPU production VPS, rollout enabled MEDIA_VIDEO_SHARED_WORKER=true:
one worker consumes the high-priority and legacy/normal video queues, with one FFmpeg
thread. Disable this flag on a dedicated media host when capacity permits parallel jobs.
FFmpeg defaults to two threads; PHP worker memory settings do not limit a child
FFmpeg process. Enforce CPU/RAM and scratch-disk limits using the process manager or
container runtime. Reserve scratch capacity for originals, encoded outputs and retries.

Live rollout keeps `MEDIA_VIDEO_QUEUE=media-video` and a 21,600-second video timeout
for compatibility with old jobs, with Redis `retry_after=21900`. A single worker
consumes `media-video-high,media-video`; FFmpeg uses one thread. The host has about
8 GB RAM and was already busy before testing, so process count was not increased.
Application cache remains file-based on this single host. Switch to shared Redis
cache before splitting hosts so completion and job overlap locks remain shared.

To separate hosts, share the same Redis, cache, database and R2 configuration:

```bash
# Web-side jobs, without video workers
php artisan horizon --environment=web

# Dedicated media host
php artisan horizon --environment=media
```

Do not leave the all-queues Horizon profile running on the web host after separating
workers. Existing deployments using MEDIA_VIDEO_QUEUE=media-video can keep that alias
during rollout; drain old queued jobs before renaming it. Restart workers gracefully
after the release/config cache switch. Do not kill active transcodes during rollout.
Queue visibility timeout must exceed the longest job timeout, including old jobs.

Horizon provisions media-video-high, media-video-normal (or its legacy alias),
media-audio, media-image and media-cleanup queues. The image queue is available for a
later asynchronous image workflow; current image routes still process synchronously.

## Cleanup

Production bucket administration is still pending; the commands below are not a
record of a successful live lifecycle update. The CLI lifecycle service currently
requires direct upload configuration to be enabled. While the operational hold is
active, configure rules in the Cloudflare dashboard instead of enabling uploads
prematurely just to run this command.

The temp bucket must contain disposable raw uploads only. This command preserves
unrelated lifecycle rules and previews the managed rule before applying it:

```bash
php artisan media:configure-r2-temp-lifecycle --days=3
php artisan media:configure-r2-temp-lifecycle --days=3 --apply
php artisan media:cleanup-temp --hours=72
```

The managed rule expires temp objects after three days and aborts incomplete
multipart sessions after one day. Cloudflare deletion is asynchronous, not guaranteed
at the exact expiry second. Review existing temp objects before applying expiration.
See [Cloudflare lifecycle behavior](https://developers.cloudflare.com/r2/buckets/object-lifecycles/).

The scheduler runs temporary cleanup daily and Horizon snapshots every five minutes.
Run the Laravel scheduler on one host. A backlog must not approach raw-object expiry;
alert on oldest queued age, failure rate, scratch capacity, memory and CPU. Autoscaling
hosts, admission quotas and cost-based limits are operational work, not automatically
provided by adding queue names. Capacity/load tests are required before raising limits.

## Verification

```bash
./vendor/bin/phpunit
node --test tests/node/r2-direct-video-upload.test.mjs
npm run build:vite
MEDIA_LARGE_FIXTURE_TEST=1 ./vendor/bin/phpunit --filter test_real_ffmpeg_worker_optimizes_temp_video_and_removes_original
```

Local large fixture verified on 2026-09-08 using real FFmpeg and local fake R2 disks:

The full backend suite passed (235 tests, 1,425 assertions), including the FFmpeg
failure/retry regression. The three browser multipart unit tests and the Vite
production build also passed. Deployment dependency installation/build and Laravel
optimization completed successfully.

| Measurement | Result |
| --- | --- |
| Synthetic raw AVI input | 580,623,842 bytes |
| Optimized MP4 output | 1,419,879 bytes |
| WebP poster | Created |
| Original temp object | Deleted |
| Peak PHP test memory | 75 MB reported by PHPUnit |

This synthetic, seven-second raw video is intentionally easy to compress. This is
not a quality benchmark or a Cloudflare S3/CDN verification. Automated tests also cover
Story/Chat real encoding, multipart metadata, ownership, missing objects/parts, byte
mismatch, pending playback, repeated completion/job execution, cleanup, WebP metadata
and existing server-upload behavior.

### Real Cloudflare Verification

On 2026-09-08 a CLI harness booted the deployed production application and invoked
its actual post create/complete controllers and FFmpeg job in-process. A temporary
test user's database transaction was rolled back and broadcasts were suppressed.
This was not an authenticated browser request, a queued Horizon execution test,
or a concurrent-load benchmark. The upload client ran on the VPS, so it also does
not measure end-user upload speed or demonstrate VPS bandwidth savings in practice.

| Measurement | Live R2 result |
| --- | --- |
| Synthetic raw AVI / verified temp HEAD | 580,623,842 bytes |
| Signed multipart PUTs | 70 |
| Upload time from VPS | 134.64 seconds |
| Completion state | queued, no playable original URL |
| FFmpeg job wall time | 54.23 seconds |
| Final MP4 HEAD / optimized_size | 1,424,317 bytes, matching |
| WebP poster | Created |
| Original temp object | Deleted |
| Final CDN range requests | HTTP 206, MISS then HIT, video/mp4 |
| PHP peak memory | 85,987,328 bytes; excludes FFmpeg child memory |
| CORS for both production origins | HTTP 403, browser upload blocked |
| Cleanup | No errors; test rows rolled back, test objects removed |

The fixture was seven seconds of synthetic uncompressed video. The compression
ratio is not representative of already-compressed user videos or a quality guarantee.
Follow-up inspection found no remaining verification user and an empty temp bucket.
Final CDN cache behavior was sampled for one object at one location only.

### Cloudflare Settings Required

In Cloudflare R2, open `zulors-media-temp` -> Settings -> CORS Policy. Add the rule
from [r2-temp-cors.json](r2-temp-cors.json), retaining any unrelated required origins
or rules. Keep the temp bucket private; do not enable public access to fix CORS.
See [Cloudflare CORS configuration](https://developers.cloudflare.com/r2/buckets/cors/).

Under Object lifecycle rules, add expiration after three days for disposable raw
objects and abort incomplete multipart uploads after one day. Review bucket contents
before applying expiration. Use a bucket-administration credential only for setup,
not as a permanent replacement for the application's object-only credential.
See [Cloudflare R2 token permissions](https://developers.cloudflare.com/r2/api/tokens/).

The following probe works while direct uploads are disabled. It creates disposable
small objects/multipart sessions, checks preflight and JavaScript-visible ETags for
single and multipart PUTs, and deletes/aborts its own test data. It prints no signed
URLs and exits nonzero on a failed check. It does not change CORS or application flags.

```bash
php deploy/check-r2-cors.php https://zulors.com https://www.zulors.com
```

During rollout all four preflight checks returned 403; the probe exited 1 and
successfully aborted its test multipart session without cleanup errors.

Only after the probe passes and bucket lifecycle is verified, set
`R2_DIRECT_UPLOAD_ENABLED=true` and `R2_DIRECT_UPLOAD_AUTO_CORS_ENABLED=false` through
the deployment environment settings, refresh `php artisan config:cache`, and restart
Horizon gracefully. The disabled auto-CORS setting prevents the runtime object token
from repeatedly attempting bucket administration. Retest from the actual browser/app.

### Mobile Compatibility

This repository's Android LocalAndroidPreview shell loads the web app in a WebView,
with JavaScript and a file chooser. It receives the deployed Vue upload changes.
No independent iOS/Swift or Flutter client source was found, and installed store-app
versions were not identified or device-tested. Do not label all native clients as
verified; independent native upload implementations need endpoint adoption and an
app release. The same CORS hold applies to the web and WebView paths.

### Remaining Acceptance

Resolve the Cloudflare settings above before direct browser acceptance. A separate
staging URL, database, R2 buckets and Redis/cache namespace are still required for
isolated testing; no new staging resources were provisioned during this rollout.

1. Use dedicated staging R2 buckets, database and queue namespace. Upload a 500 MB+
   representative video from the browser/app, capturing direct R2 requests and ETags.
2. Before publishing, HEAD the temp object and compare ContentLength to original bytes;
   verify there is no raw final object and public APIs return no playable source URL.
3. Publish, record job time/worker CPU/RAM/disk, and wait for processed state.
4. HEAD the final MP4, compare bytes to optimized_size, verify H.264/audio/duration,
   WebP poster, seeking, visual quality and temp deletion. Confirm custom-domain cache
   behavior on repeated requests; existing Cache-Control alone does not prove a hit.
5. Repeat for Story/Chat, disconnect/retry, failed worker, cancelled upload and older
   clients using fallback. Test simultaneous uploads and processing backlog separately.
6. Remove only the verification objects/account. Use the normal deployment process
   for subsequent releases, retaining the previous release for rollback.
