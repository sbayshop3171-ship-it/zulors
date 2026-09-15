# Story Music Catalog

এই folder-এ production-safe music metadata রাখবেন। Actual MP3/cover এখানে রাখবেন না।

## Local File Folder

গান/cover local machine-এ রাখুন:

```text
storage/app/story-music-library/sources/pixabay/happy-day.mp3
storage/app/story-music-library/sources/pixabay/happy-day.jpg
storage/app/story-music-library/sources/mixkit/soft-memories.mp3
storage/app/story-music-library/sources/mixkit/soft-memories.jpg
```

## CSV

`music_catalog.example.csv` copy করে `music_catalog.csv` বানান।

Required columns:

```text
slug,title,source,source_url,license_url,license_type,audio_file
```

Allowed `license_type`:

```text
pixabay, mixkit, cc0, cc-by, licensed
```

Blocked:

```text
cc-by-nc, cc-by-nd, non-commercial, no-derivatives
```

Collections:

```text
for_you, trending, old, professional, saved
```

Important: `trending` মানে copyrighted viral গান নয়; royalty-free trending-style গান।

## Original Audio Auto Extract

Video upload থেকে audio list-এ আনতে request-এ category দিতে হবে:

```text
story_music_category=reel
story_music_category=music_video
story_music_category=story_music_source
```

Optional search metadata দিলে user search ভালো কাজ করবে:

```text
music_title=Song name
music_artist=Artist name
music_album=Album name
lyrics_keywords=first line, hook words, chorus words
search_keywords=lofi, sad, romantic, trending
```

Backend MP3 বানানোর পরে audio quality gate চালায়: duration, sample rate, bitrate,
volume, silence ratio দেখে weak/silent/broken audio skip করবে। External recognition
provider না থাকলে backend নিজে copyrighted song name বা lyrics 100% চিনতে পারে না;
provider result থাকলে `recognized_title`, `recognized_artist`,
`recognition_confidence` পাঠানো যাবে এবং সেগুলো search index-এ যাবে।
