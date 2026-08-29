# Native iOS Audio Calling Implementation Plan

## Goal

Add a native Swift calling layer around the existing Agora call contract so iOS supports reliable foreground/background audio, lock-screen controls, Bluetooth and wired route changes, interruption recovery, and system incoming-call UX.

## Phase 1 — Native call foundation

1. Create an iOS target/module with a `CallKit`-backed `CXProvider`, `CXCallController`, and a single `NativeAgoraCallManager` owner for the Agora engine.
2. Keep call identity, token, channel, participant UUID, mute state, and route state in one native call session model. The web/PWA bridge must not create a second audio engine.
3. Add `NSMicrophoneUsageDescription` and verify microphone permission before requesting the Agora token or starting the engine.
4. Configure Agora’s native voice profile and enable AEC, ANS, and AGC through the iOS SDK APIs. Record the SDK version and audio profile in diagnostics.

## Phase 2 — AVAudioSession and routing

1. Configure `AVAudioSession` with `.playAndRecord` and `.voiceChat` mode, then activate it immediately before joining/publishing audio.
2. Use `.allowBluetooth` and `.allowBluetoothA2DP` where supported; preserve the system-selected route for Bluetooth HFP and wired headsets.
3. Route speaker/receiver through `overrideOutputAudioPort(.speaker)` only for an explicit user speaker action. Clear the override when returning to receiver or when a headset/Bluetooth route appears.
4. Observe `AVAudioSession.routeChangeNotification`, `interruptionNotification`, and media-services-reset notifications. Re-apply the correct route and resume the Agora track after a transient interruption.
5. Never force the receiver while a wired or Bluetooth output is active. Expose the resolved port type to the UI for diagnostics.

## Phase 3 — CallKit and lock-screen behavior

1. Report incoming calls through `CXProvider.reportNewIncomingCall` using a stable UUID and the remote display name.
2. Implement `CXStartCallAction`, `CXAnswerCallAction`, `CXEndCallAction`, and `CXSetMutedCallAction`; make each action idempotent and acknowledge it only after the Agora/native state transition succeeds.
3. Set `providerConfiguration` to audio-only capabilities and configure the maximum call groups to one for the initial release.
4. On answer/start, activate the `AVAudioSession` from `provider(_:perform:)`; on end, leave Agora, deactivate the session with notification options, and release observers.
5. Use CallKit’s mute action as the source of truth and mirror it to the existing server `mute` signal. Do not toggle both raw audio tracks and the Agora track independently.

## Phase 4 — Background execution and PushKit

1. Add only the required `UIBackgroundModes`: `audio` for an active call and `voip` only if PushKit is used for incoming call delivery.
2. Register `PKPushRegistry` with the VoIP push type, authenticate the payload, report the CallKit incoming call promptly, and never use VoIP pushes for non-call events.
3. Keep the audio session active while a call is connected and release it immediately after hang-up. Validate behavior when the app is backgrounded, suspended, force-quit, and the screen is locked.
4. Ensure the server marks unanswered/expired calls stale so a missed push cannot leave an orphaned call record.

## Phase 5 — Web/native bridge and lifecycle

1. Define a narrow bridge API: `start`, `answer`, `decline`, `end`, `setMuted`, `setSpeakerEnabled`, `getRoute`, and `resumeAudio`.
2. Use the existing call UUID and signaling endpoints; do not duplicate offer/answer or mute signaling in both Swift and JavaScript.
3. Emit native events for `connected`, `reconnecting`, `remoteMuted`, `routeChanged`, `interrupted`, `ended`, and `error`.
4. Add bounded reconnect handling for Wi-Fi/data transitions and a final cleanup path for every Agora, CallKit, PushKit, and `AVAudioSession` observer.

## Required entitlements and review items

- Microphone usage description in `Info.plist`.
- Background Modes: Audio, and Voice over IP only when PushKit is implemented.
- Push Notifications capability if VoIP notifications are used.
- App Store privacy description and CallKit behavior review.
- No background recording outside an active user-visible call.

## Acceptance matrix

- Incoming call while foreground, background, and screen locked.
- Answer/end from the app and from the lock screen.
- Receiver/speaker toggle before and during a call.
- Bluetooth HFP connect/disconnect and wired headset plug/unplug mid-call.
- Mute/unmute from the app, lock screen, and remote participant.
- Phone call/Siri/alarm interruption and media-services reset.
- Wi-Fi-to-cellular transition with Agora and WebSocket recovery.
- No echo with speaker enabled, no audio after hang-up, and no orphan CallKit or server call state.

## Rollout

Ship behind an iOS feature flag. Start with internal TestFlight builds on at least two physical iPhones and two iOS versions, collect route/interruption/reconnect telemetry, then enable for a small production cohort before general release.
