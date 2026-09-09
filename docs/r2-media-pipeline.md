# R2 Media Pipeline

## Live Rollout Status (2026-09-08)

**Direct uploads are now enabled on https://zulors.com.** The user chose bounded
production verification on the existing VPS instead of provisioning staging or
additional servers. No new paid infrastructure was provisioned.

Current activation flags are:

```dotenv
R2_DIRECT_UPLOAD_ENABLED=true
R2_DIRECT_UPLOAD_AUTO_CORS_ENABLED=false
MEDIA_VIDEO_DIRECT_ONLY=false
```

CORS now passes for both production origins. The dashboard has an enabled,
bucket-wide three-day object expiration rule and a one-day incomplete multipart
abort rule for `zulors-media-temp`. HEAD responses for disposable objects inside
and outside `tmp/` confirmed a three-day expiration. This verifies rule application,
not an observation of the scheduled deletion three days later. Temp was empty
before broadening expiration; the final bucket is distinct and unaffected.

Configuration was backed up outside the web root with restricted permissions,
Laravel config cache was refreshed and Horizon was restarted gracefully after
checking that video queues were empty. One shared video worker and one FFmpeg
thread remain unchanged. The restricted application storage key was not replaced.

**Direct-only enforcement is deliberately still off.** Supported clients can use
direct uploads now, but legacy/proxy fallback remains available until installed-app
compatibility is verified. The controlled Chromium test used no fallback routes.
Do not describe this rollout as zero total VPS bandwidth or unlimited-load readiness.

### Post Editor Preview Regression (2026-09-09)

Release `80ccefb` was committed, pushed and activated on production as a frontend-only
hotfix. Assets were built locally with the live `VITE_*` settings because the shared
VPS was busy. The six changed source files were checked against the prior live
version, new hashed assets were uploaded, and the manifest was switched atomically.
Old chunks remain for already-open clients; the previous manifest/source is backed
up outside the web root. No database migration, environment change, worker restart,
user upload deletion or server-side build was performed for this hotfix.

At 2026-09-09 07:45 UTC, public HTTP asset hashes and the live manifest matched the
release; home/login/signup returned HTTP 200 and Horizon was running. Direct uploads
remain enabled, direct-only remains off, and FFmpeg still uses one thread. The user's
installed Samsung app still needs to be reopened and retested before device acceptance.

A user testing Samsung app version `0.4.0-production` reported that the upload
progress line repeatedly moved backwards, then the video player changed from a
playable local preview to `0:00` after completion. The web editors were revoking
their local object URLs when refreshing the uploaded draft, whose raw source is
intentionally hidden until FFmpeg finishes after publication. Multipart progress
also dropped active workers' bytes whenever another part was acknowledged.

Mobile and desktop Post editors now retain the device preview for the current
editor session, merge it with the server media ID without duplicate players, and
keep that URL out of public timeline/draft stores. Removal still deletes the real
server media and releases the object URL; editor unmount and successful desktop
publication release it too. Reopened pending drafts without a device URL show a
poster/placeholder, not a broken empty player. Re-selecting the local file is needed
to recover that device-only preview after the editor has been closed.

Multipart progress includes all parts and does not reset on acknowledgements or
retries. Publication remains blocked during upload/verification and after a failed
upload; successful upload can still publish before transcoding, as required by the
publish-triggered FFmpeg flow. This change does not publish originals or enable the
direct-only policy.

Eleven focused Node regressions execute the real mobile/desktop editor setup with
mock API/XHR dependencies, covering direct/fallback completion, progress, failure,
deletion and URL cleanup. A local Chromium test mounts the actual Vue preview
components at 390x844 and 1365x900: the same video element survives completion,
duration remains four seconds, playback and seeking work, and sampled decoded
pixels are nonblank. These tests do not replace a repeat test on the Samsung app.
The full Node suite passed (57 tests), the focused backend suite passed (15 tests,
90 assertions), and the Vite production build passed using the live frontend settings.

```bash
node --test tests/node/post-video-preview.test.mjs
# Requires Playwright/Chrome and FFmpeg. PLAYWRIGHT_MODULE can point to an existing
# Playwright installation; the runner closes its own browser and temporary server.
node tests/browser/post-video-preview.mjs
```

### Earlier Rollouts

The following records describe the earlier CORS hold, superseded by the activation
above. They are retained to distinguish earlier tests from the current state.

Code commit `355a667` was pushed to main and deployed to https://zulors.com using
the staged-release deployment script. The live page checks passed and Horizon is
running. This supersedes the earlier read-only inspection of the legacy release.
No isolated staging environment was supplied or created.

