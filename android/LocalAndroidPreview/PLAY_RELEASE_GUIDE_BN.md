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

Production source-এ Google Play flexible in-app update flow আগে থেকেই আছে। App চালু হলে এবং foreground-এ ফিরলে Play Core update check করে। Play Store eligible update জানালে app-এর update prompt দেখায়; user শুরু করলে download background-এ হয়, তারপর নিরাপদ সময়ে install complete হয়। Active call চললে install পিছিয়ে যায়।

Production build-এর আগে `check-play-update-setup.sh` নিশ্চিত করে update manager, availability check, flexible prompt, install completion, Play dependency এবং build flag source-এ আছে। Production build-এ flag ভুল করে `false` থাকলেও build helper এটিকে `true` করে; প্রয়োজনীয় implementation না থাকলে AAB তৈরি বন্ধ হয়।

এটি Play Store-এর নীরব background auto-update-কে বাধ্য করে না। Play Store থেকে install করা eligible user-কে app update prompt দেখায়; background auto-update কখন হবে তা Play Store-এর user/device setting-এর ওপর নির্ভর করে। নতুন AAB Play Console-এ upload, review ও rollout হওয়ার পরই update eligible ব্যবহারকারীরা তা পাবে; release পৌঁছাতে সময় লাগতে পারে। Sideloaded APK-তে Play update check কাজ করে না।

## পরবর্তী release build

নতুন release-এর default version name `1.0.9`; version code সর্বশেষ প্রকাশিত `1792433586`-এর চেয়ে বড় হতে হবে. Build helper-এ Play-এর জন্য minSdk 24 সেট করা আছে.

```bash
cd android/LocalAndroidPreview
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

`AndroidManifest.xml` হলো একমাত্র manifest source; build helper local/production mode অনুযায়ী এর placeholders পূরণ করে। Generated manifest থাকে timestamped build folder-এ, upload folder-এ নয়.

Production signing files project root-এর `LocalAndroidPreview` folder-এ থাকে। Private password ও keystore Git-এ যাবে না; একমাত্র canonical Firebase app config `LocalAndroidPreview/firebase/google-services.json`-এ রাখা হয়েছে এবং GitHub-এ push করা যাবে:

```text
.env.play-upload-key
keystores/zulors-upload.jks
```

Firebase client config-এ private signing key/password নেই। তাই project-এর অন্য `google-services.json` copy বাদ দিয়ে শুধু canonical config ব্যবহার করুন.

এই upload key-টাই প্রতিটি Production build-এ ব্যবহার হবে; নতুন keystore বানাবেন না এবং Firebase-এ একই SHA-1 বারবার যোগ করার দরকার নেই:

```text
Alias: zulors-upload
SHA-1: EE:5C:95:A4:24:90:58:06:B5:D9:04:2A:B8:A5:4B:D0:ED:9E:1D:5A
SHA-256: 0E:9D:9E:43:73:18:3A:63:5C:D2:C3:44:0D:76:C3:F9:5B:FA:2D:DB:84:CD:E4:C0:EA:FC:A6:1B:88:2A:15:C4
```

Production rollout-এর আগে internal/closed test track-এ যাচাই করুন:

- Play track-এর পুরোনো supported install-এ update আসে;
- Post, Story ও Chat background upload ঠিক থাকে;
- notification permission allowed/denied দুই অবস্থায় কাজ করে;
- app minimize/reopen ও screen lock-এর পর pending upload থাকে;
- Google sign-in ও push notification token registration কাজ করে.

## নতুন Mac বা fresh clone থেকে একই release setup

যে ফাইলটি Git-এ থাকবে:

```text
LocalAndroidPreview/firebase/google-services.json
```

এটাই `com.zulors.app`-এর একমাত্র canonical Firebase config। Root `.gitignore`-এ শুধু এই নির্দিষ্ট ফাইলটিকে track করার exception আছে; অন্য `google-services.json` কপি build-এ ব্যবহার হয় না.

Play upload keystore এবং password private signing credential। এগুলো Git history-তে রাখা হবে না—public বা private repository-তে ভুল করে commit হলেও key আর নিরাপদ থাকে না। একবার GitHub CLI দিয়ে signing info-গুলো repository Actions secrets-এ সংরক্ষণ করুন:

```bash
brew install gh                 # gh আগে থেকে না থাকলে
gh auth login
bash android/LocalAndroidPreview/configure-github-signing-secrets.sh
```

স্ক্রিপ্টটি বর্তমান `LocalAndroidPreview/keystores/zulors-upload.jks` এবং `.env.play-upload-key` থেকে key ও password সরাসরি GitHub-এ পাঠায়; terminal-এ credential দেখায় না। GitHub repository-তে এগুলো `ANDROID_UPLOAD_KEYSTORE_BASE64`, `RELEASE_STORE_PASSWORD`, `RELEASE_KEY_PASSWORD`, `RELEASE_KEY_ALIAS`, `RELEASE_KEYSTORE_TYPE` নামে Actions secrets হবে। এই সেটআপ একবারের; secret value পরে আর Firebase-এ যোগ করতে হবে না.

এরপর workflow ও canonical Firebase JSON GitHub-এ push করার পর ভবিষ্যতে নতুন Mac-এ repository clone করে GitHub-এর **Actions → Android production build → Run workflow** থেকে signed AAB/APK তৈরি করা যাবে। AAB ও APK ১৪ দিনের জন্য workflow artifact হিসেবে থাকবে; workflow নিজে Play Store-এ release করে না। Local machine-এ সরাসরি production-sign করা APK/AAB বানাতে keystore ও password পুনরুদ্ধার করতে হবে; GitHub Actions secret থেকে সেগুলো plaintext-এ download করা যায় না.

নতুন signing secret setup-এর আগে Mac reset হলে keystore/password-এর আলাদা encrypted backup ছাড়া GitHub clone থেকে একই signing identity ফিরিয়ে আনা যাবে না। তাই প্রথমে secret setup script চালিয়ে সফলতা নিশ্চিত করুন; এরপর চাইলে keystore-র encrypted offline backup রাখুন। Firebase JSON public client config, কিন্তু signing key/password নয়.

Play Console-এর upload-key reset pending থাকলে ওই request approve হওয়ার পরই নতুন key-তে signed AAB upload করা যাবে। Workflow build ও test করতে পারে, কিন্তু Play Console upload-key reset approval-কে bypass করতে পারে না.
