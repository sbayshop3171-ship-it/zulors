# Zulors Call Quality QA

Use this checklist before releasing audio-call changes. Android currently uses
Agora Voice SDK 4.7.0 and web/PWA uses Agora RTC SDK 4.24.9. Video calling and iOS
are outside this audio-call scope.

## Automated Checks

Run these before an APK handoff:

```sh
npm run test:ws
npm run build:vite
./vendor/bin/phpunit --filter AudioCallsTest
APP_MODE=local EXTERNAL_APP_DIR="$PWD/LocalAndroidPreview" bash android/LocalAndroidPreview/build-local.sh
```

Expected result:

- Node tests pass.
- Audio call feature tests pass.
- Vite production build completes.
- Android debug build completes with the configured Agora Maven repository.
- A fresh APK is created in `android/LocalAndroidPreview/build/latest/`.

## Device Matrix

Minimum manual coverage:

- Device A on stable Wi-Fi
- Device B on a second Wi-Fi or mobile data
- One test with earpiece
- One test with speaker enabled
- One test with wired or Bluetooth audio if available
- One call with the app backgrounded and screen locked on each Android device
- Several communication-volume levels on both earpiece and speaker
- Web/PWA call with autoplay blocked, then resumed by a user tap

## Core Audio Tests

### 1. Repeat Or Echo Check

- Start a one-to-one audio call.
- Keep both phones close enough to behave like real use, but not muted.
- Speak a short phrase such as "hello one two three".
- Confirm the speaker does not hear their own voice repeating back.
- Repeat once with earpiece, then once with speaker enabled.

Pass:

- No noticeable self-repeat on earpiece.
- Speaker mode may add a small amount of room feedback, but it should not
  create a strong loop or delayed echo.

### 2. Slow Network Recovery Check

- Start the call on a good connection.
- Move one device to weak mobile data or throttle the hotspot/router.
- Keep speaking from both sides during the degraded period.
- Watch whether audio returns by itself after a short disruption.

Pass:

- Call stays connected while remote audio is still live.
- Brief delay is acceptable.
- Audio becomes understandable again without forcing the user to redial.

### 3. One-Way Audio Delay Check

- Caller says "hello" immediately after answer.
- Receiver confirms whether the first greeting arrives late, clipped, or not
  at all.
- Repeat 5 to 10 times on different networks.
- Include 10 greeting calls on a stable network and record whether the first
  greeting is clipped, delayed, or missing.

Pass:

- The first spoken words arrive consistently.
- Delay may grow on weak network, but audio should still land without the call
  collapsing quickly.
- The status changes to one-way audio if the call transport connects but remote
  audio does not become active.

## How To Separate Code Issues From Network Issues

Likely code or routing issue:

- Repeat happens mainly on speaker mode only.
- Repeat happens on strong Wi-Fi too.
- One-way audio happens on every call start.
- Route badge changes unexpectedly between earpiece, speaker, wired, or
  Bluetooth.

Likely network issue:

- RTT, jitter, or packet loss spikes during the problem window.
- Audio delay gets worse only when moving to weak mobile data.
- The same build is stable on good Wi-Fi but degrades under throttling.

## Logs And Signals To Capture

When a call fails QA, collect:

- Device model
- Android version
- Call route: earpiece, speaker, wired, Bluetooth
- Network type: Wi-Fi, 4G, 5G, hotspot
- Whether remote audio connected before the issue
- Whether the call recovered without redial
- The call's recorded `channel_join_duration_ms` and
  `local_audio_publish_duration_ms` from the ready signal
- The first `remote_audio_arrived` timing and any `route_changed` events from
  heartbeat metadata
- A screen recording or second-phone video if possible

## Current Fix Coverage

These fixes are now in place:

- Native Agora readiness waits for both channel join and microphone encoding;
  transport connection alone does not send ready.
- The server marks an Agora call connected only after both participants report
  that media is ready.
- Android volume buttons target the communication stream while calling, and
  the previous volume stream is restored after the call. The earpiece uses a
  safe receiver level; speaker mode uses a higher Agora playback gain. Receiver
  hardware cannot be expected to match the loudspeaker's acoustic output.
- Web/PWA remote audio uses Agora's early receive/play path without a second
  manual subscription path. The app's media element applies output gain once.
- Reconnect and degraded-network hangups are now tied to actual remote-audio
  loss instead of dropping the call just because the network is poor.
- Join, local publish, remote-audio arrival, and route-change timing is stored
  in the existing call participant metadata.

## Current Gaps

- Native video call path is still not implemented.
- Real-world QA still needs two physical devices.
- Automated builds cannot verify real-device speaker echo, receiver loudness,
  Bluetooth switching, or background audio. Two-device hands-on QA is still
  required before release rollout.
