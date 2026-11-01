# Adaptive video playback and HLS processing

R2 post videos publish from the verified MP4. When HLS processing is enabled,
`GeneratePostVideoHls` runs on the existing `media-video-normal` queue and
creates a master playlist plus 240p, 360p, 480p and 720p renditions that do not
exceed the source height. The master playlist is published only after its
segments are uploaded. The MP4 remains the canonical source and playback
fallback throughout processing and after a failed job.

The production media pipeline serves these public R2 objects through the
Cloudflare cached custom domain (`media.zulors.com`). Playlists and segments
receive `public, max-age=31536000, immutable` cache headers. R2 serves byte
ranges for direct MP4 requests; the service worker passes range requests through
instead of storing incomplete media responses. The web and mobile feed players
prefer HLS, use HLS.js where native HLS is unavailable, and fall back to the
MP4 if adaptive playback cannot start.

Feed cards render the thumbnail first. The video player is created within 500px
of the viewport, starts with `preload="none"`, and attaches its playlist or MP4
only near the viewport. Off-screen players pause and release their buffered
source; the feed playback arbiter allows one visible video to play at a time.
Timeline prefetch warms video thumbnails but does not fetch video bytes.

## Rollout controls

```env
MEDIA_REELS_HLS_ENABLED=true
MEDIA_REELS_HLS_USER_IDS=*
MEDIA_REELS_HLS_TIMEOUT_SECONDS=21600
MEDIA_REELS_HLS_STALE_AFTER_MINUTES=120
```

FFmpeg with `libx264` and AAC support must be available to the Horizon worker
consuming `media-video-normal`. The production media pipeline configuration
enables processing for newly published R2 post videos and exposes HLS to all
authenticated viewers (`*`). A comma-separated user ID list can be used for a
staged rollout in other environments. Turning the flag off immediately makes
R2 playback use the MP4 again; Cloudflare Stream media keeps its existing
playback path. Previously uploaded MP4s are not batch-transcoded by deploy;
they remain playable and can be requeued separately when desired.

The additive `MediaResource` metadata reports `playback_state`,
`background_processing_state`, and `metadata.playback.hls_state` with rendition
readiness. The R2 MP4 `source_url` remains present for old clients. No database
migration or new public endpoint is needed.

## Recovery and observation

The scheduler runs `media:reconcile-reel-hls` every five minutes. It requeues
R2 jobs whose heartbeat is older than the configured stale limit. Terminally
failed jobs remain MP4-playable and can be explicitly requeued with:

```bash
php artisan media:reconcile-reel-hls --retry-failed
```

The worker logs queue age, encode duration, rendition count and completion
outcome to the performance log. Client performance events include play
requests, first-frame duration, stalls, successful recoveries, selected
rendition and terminal playback failures. Those events contain only route,
client variant and coarse network/device classes; they do not include media
URLs or user, post, media or persistent device IDs.

Monitor first-frame duration, stall and recovery rates, processing queue age,
HLS rendition readiness and MP4 fallback rate after rollout. Encoding increases
worker CPU use and adds HLS objects to R2 storage; the original MP4 remains in
place.
