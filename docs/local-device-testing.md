# Zulors Local Device Testing

Use this checklist before Play Store packaging. Do not publish until every
critical flow is tested on a real Android phone and at least one emulator.

## Test Modes

- Wi-Fi phone test: run the Laravel server on `0.0.0.0:8000`, then open the
  computer LAN URL on the phone.
- USB phone test: run `adb reverse tcp:8000 tcp:8000`, then open
  `http://127.0.0.1:8000` on the phone.
- PWA test: open the site in Chrome, add it to the home screen, then test
  full-screen app-like behavior.
- Browser responsive test: check 360px, 390px, 430px, tablet, and desktop.

## Ready Commands

Run checks only:

```sh
bash scripts/local-test-doctor.sh
```

Start local mobile server later when needed:

```sh
bash scripts/start-local-mobile-server.sh
```

Prepare USB localhost access later when needed:

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