Direct browser uploads were initially disabled pending Cloudflare CORS setup.
The deployed disk was `r2_temp`, with `R2_DIRECT_UPLOAD_ENABLED=false` as an
operational hold. Post/Story/Chat used the existing server-upload fallback, subject
to VPS bandwidth and proxy/PHP limits. The new FFmpeg processing code was deployed.

The real R2 test below verified storage and encoding, but both production origins
returned HTTP 403 to CORS preflight requests. The runtime object credential also
received `AccessDenied` when inspecting bucket CORS and lifecycle configuration.
The lifecycle `--apply` command was attempted but **did not apply a rule**. Existing
bucket rules could not then be inspected using the application's object credential.

Follow-up release `1e2342c` was committed, pushed and deployed on the same date.
Live page checks passed, Horizon was running, and the deployed policy file hash
matched the local release. An in-process CLI check enabled the policy only for
that test process and verified direct-create HTTP 503 and raw/part proxy HTTP 409;
it did not change the live environment flags. The lifecycle `--apply` command was
retried after deployment and again failed at GetBucketLifecycleConfiguration with
AccessDenied, without applying rules.

Follow-up verification before dashboard setup still returned 403 for single and
multipart preflight at both origins. The release introduced an opt-in
`MEDIA_VIDEO_DIRECT_ONLY` policy, initially left false while CORS was blocked.
Those code changes alone did not resolve bucket administration or app acceptance.

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
The example below is a future worker configuration, not a verbatim copy of the
shared VPS environment. Its activation flags match the current compatibility rollout;
the shared-worker settings and legacy timeouts below must be preserved on this VPS.

```dotenv
QUEUE_CONNECTION=redis
CACHE_STORE=redis
MEDIA_QUEUE_CONNECTION=redis
R2_DIRECT_UPLOAD_ENABLED=true
R2_DIRECT_UPLOAD_AUTO_CORS_ENABLED=false
MEDIA_VIDEO_DIRECT_ONLY=false
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

The owner configured production lifecycle rules through the Cloudflare dashboard:
`Default Multipart Abort Rule` now aborts incomplete uploads after one day, and
`delete-temp-files-after-3-days` expires objects after three days with no prefix
filter. Both are enabled. The CLI commands below did not apply these rules and
still require an administrative setup credential; the runtime object key remains
restricted. Do not apply redundant CLI rules just to clear a historical AccessDenied.

The lifecycle service checks storage configuration independently of the direct-upload
feature flag. It can preview/apply while uploads are disabled when authorized;
it never bypasses Cloudflare authorization.

The temp bucket must contain disposable raw uploads only. This command preserves
unrelated lifecycle rules and previews the managed rule before applying it:

```bash
php artisan media:configure-r2-temp-lifecycle --days=3
php artisan media:configure-r2-temp-lifecycle --days=3 --apply
php artisan media:cleanup-temp --hours=72
```

The managed CLI rule expires temp objects after three days and aborts incomplete
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

The follow-up direct-only/lifecycle changes passed the full backend suite again
(243 tests, 1,466 assertions), the three browser multipart tests and the Vite build.
Tests cover rejection of video proxy/fallback routes, unavailable direct creation,
continued direct creation, and lifecycle operation while the upload flag is off.
They also verify that an AccessDenied read cannot lead to overwriting unknown rules.

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

Before CORS setup on 2026-09-08 a CLI harness booted the deployed application and invoked
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

### Live Browser Verification

After activation on 2026-09-08, local desktop Chrome in headless mode opened the real
production login page and called the authenticated production APIs with a one-hour
test-user token and CSRF cookies. It used the repository's shared multipart uploader,
whose SHA-256 matched the deployed source. File selection and API calls were scripted
on that page, not a click-through test of the complete Vue editors. Story and Chat
ran at a 390 x 844 viewport; this was not an installed mobile app/device test.

The source was a six-second 640 x 360 H.264/AAC synthetic MP4 with deliberate CBR
padding: **11,878,524 bytes**. It tests transport and processing, not representative
compression quality. Each Post/Story/Chat upload used two signed R2 parts, exposed
ETags, reached 100%, completed successfully and tolerated repeated completion.
Raw playback URLs were withheld. An injected Story PUT connection failure was
retried successfully. No video server-upload/raw/part-proxy route was called.
Upload client bytes travelled from the local browser to R2, not through the web VPS.

| Flow | Browser create / complete | Encoding verification | Final HEAD bytes |
| --- | --- | --- | --- |
| Post | 200 / 200, 14.21 seconds | In-process job, 10.64 seconds | 580,291 |
| Story | 200 / 200, 20.19 seconds including retry | In-process job, 22.11 seconds | 1,608,903 |
| Private Chat | 201 / 200, 14.94 seconds | Actual Redis/Horizon job | 940,990 |

Post and Story remained drafts during browser testing. Their actual FFmpeg jobs
were then invoked sequentially in CLI database transactions, with broadcasts
suppressed and publication changes rolled back. No public test Post or Story was
published. Only Chat tested queued Horizon execution in this follow-up; do not
describe the draft tests as queued end-to-end publication tests.

All three jobs produced WebP posters, matching optimized_size/final HEAD values,
and removed their original temp objects. Chat output independently probed as
H.264/AAC, 720 x 720, duration 6.013991 seconds. Chrome decoded it, played it and
successfully sought within it. Two CDN range probes returned 206, video/mp4, HIT.
This samples one object/location, not every playback path or cache location.

All six final test objects, both test users and their test data/token were removed;
there were no cleanup errors. The additional local regression run passed 14 tests
with 136 assertions plus the three JavaScript multipart tests. No application-code
change or full production rebuild was needed for activation. Home/login/signup
checks returned 200 and Horizon was running. Small HTTP timing samples are not a
web-latency or concurrent-load benchmark.

### Cloudflare Settings Reference

In Cloudflare R2, open `zulors-media-temp` -> Settings -> CORS Policy. The owner added the rule
from [r2-temp-cors.json](r2-temp-cors.json), retaining any unrelated required origins
or rules. Keep the temp bucket private; do not enable public access to fix CORS.
See [Cloudflare CORS configuration](https://developers.cloudflare.com/r2/buckets/cors/).

The exact production origins are `https://zulors.com` and `https://www.zulors.com`,
without trailing slashes. The rule allows `PUT`, `GET`, `HEAD`, request headers `*`
and exposes response header `ETag`, with a 3,600-second preflight cache. Cloudflare
handles OPTIONS; it is not an extra AllowedMethods entry. Add a staging/WebView
origin only after identifying its actual scheme and host; do not assume all native
apps share the production browser origin.

