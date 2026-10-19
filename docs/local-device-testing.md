# Zulors Local Device Testing

Use this checklist before Play Store packaging. Do not publish until every
critical flow is tested on a real Android phone and at least one emulator.

## Test Modes

- Local browser: run `bash scripts/start-local-mobile-server.sh`, then open
  `http://127.0.0.1:8001/explore/posts`. The script starts Laravel and Vite so
  frontend edits update without a production rebuild.
- USB Android WebView: connect and authorize the device before starting the
  server. The start script forwards ports 8001 and 5173. If the device was
  connected later, run `bash scripts/adb-reverse-localhost.sh` and open
  `http://127.0.0.1:8001/explore/posts` in the local preview app.
- PWA test: open the site in Chrome, add it to the home screen, then test
  full-screen app-like behavior.
- Browser responsive test: check 360px, 390px, 430px, tablet, and desktop.

## Ready Commands

Run checks only:

```sh
bash scripts/local-test-doctor.sh
```

Start local Laravel + Vite servers:

```sh
bash scripts/start-local-mobile-server.sh
```

Prepare USB localhost access manually if the device was connected after startup:

```sh
bash scripts/adb-reverse-localhost.sh
```

## Core QA Checklist

- Auth: login, register, logout, password reset, wrong password errors.
- Account: profile edit, avatar upload, cover upload, privacy settings.
- Feed: load home feed, create post, edit post, delete post, like, comment,
  share, save, report.
- Media: image upload, video upload, audio playback, upload progress, failed
  upload handling.
- Stories: create story, view story, delete story, story privacy/info states.
- Messenger: inbox, chat open, send text, send media, reply, archive, delete,
  report, group chat if enabled.
- Marketplace: list, search, category filter, product detail, create product,
  edit product, delete product, image upload.
- Jobs: list, search, category filter, job detail, create job, edit job,
  publish/unpublish, archived/active/all tabs.
- Business: business home, business jobs, business marketplace, wallet pages,
  mobile card layout.
- Wallet: balance, hide/show balance, wallet number copy, transactions,
  deposit, withdrawal/cashout link.
- Notifications: notification list, counters, realtime behavior if broadcast
  is configured.
- Settings: language, theme, notification settings, social links, account
  deletion route and policy links.
- PWA: manifest loads, service worker registers, install prompt/home-screen
  install, app opens without browser chrome.

## Android UX Checklist

- Bottom navigation is reachable with one thumb.
- Back button behavior is predictable on pages, sheets, and modals.
- Keyboard does not cover important form actions.
- File picker/camera permission works on Xiaomi.
- Long text does not overflow cards or buttons.
- Pull/scroll interactions remain smooth.
- Error and empty states look intentional.
- Dark mode remains readable.

## Bug Report Template

- Feature:
- Device:
- URL:
- Steps:
- Expected:
- Actual:
- Screenshot/video:
- Priority: High / Medium / Low

## Play Store Gate

Before Play Store upload, Zulors still needs a native Android wrapper or TWA,
a production HTTPS domain, real Play Store assets, privacy/data safety answers,
review credentials, and closed testing if required by the Play Console account.
