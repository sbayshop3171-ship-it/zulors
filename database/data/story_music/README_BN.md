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
