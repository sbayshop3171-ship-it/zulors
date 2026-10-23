# Zulors Play Store Release Guide

## সর্বশেষ Production Release

Google Play Console-এর ১৯ অক্টোবর ২০২৬-এর screenshot-এ নিচের release-টি **Available on Google Play** দেখাচ্ছে:

```text
Package: com.zulors.app
Version name: 1.0.8
Version code: 1792433586
Minimum SDK: 24 (Android 7.0+)
Target SDK: 36
Countries/regions: 178
Supported Android devices: 19,321
Flexible in-app updates: enabled in production build
```

Google Play Store listing-এর screenshot-এ **Uninstall / Open** দেখা যায়। `Open` মানে app ফোনে install আছে; এটি `Update` নয়। ফোনে installed version এবং Android version না দেখে ওই ফোনে update না দেখানোর নির্দিষ্ট কারণ বলা যায় না:

- Installed version `1.0.8` হলে ফোনটি সর্বশেষ release-এ আছে, তাই `Open` স্বাভাবিক।
- Installed version পুরোনো এবং Android 7/API 24-এর নিচে হলে minSdk 24-এর কারণে 1.0.8 update দেওয়া যাবে না। আগের release review-তে Console ১,১৫৮টি device হারানোর warning দেখিয়েছিল—এটি device compatibility-র প্রভাব, ব্যবহারকারীর সংখ্যা নয়।
- Installed version পুরোনো এবং Android 7/API 24 বা তার বেশি হলে Play Store-এর app details এবং **Manage apps & device → Updates available** দেখুন। একই Google account ব্যবহার হচ্ছে কি না, app Play Store থেকেই install হয়েছিল কি না, এবং listing-এ `Update` আসে কি না যাচাই করুন। Google Play update eligibility-তে app ID/signing মিল, user library-তে app থাকা, device compatibility, এবং installed version পুরোনো হওয়া লাগে.

ফোনে version দেখতে **Settings → Apps → Zulors → App info** দেখুন। Android version দেখতে **Settings → About phone → Android version** দেখুন। Play Store থেকে app auto-update চালু করতে app listing-এর `⋮` menu-তে **Enable auto update** বেছে নিন; global auto-update, network, account sign-in, এবং device eligibility-ও update কখন install হবে তা প্রভাবিত করে.

## Update behavior

Production build-এ Google Play flexible in-app update flow চালু। App চালু হলে এবং foreground-এ ফিরলে app Play Core-কে update check করে. Play Core eligible update জানালে app dialog দেখায়; user download-এ সম্মতি দিলে download background-এ হয়. Download শেষে app নিরাপদ সময়ে install complete করে; active call থাকলে তা defer হয়. এটি নীরবে/জোর করে update install করে না. Google Play-এর background auto-update আলাদা এবং Play Store-এর user/device setting-নির্ভর.

Flexible in-app update Play Store থেকে install করা app-এর জন্য. Sideloaded/debug APK-তে Play Store update flow কাজ করবে না. Google Play Console-এ upload করার artifact হলো signed AAB; APK শুধু local test-এর জন্য.

## পরবর্তী release build

নতুন release-এর default version name `1.0.9`; version code সর্বশেষ প্রকাশিত `1792433586`-এর চেয়ে বড় হতে হবে. Build helper-এ Play-এর জন্য minSdk 24 সেট করা আছে.

```bash
cd /Users/muhammadrasel/Documents/Zulors/Source/android/LocalAndroidPreview
VERSION_NAME=1.0.9 MIN_SDK=24 TARGET_SDK=36 ./build-play-production.command
```

`VERSION_CODE` না দিলে helper Unix timestamp ব্যবহার করে. Bundle upload করার আগে version code-টি Play Console-এ প্রকাশিত সর্বশেষ code-এর চেয়ে বড় බව যাচাই করুন.

Play Console-এ upload করার জন্য 1.0.8 AAB:

```text
android/LocalAndroidPreview/build/latest/zulors-production-1.0.8-1792433586.aab
```

একই build-এর APK local testing-এর জন্য:

```text
android/LocalAndroidPreview/build/latest/zulors-production-1.0.8-1792433586.apk
```

Play Store-এ পরবর্তী app update দিতে নতুন signed AAB upload করে Google review/rollout সম্পন্ন করতে হবে. Website-এ GitHub push একা Play Store-এর Android binary বদলায় না.

## Signing and test notes

Release signing environment এবং keystore Git-এ commit করা হয় না. Build helper এগুলো local app folder অথবা `../../../LocalAndroidPreview` থেকে নেয়:

```text
.env.play-upload-key
firebase/google-services.json
keystores/zulors-upload.jks
```

Production rollout-এর আগে internal/closed test track-এ যাচাই করুন:

- Play track-এর পুরোনো supported install-এ update আসে;
- Post, Story ও Chat background upload ঠিক থাকে;
- notification permission allowed/denied দুই অবস্থায় কাজ করে;
- app minimize/reopen ও screen lock-এর পর pending upload থাকে;
- Google sign-in ও push notification token registration কাজ করে.
