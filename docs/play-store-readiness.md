# Zulors Play Store Readiness

## Current Android Test Artifact

- Live URL used by the production test wrapper: `https://zulors.com/`
- Test package name: `com.zulors.app`
- Local test package name: `com.zulors.localpreview`
- Current production test APK: `LocalAndroidPreview/build/latest/zulors-production-debug.apk`
- Current local preview APK: `LocalAndroidPreview/build/latest/zulors-local-debug.apk`
- Minimum SDK: 23
- Target SDK: 36

The APK is ready for Xiaomi device QA, but it is debug-signed and is not the final Play Store upload artifact.
Google Play release should use an Android App Bundle signed with the final upload key.

## Public Store Links

- App URL: `https://zulors.com/`
- Privacy Policy: `https://zulors.com/privacy-policy`
- Terms of Use: `https://zulors.com/terms-of-use`
- Cookies Policy: `https://zulors.com/cookies-policy`
- Account Deletion: `https://zulors.com/account-deletion`

## Play Store Submission Gates

- Create the Play Console app with package `com.zulors.app`.
- Build a release Android App Bundle (`.aab`) with the final upload signing key.
- Enroll the app in Play App Signing.
- Complete App Content sections: Data safety, Privacy policy, App access, Ads, Content rating, Target audience, News app declaration, Government app declaration, and Account deletion.
- If the Play Console account is a new personal developer account, complete the required closed test before production release.
- Provide review credentials for a normal test account.
- Upload phone screenshots, app icon, feature graphic, short description, full description, and support contact details.

## Data Safety Draft

Zulors is a social platform, so the Data safety answers should be conservative and match the Privacy Policy.
Expected collected data categories include:

- Personal info: name, email, phone number, date of birth, country, city, profile info.
- Photos and videos: avatars, covers, posts, stories, messages, marketplace/job media.
- Audio: audio/video recording or uploaded media if the user uses those features.
- Messages: direct messages and attachments.
- App activity: posts, comments, reactions, follows, bookmarks, search, feed interactions.
- App info and performance: crash/debug logs, diagnostics, device/browser details.
- Financial info: wallet, deposits, transfers, withdrawals, payment provider references if enabled.
- Device or other IDs: session identifiers, device identifier cookies, security identifiers.

Do not declare location collection unless production app location features are intentionally enabled and disclosed.

## Xiaomi QA Checklist

- Install `zulors-production-debug.apk`.
- Launch app and confirm it opens `https://zulors.com/` and redirects to login.
- Register a new account and confirm OTP/verification behavior.
- Login, logout, password reset.
- Update profile, avatar, cover, privacy settings.
- Create text post, image post, video post, story.
- Test Reels: open Explore Reels, swipe vertically, portrait and landscape sizing.
- Test comments, likes, share, bookmark, report, delete.
- Test camera/microphone permission prompts only when a feature needs them.
- Test upload from gallery/files without storage permission prompts.
- Test keyboard behavior on login, signup, post editor, comments, messages.
- Test Android back button on pages, sheets, modals, full-screen video.
- Test poor network or airplane mode fallback.

## Known Next Work

- Create a proper release `.aab` pipeline.
- Replace the debug keystore with a protected upload signing key.
- Add final Play Store screenshots and feature graphic.
- Consider a TWA wrapper with Digital Asset Links if the app should behave as a verified web app instead of a plain WebView wrapper.
