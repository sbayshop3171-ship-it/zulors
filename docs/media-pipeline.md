# Media Pipeline Roadmap

এই project এখন কম খরচের Cloudflare R2 media pipeline support করে:

`Browser/Mobile -> R2 temp direct upload -> Laravel queue -> FFmpeg/WebP optimization -> R2 final -> Cloudflare CDN`

## Local Test

1. Install system tools:
   ```bash
   ffmpeg -version
   ffprobe -version
   redis-server --version
   ```

2. Keep local queue/worker running:
   ```bash
   php artisan horizon
   ```

3. For local-only testing, keep R2 direct upload disabled:
   ```env
   R2_DIRECT_UPLOAD_ENABLED=false
   R2_TEMP_ENABLED=false
   R2_FINAL_ENABLED=false
   ```

4. Test uploads:
   - Upload a large image and confirm it becomes WebP with max `2048x2048`.
   - Upload a video, submit the post, and confirm the post stays processing until the queue completes.
   - Run `php artisan media:cleanup-temp --hours=1` to test stale temp cleanup.

## Cloudflare R2 Setup

Create two R2 buckets:

- `media-temp`: private raw temporary uploads.
- `media-final`: optimized final media.

Add a lifecycle rule on `media-temp`:

- Delete objects under `tmp/direct/videos/` after `1-3 days`.

Connect a custom domain to the final bucket:

```text
media.yourdomain.com
```

Use this URL in:

```env
R2_PUBLIC_URL=https://media.yourdomain.com
```

## R2 CORS

For the temp bucket, allow your app domains to upload with PUT:

```json
[
  {
    "AllowedOrigins": ["https://yourdomain.com", "https://www.yourdomain.com"],
    "AllowedMethods": ["PUT", "GET", "HEAD"],
    "AllowedHeaders": ["*"],
    "ExposeHeaders": ["ETag"],
    "MaxAgeSeconds": 3600
  }
]
```

## Production ENV

```env
R2_ACCESS_KEY_ID=...
R2_SECRET_ACCESS_KEY=...
R2_ENDPOINT=https://ACCOUNT_ID.r2.cloudflarestorage.com
R2_REGION=auto

R2_TEMP_ENABLED=true
R2_TEMP_BUCKET=media-temp
R2_FINAL_ENABLED=true
R2_FINAL_BUCKET=media-final
R2_PUBLIC_URL=https://media.yourdomain.com

R2_DIRECT_UPLOAD_ENABLED=true
R2_FINAL_ROUND_ROBIN=true

QUEUE_CONNECTION=redis
MEDIA_VIDEO_MAX_PROCESSES=1
POST_IMAGE_QUALITY=84
POST_VIDEO_CRF=24
POST_VIDEO_PRESET=veryfast
```

## Live Server Commands

```bash
composer install --no-dev --optimize-autoloader
npm ci
npm run build
php artisan migrate --force
php artisan optimize:clear
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan horizon:terminate
```

Supervisor/systemd must keep these running:

```bash
php artisan horizon
php artisan reverb:start
```

Cron must run:

```bash
* * * * * cd /path/to/source && php artisan schedule:run >> /dev/null 2>&1
```

## Cost Control Rules

- Final R2 bucket stores only optimized WebP images and H.264 MP4 videos.
- Raw direct uploads stay in `media-temp` only temporarily.
- `media:cleanup-temp` removes old local/R2 temp files.
- Final media uses `Cache-Control: public, max-age=31536000, immutable`.
- `media.yourdomain.com` should be cached through Cloudflare Cache Rules.
- Video queue concurrency should start at `1` on a small VPS, then increase only after monitoring CPU/RAM.
