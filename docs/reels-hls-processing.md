# Reels HLS playback and processing

R2 post videos publish from the verified MP4. When HLS processing is enabled,
`GeneratePostVideoHls` runs on the existing `media-video-normal` queue and
creates a master playlist plus 240p, 360p, 480p and 720p renditions that do not
exceed the source height. The master playlist is published only after its
segments are uploaded. The MP4 remains the canonical source and playback
fallback throughout processing and after a failed job.

## Rollout controls

```env
MEDIA_REELS_HLS_ENABLED=false
MEDIA_REELS_HLS_USER_IDS=123,456
MEDIA_REELS_HLS_TIMEOUT_SECONDS=21600
MEDIA_REELS_HLS_STALE_AFTER_MINUTES=120
```

Keep processing disabled until FFmpeg with `libx264` and AAC support is
available to the Horizon worker consuming `media-video-normal`. Set the flag to
`true` to process newly published R2 post videos. The API exposes the HLS URL
only while the flag is on and the authenticated viewer ID is in the comma
separated allowlist. Turning the flag off immediately makes R2 playback use the
MP4 again; Cloudflare Stream media keeps its existing playback path.

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

Start with a small user-ID allowlist. Compare first-frame duration, stall and
recovery rates, processing queue age and MP4 fallback rate before expanding it.
