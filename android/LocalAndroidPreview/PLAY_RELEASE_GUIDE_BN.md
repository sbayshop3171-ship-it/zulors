# Zulors Play Store Release Guide

এই Android wrapper-এ production mode-এ Google Play flexible in-app update flow চালু
থাকে। Play Store থেকে install করা build-এ update available হলে Google Play-এর নিজের
flexible update dialog দেখাবে। Download শেষ হলে app নিরাপদ সময়ে update complete
করবে; active call থাকলে install কিছুক্ষণ defer করবে।

## Build

Play Console-এ APK নয়, signed AAB upload করুন। একই version-এর signed APK শুধু device
sanity test-এর জন্য রাখা হয়।

Double-click:

```bash
android/LocalAndroidPreview/build-play-production.command
```

অথবা Terminal থেকে:

```bash
cd /Users/muhammadrasel/Documents/Zulors/Source/android/LocalAndroidPreview
VERSION_CODE=1789031024 VERSION_NAME=1.0.4 ./build-play-production.command
```

Required secure files are not committed to git. The helper looks for them in the
local app folder first, then in `../../../LocalAndroidPreview`:

```text
.env.play-upload-key
firebase/google-services.json
keystores/zulors-upload.jks
```

## Current Play Candidate

```text
Package: com.zulors.app
Version name: 1.0.4
Version code: 1789031024
Target SDK: 36
Flexible updates: enabled in production builds
Firebase Messaging: enabled in production builds
```

Upload this file to Play Console:

```text
android/LocalAndroidPreview/build/latest/zulors-production-1.0.4-1789031024.aab
```

Use this APK only for same-signature device sanity testing:

```text
android/LocalAndroidPreview/build/latest/zulors-production-1.0.4-1789031024.apk
```

## Important

Flexible in-app updates work only for apps installed through Google Play. Sideloaded
APK/debug APK builds can compile the code, but Google Play will not deliver the
update flow to them.

Before production rollout, use internal/closed testing first and confirm:

- installed app updates from the Play track;
- Post, Story and Chat background uploads still work;
- notification permission denied/allowed paths both behave;
- app minimize/reopen and screen lock do not lose pending uploads;
- Google sign-in and push notification token registration still work.
