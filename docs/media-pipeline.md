# R2 Direct Media Pipeline

## Current user-upload path

New post/reel, story, chat, profile, cover, group-avatar and onboarding-avatar
uploads use signed, object-scoped Cloudflare R2 URLs. Large files use resumable
multipart transfers. Laravel handles authentication, authorization, quotas,
metadata, completion verification and database paths; it does not receive media
bodies or create media temp files. Images can be resized in a browser worker.
Videos are sent in their original bytes so phones do not spend time, battery or
RAM re-encoding them. Documents remain unchanged.

The upload button starts the R2 transfer without a video-preparation step. The
browser persists part ETags for retry/resume, and repeat create/complete requests
reuse the same publication or media identity. Completed R2 objects are verified
and promoted with an R2-side copy; failed or abandoned temporary objects are
covered by the R2 lifecycle policy.

## Video compression and real limits

R2 is object storage. It does not encode or compress videos. This R2-only,
no-device-encoding path gives fast transfer startup, resume after interruption,
no VPS media disk usage and no VPS video processing. It does not shrink video
bytes or make a 1 GB file finish in seconds. Transfer time is bounded by the
user's network upload speed. Browser playback also depends on the codec inside
the original container; the upload API checks declared type and R2 size/type
metadata but does not inspect the video bitstream or prove its duration.

To reduce video storage bytes, an encoder must run somewhere. Running it on a
phone can create heat, lag, battery drain and unpredictable delay. Running it
on the VPS violates the R2-only media-body rule. Running it in a separate cloud
transcoder can keep source and output objects in R2, but adds compute charges
and requires a separately deployed worker. No such transcoder is configured by
this implementation. Do not advertise a 90% reduction or immediate readiness
until a real-device corpus and the chosen transcoder have been benchmarked.

## R2 cost model

Cloudflare's current R2 Standard pricing includes 10 GB-month storage, 1 million
Class A requests and 10 million Class B requests per month; beyond those
allowances it lists storage at $0.015/GB-month, Class A at $4.50/million and
Class B at $0.36/million. R2 internet egress is free. See
[Cloudflare R2 pricing](https://www.cloudflare.com/products/r2/) and
[multipart upload limits and cleanup](https://developers.cloudflare.com/r2/objects/upload-objects/).

As a storage-only illustration before free allowances, 1,000 GB stored for a
full month is about $15. If encoding truly reduces that same stored corpus by
90% to 100 GB, it is about $1.50, saving about $13.50/month in that scenario.
That is a 90% reduction in the variable storage component, not automatically a
90% reduction in the total hosting bill. The transcoder, VPS, database, cache,
operations and temporary retention can change the total. The direct R2 path
keeps source and output in R2 and does not incur an R2 internet-egress fee when
the app reads from R2; request and compute costs still need measuring.

The current pipeline uses separate private temp and final buckets to support
short temp retention and server-side promotion. They must be in the same R2
account/endpoint. A completed source is deleted after the final copy is verified.
Multipart uploads should be aborted after one day and unused temp objects should
expire after three days. Do not apply the temp lifecycle rule to the final
bucket.

## Local checks

```bash
vendor/bin/phpunit --filter='MediaPublicationApiTest|DirectAssetUploadApiTest|PublicationFinalizerTest|MediaStorageHygieneTest'
npm run test:ws
npm run build:vite
```

These tests use storage fakes and simulated browser transfer requests. They do
not prove latency, R2 CORS, device codec behavior, a real Android WebView upload,
or network-resume behavior on a physical device.

## Staging gate (keep production disabled)

Keep the feature flag off until the staging checks below pass:

```env
R2_DIRECT_UPLOAD_ENABLED=false
```

Use separate staging temp/final buckets, staging-only credentials, the actual
app origins in CORS, shared Redis/cache for all app instances, and a staging
custom media domain. Run the additive profile/group disk migration, apply the
R2 temp lifecycle policy, put the Nginx multipart guards on the staging host,
and confirm `nginx -t` succeeds. Then test small and large images, 1 GB and
multi-part videos, interrupted/resumed uploads, duplicate completion, expired
URLs, unsupported codecs, documents and rejected legacy upload routes. Verify
that final objects are in R2, no request reached PHP with media bytes, failed
uploads do not create duplicate posts, and R2/VPS metrics contain no secrets or
signed URLs.

The local implementation and automated tests are not a substitute for those
staging checks. Do not enable the production flag or deploy this change until
staging evidence and rollback steps have been reviewed.