Under Object lifecycle rules, expiration after three days for disposable raw
objects and abort after one day are now enabled bucket-wide. Review bucket contents
before applying expiration. Use a bucket-administration credential only for setup,
not as a permanent replacement for the application's object-only credential.
See [Cloudflare R2 token permissions](https://developers.cloudflare.com/r2/api/tokens/).

Cloudflare's R2 **Object Read & Write** permission is insufficient for CORS/lifecycle
administration. A one-time R2 **Admin Read & Write** credential can configure buckets;
the corresponding account-level API permission is **Workers R2 Storage Write**.
This is Cloudflare token policy, not an AWS IAM policy to attach to the VPS. Avoid
replacing the running application's restricted key with an account-wide admin key.
The dashboard is the simplest setup path; otherwise supply setup credentials through
a secure deployment channel, apply/verify the rules, then revoke the setup credential.

The following probe works while direct uploads are disabled. It creates disposable
small objects/multipart sessions, checks preflight and JavaScript-visible ETags for
single and multipart PUTs, and deletes/aborts its own test data. It prints no signed
URLs and exits nonzero on a failed check. It does not change CORS or application flags.

```bash
php deploy/check-r2-cors.php https://zulors.com https://www.zulors.com
```

Before dashboard setup all four preflight checks returned 403. After setup, and
again immediately before activation, all four returned 204; signed PUTs returned
200 with exposed ETags. Probe cleanup completed without errors.

Activation set `R2_DIRECT_UPLOAD_ENABLED=true` and
`R2_DIRECT_UPLOAD_AUTO_CORS_ENABLED=false`, refreshed `php artisan config:cache`,
and restarted Horizon gracefully. The disabled auto-CORS setting prevents the
runtime object token from repeatedly attempting bucket administration.
`MEDIA_VIDEO_DIRECT_ONLY` remains false for compatibility. Enable it separately
only after the supported installed clients are verified or version-gated.
For rollback, disable direct uploads while leaving direct-only false, rebuild config
cache and restart workers gracefully after accounting for in-flight uploads/jobs.
Do not restore an old full environment backup over unrelated later settings.

Direct-only mode sets both raw and multipart proxy budgets to zero. Updated Post
editors honor zero without substituting a default budget. Post server video upload,
raw/part proxy, Story video fallback and Chat video fallback return HTTP 409 with
`code=direct_upload_required`. Direct create returns HTTP 503 with
`code=direct_upload_unavailable` when R2 is disabled/misconfigured, instead of telling
the client to upload through PHP. Progress/completion remain available for existing
sessions; this does not disable image/audio uploads or require Cloudflare Stream.

This is a supported-client/application policy, not a network firewall. An old client
can still send bytes to PHP before receiving rejection, particularly on the mixed
Story/Chat routes. Strict prevention of arbitrary ingress requires edge/proxy controls
and version-gating old clients. Do not describe this as zero total VPS bandwidth:
co-located FFmpeg downloads originals and uploads outputs, and current image uploads
also pass through the web host. Move media workers to a separate host to remove their
transfers from the web VPS; direct image uploads would be a separate implementation.

### Mobile Compatibility

This repository's Android LocalAndroidPreview shell loads the web app in a WebView,
with JavaScript and a file chooser. It receives the deployed Vue upload changes.
No independent iOS/Swift or Flutter client source was found, and installed store-app
versions were not identified or device-tested. Do not label all native clients as
verified; independent native upload implementations need endpoint adoption and an
app release. The production-origin CORS hold is resolved; a distinct WebView origin
must still be identified and permitted before it can be considered compatible.

Follow-up tooling check: `adb devices -l` found no connected device, and
`xcrun devicectl list devices` was unavailable because the required Xcode tooling
was not installed/selected. No real installed Android/iOS upload was performed.
Provide a USB-debugging-authorized Android device with the actual installed build,
or an iOS device with its signed build and supported Xcode device tooling. Use a
dedicated test account. Check Post/Story/Chat on Wi-Fi and cellular,
network interruption/retry, background/foreground, seeking and processing completion.
Capture the app version and WebView origin, R2 PUT requests/ETags, and confirm that
no legacy video upload/raw/part proxy route is called. A test of this preview shell
alone does not certify an independently distributed native app.

### Peak-Hour Capacity

There is no measured maximum safe upload size/duration for this shared VPS. Size
alone does not bound decode/encode CPU or memory: duration, resolution, frame rate,
codec and content matter. The synthetic seven-second timing is not a throughput
model for three- or ten-minute user videos.

A conservative starting proposal for post/chat is **256 MiB and 180 seconds** with
one shared video worker and one FFmpeg thread. This is a proposed admission limit
to benchmark, not an applied change or a guarantee of smoothness. The configured
1 GiB / 600-second limits have not been changed, and Story's existing shorter clip
policy should stay in place. Candidate environment limits are:

```dotenv
MEDIA_VIDEO_MAX_BYTES=268435456
MEDIA_VIDEO_MAX_DURATION_SECONDS=180
POST_VIDEO_MAX_SIZE=262144
CHAT_MEDIA_MAX_SIZE=262144
```

Drain already-accepted jobs before lowering the worker's limits; otherwise an
upload admitted under the old limits could fail when processing starts. Benchmark
representative high-resolution/high-frame-rate sources and tune output resolution
and frame-rate policy separately. There is no automatic peak-hours switch.

At the follow-up check, RAM available was about 1.5 GB out of 7.9 GB and there was
no swap; load averages were 2.80 / 3.97 / 4.40. Do not increase worker processes based
on user count alone. Measure queue oldest age, p95/p99 web latency, FFmpeg child RSS,
CPU and scratch usage. Use enforced CPU/memory limits on a dedicated media process
group/container or separate host, leaving capacity for web/database workloads.
One FFmpeg thread is not a hard host resource limit. Start alerts and admission
throttling well before queue age approaches the three-day raw expiry. These host
limits, autoscaling and backlog-based admission are not implemented by this release.

### Remaining Acceptance

The owner explicitly declined staging and authorized low-impact production testing
on the existing VPS. No separate environment or paid worker was provisioned, and
no heavy concurrent-load test was run on the shared host. CORS and lifecycle are no
longer the activation blockers. Remaining acceptance is not waived by going live:

1. Obtain an authorized 500 MB+ representative video and verify browser/app upload,
   direct R2 requests and ETags in a controlled window. The earlier large CLI fixture
   and the small browser fixture do not meet this representative-file requirement.
2. Before publishing, HEAD the temp object and compare ContentLength to original bytes;
   verify there is no raw final object and public APIs return no playable source URL.
3. Verify Post/Story publication through the actual editors and Horizon with an
   agreed test-visibility scope. Record worker CPU/RAM/disk and processing latency.
4. HEAD the final MP4, compare bytes to optimized_size, verify H.264/audio/duration,
   WebP poster, seeking, visual quality and temp deletion. Confirm custom-domain cache
   behavior on repeated requests; existing Cache-Control alone does not prove a hit.
5. Repeat for Story/Chat, disconnect/retry, failed worker, cancelled upload and older
   clients using fallback. Verify installed Android/iOS builds before direct-only
   enforcement. Test simultaneous uploads and backlog separately with explicit
   limits and abort thresholds; production stress testing is not authorized unbounded.
6. Remove only the verification objects/account. Use the normal deployment process
   for subsequent releases, retaining the previous release for rollback.
