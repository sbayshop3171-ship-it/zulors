# Story Music R2 Setup

এই setup দিয়ে curated legal music local folder থেকে Cloudflare R2-তে upload হবে এবং database-এ searchable music library তৈরি হবে।

## 1. গান download

Pixabay/Mixkit/CC0/CC-BY থেকে গান manually বাছাই করুন। Popular copyrighted গান, Spotify/YouTube/Apple Music থেকে ripped গান, CC-BY-NC/CC-BY-ND গান ব্যবহার করবেন না।

শুরুতে 100টা যথেষ্ট:

```text
20 romantic
20 sad
20 happy
15 lofi
15 cinematic
10 professional/trending-style
```

## 2. Local folder

```text
storage/app/story-music-library/sources/pixabay/song-name.mp3
storage/app/story-music-library/sources/pixabay/song-name.jpg
storage/app/story-music-library/sources/mixkit/song-name.mp3
```

## 3. CSV বানান

```bash
cp database/data/story_music/music_catalog.example.csv database/data/story_music/music_catalog.csv
```

তারপর `music_catalog.csv`-তে real track info দিন।

## 4. .env

Credential chat-এ paste করবেন না। `.env`-এ রাখুন:

```env
R2_MUSIC_ENABLED=true
R2_MUSIC_ACCESS_KEY_ID=...
R2_MUSIC_SECRET_ACCESS_KEY=...
R2_MUSIC_ENDPOINT=https://ACCOUNT_ID.r2.cloudflarestorage.com
R2_MUSIC_REGION=auto
R2_MUSIC_USE_PATH_STYLE_ENDPOINT=true
R2_MUSIC_BUCKET=zulors-music-library
R2_MUSIC_PUBLIC_URL=
STORY_MUSIC_DISK=r2_music
STORY_MUSIC_PREFIX=music/story-library
STORY_MUSIC_SIGNED_URL_MINUTES=30
STORY_MUSIC_ORIGINAL_AUDIO_ENABLED=true
STORY_MUSIC_ORIGINAL_AUDIO_REQUIRE_CONSENT=false
STORY_MUSIC_ORIGINAL_AUDIO_REQUIRE_ALLOWED_CATEGORY=true
STORY_MUSIC_ORIGINAL_AUDIO_AUTO_PUBLISH=true
STORY_MUSIC_ORIGINAL_AUDIO_MAX_SECONDS=60
STORY_MUSIC_ORIGINAL_AUDIO_EXPIRE_AFTER_HOURS=24
```

## 5. Run

Validate first:

```bash
php artisan story-music:import --dry-run
```

Upload to R2 + sync DB:

```bash
php artisan migrate
php artisan story-music:import
```

Existing track replace করতে:

```bash
php artisan story-music:import --force
```

## 6. App API

List:

```text
GET /api/story/music/tracks?tab=trending
GET /api/story/music/tracks?search=love
GET /api/story/music/tracks?mood=sad
```

Play URL:

```text
GET /api/story/music/tracks/{id}/play-url
```

এই URL short-lived signed URL, permanent public MP3 না।

## 7. User video থেকে Original Audio

Frontend upload request-এ এই category দিলে backend automatically original audio বানাবে:

```text
story_music_category=music_video
story_music_category=reel
story_music_category=story_music_source
```

Video processed হওয়ার পর MP3 max 60 seconds হয়ে R2-তে যাবে এবং `Original audio` tab-এ public হবে। 24 ঘণ্টা পর scheduled command track deactivate করে file remove করবে।

Manual endpoint দরকার হলে app থেকে:

```text
POST /api/story/music/original-audio/media/{media_id}
```

Body:

```json
{
  "story_music_category": "reel",
  "title": "Original audio",
  "mood": "happy",
  "genre": "lofi"
}
```

Video already processed হলে audio extract job সঙ্গে সঙ্গে queue হবে। Video processing চললে processing শেষ হওয়ার পর queue হবে। Result দেখতে:

```text
GET /api/story/music/tracks?tab=original_audio&sort=newest
GET /api/story/music/tracks?tab=original_audio&sort=trending
```

Admin old reel/music videos batch করতে:

```bash
php artisan story-music:extract-original-audio --source=posts --limit=100 --dry-run
php artisan story-music:extract-original-audio --source=posts --limit=100 --include-uncategorized --mark-category=reel --publish
```

Hourly cleanup:

```bash
php artisan story-music:expire-original-audio --delete-files
```

`--include-uncategorized --mark-category=reel` শুধু আপনার approved reel/music videos-এর জন্য ব্যবহার করবেন।
