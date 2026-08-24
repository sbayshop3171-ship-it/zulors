# Zulors Call Quality QA

Use this checklist before calling the audio-call fixes complete. The current
focus is audio quality and recovery. Native video calling is not enabled yet,
so do not mark "professional video call" as complete until a real video media
path exists.

## Automated Checks

Run these before every APK handoff:

```sh
cd Source
npm run test:ws
npm run build:vite
cd ..
APP_MODE=production ARTIFACT_TYPE=apk bash LocalAndroidPreview/build-local.sh
```

Expected result:

- Node tests pass.
- Vite production build completes.
- A fresh APK is created in `LocalAndroidPreview/build/latest/`.

## Device Matrix

Minimum manual coverage:

- Device A on stable Wi-Fi
- Device B on a second Wi-Fi or mobile data
- One test with earpiece
- One test with speaker enabled
- One test with wired or Bluetooth audio if available

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

Pass:

- The first spoken words arrive consistently.
- Delay may grow on weak network, but audio should still land without the call
  collapsing quickly.

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
- A screen recording or second-phone video if possible

## Current Fix Coverage

These fixes are now in place:

- Native Agora voice calls use the default communication audio scenario instead
  of the game-streaming scenario.
- Native speaker playback gain is reduced to lower echo risk.
- Reconnect and degraded-network hangups are now tied to actual remote-audio
  loss instead of dropping the call just because the network is poor.

## Current Gaps

- Native video call path is still not implemented.
- Real-world QA still needs two physical devices.
- Git-tracked deployment currently covers the `Source` app, but the local
  Android wrapper folder is outside the tracked repository.
