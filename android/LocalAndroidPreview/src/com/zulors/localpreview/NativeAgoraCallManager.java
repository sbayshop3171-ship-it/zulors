package com.zulors.app;

import android.content.Context;
import android.media.AudioAttributes;
import android.os.Build;
import android.os.Handler;
import android.util.Log;

import org.json.JSONObject;

import io.agora.rtc2.ChannelMediaOptions;
import io.agora.rtc2.Constants;
import io.agora.rtc2.IRtcEngineEventHandler;
import io.agora.rtc2.RtcEngine;
import io.agora.rtc2.RtcEngineConfig;
import io.agora.rtc2.RtcEngineEx;
import io.agora.rtc2.audio.AdvancedAudioOptions;

public class NativeAgoraCallManager {
    private static final String ROUTE_EARPIECE = "earpiece";
    private static final String ROUTE_SPEAKER = "speaker";
    private static final String ROUTE_WIRED = "wired";
    private static final String ROUTE_BLUETOOTH = "bluetooth";
    private static final int DEFAULT_AUDIO_ROUTE = Constants.AUDIO_ROUTE_DEFAULT;
    private static final int EARPIECE_AUDIO_ROUTE = Constants.AUDIO_ROUTE_EARPIECE;
    private static final int SPEAKER_AUDIO_ROUTE = Constants.AUDIO_ROUTE_SPEAKERPHONE;
    private static final int WIRED_HEADSET_AUDIO_ROUTE = Constants.AUDIO_ROUTE_HEADSET;
    private static final int WIRED_HEADSET_NO_MIC_AUDIO_ROUTE = Constants.AUDIO_ROUTE_HEADSETNOMIC;
    private static final int BLUETOOTH_HFP_AUDIO_ROUTE = Constants.AUDIO_ROUTE_BLUETOOTH_DEVICE_HFP;
    private static final int BLUETOOTH_A2DP_AUDIO_ROUTE = Constants.AUDIO_ROUTE_BLUETOOTH_DEVICE_A2DP;
    private static final int VOICE_TIGHT_ROUTE_RECORDING_SIGNAL_VOLUME = 96;
    private static final int VOICE_SPEAKER_RECORDING_SIGNAL_VOLUME = 88;
    private static final int VOICE_TIGHT_ROUTE_PLAYBACK_SIGNAL_VOLUME = 100;
    private static final int VOICE_BLUETOOTH_PLAYBACK_SIGNAL_VOLUME = 100;
    private static final int VOICE_SPEAKER_PLAYBACK_SIGNAL_VOLUME = 112;
    private static final int AINS_BALANCED_MODE = 1;
    private static final int AINS_ULTRA_LOW_LATENCY_MODE = 2;
    private static final String DEFAULT_AUDIO_ENCODER_PROFILE = "speech_low_quality";
    private static final long REMOTE_AUDIO_FRESHNESS_MS = 12000L;

    public interface Listener {
        void onNativeCallEvent(String type, JSONObject payload);
    }

    private static final String TAG = "ZulorsAgoraNative";

    private final Context appContext;
    private final Handler mainHandler;
    private final Listener listener;
    private RtcEngineEx engine;
    private boolean joined = false;
    private boolean joining = false;
    private boolean muted = false;
    private boolean speakerEnabled = false;
    private boolean remoteAudioConnected = false;
    private boolean remoteAudioDecoding = false;
    private int routeApplyGeneration = 0;
    private String preferredAudioRouteName = ROUTE_EARPIECE;
    private String activeAppId = null;
    private String activeChannel = null;
    private String activeToken = null;
    private String activeAreaCode = null;
    private String activeExcludedArea = null;
    private String activeAudioEncoderProfile = DEFAULT_AUDIO_ENCODER_PROFILE;
    private int activeUid = 0;
    private int activeAudioRoute = DEFAULT_AUDIO_ROUTE;
    private int activeRequestedAudioBitrateKbps = 18;
    private int activeRequestedAudioBitrateFloorKbps = 16;
    private int activeRequestedAudioSampleRate = 16000;
    private int latestRoundTripTimeMs = 0;
    private int latestTxPacketLossRate = 0;
    private int latestRxPacketLossRate = 0;
    private int latestRemoteAudioLossRate = 0;
    private int latestJitterBufferDelayMs = 0;
    private int latestNetworkTransportDelayMs = 0;
    private int latestEndToEndDelayMs = 0;
    private int latestReceivedAudioBitrate = 0;
    private int latestSentAudioBitrate = 0;
    private int latestAudioDeviceDelayMs = 0;
    private int latestAudioPlayoutDelayMs = 0;
    private int latestAecEstimatedDelayMs = 0;
    private int consecutiveWeakQualitySamples = 0;
    private int consecutivePoorQualitySamples = 0;
    private long lastRemoteAudioActiveAtMs = 0L;
    private long lastRemoteAudioDecodeAtMs = 0L;
    private long lastRemoteAudioBitrateAtMs = 0L;

    private final IRtcEngineEventHandler rtcEventHandler = new IRtcEngineEventHandler() {
        @Override
        public void onError(int error) {
            dispatchEvent("error", makePayload(
                "error", error,
                "message", RtcEngine.getErrorDescription(Math.abs(error))
            ));
        }

        @Override
        public void onJoinChannelSuccess(String channel, int uid, int elapsed) {
            joining = false;
            joined = true;
            activeChannel = channel;
            activeUid = uid;
            applySpeakerRoute();
            dispatchState("connected");
        }

        @Override
        public void onLeaveChannel(RtcStats stats) {
            joining = false;
            joined = false;
            remoteAudioConnected = false;
            remoteAudioDecoding = false;
            resetQualityMetrics();
            dispatchState("disconnected");
            dispatchRemoteAudio(false);
        }

        @Override
        public void onUserJoined(int uid, int elapsed) {
            if (uid == activeUid) {
                muteSelfRemoteAudio(uid);
                return;
            }

            dispatchEvent("participant", makePayload("uid", uid));
        }

        @Override
        public void onUserOffline(int uid, int reason) {
            remoteAudioConnected = false;
            remoteAudioDecoding = false;
            dispatchRemoteAudio(false);
        }

        @Override
        public void onRemoteAudioStateChanged(int uid, int state, int reason, int elapsed) {
            if (uid == activeUid) {
                muteSelfRemoteAudio(uid);
                return;
            }

            boolean connected = state == Constants.REMOTE_AUDIO_STATE_STARTING
                || state == Constants.REMOTE_AUDIO_STATE_DECODING;

            remoteAudioDecoding = connected;

            if (connected) {
                noteRemoteAudioDecodeActivity();
            }
            else {
                updateRemoteAudioConnected();
            }

            dispatchRemoteAudio(isRemoteAudioLive());
        }

        @Override
        public void onConnectionStateChanged(int state, int reason) {
            if (state == Constants.CONNECTION_STATE_CONNECTED) {
                applySpeakerRoute();
                dispatchState("connected");
                return;
            }

            if (state == Constants.CONNECTION_STATE_CONNECTING) {
                dispatchState("connecting");
                return;
            }

            if (state == Constants.CONNECTION_STATE_RECONNECTING) {
                applySpeakerRoute();
                dispatchState("reconnecting");
                return;
            }

            if (state == Constants.CONNECTION_STATE_DISCONNECTED) {
                dispatchState("disconnected");
                return;
            }

            if (state == Constants.CONNECTION_STATE_FAILED) {
                dispatchState("failed");
            }
        }

        @Override
        public void onNetworkQuality(int uid, int txQuality, int rxQuality) {
            if (uid != 0 && uid != activeUid) {
                return;
            }

            int packetLossPercent = Math.max(latestRemoteAudioLossRate, Math.max(latestTxPacketLossRate, latestRxPacketLossRate));
            String rawNetworkQuality = classifyNetworkQuality(txQuality, rxQuality, packetLossPercent);
            String networkQuality = stabilizeNetworkQuality(rawNetworkQuality, isSevereMediaQuality(packetLossPercent));
            String issue = issueForNetworkQuality(networkQuality);
            updateRemoteAudioConnected();

            dispatchEvent("quality", makePayload(
                "network_quality", networkQuality,
                "issue", issue,
                "connection_state", joined ? "connected" : (joining ? "connecting" : "disconnected"),
                "round_trip_time_ms", latestRoundTripTimeMs,
                "jitter_ms", latestJitterBufferDelayMs,
                "packet_loss_percent", packetLossPercent,
                "available_outgoing_bitrate", latestSentAudioBitrate,
                "received_bitrate", latestReceivedAudioBitrate,
                "tx_quality", txQuality,
                "rx_quality", rxQuality,
                "network_transport_delay_ms", latestNetworkTransportDelayMs,
                "jitter_buffer_delay_ms", latestJitterBufferDelayMs,
                "end_to_end_delay_ms", latestEndToEndDelayMs,
                "audio_device_delay_ms", latestAudioDeviceDelayMs,
                "audio_playout_delay_ms", latestAudioPlayoutDelayMs,
                "aec_estimated_delay_ms", latestAecEstimatedDelayMs,
                "local_audio_published", joined,
                "remote_audio_playing", remoteAudioConnected,
                "remote_audio_live", isRemoteAudioLive(),
                "last_remote_audio_active_at_ms", lastRemoteAudioActiveAtMs,
                "last_remote_audio_active_at", isoTimestamp(lastRemoteAudioActiveAtMs),
                "route", mapRouteName(activeAudioRoute),
                "speaker", speakerEnabled,
                "speaker_enabled", speakerEnabled,
                "muted", muted,
                "agora_uid", activeUid,
                "agora_channel", activeChannel,
                "device_model", deviceModel()
            ));
        }

        @Override
        public void onRtcStats(RtcStats stats) {
            if (stats == null) {
                return;
            }

            latestRoundTripTimeMs = Math.max(0, Math.max(stats.gatewayRtt, stats.lastmileDelay));
            latestTxPacketLossRate = Math.max(0, stats.txPacketLossRate);
            latestRxPacketLossRate = Math.max(0, stats.rxPacketLossRate);
        }

        @Override
        public void onLocalAudioStats(LocalAudioStats stats) {
            if (stats == null) {
                return;
            }

            latestSentAudioBitrate = Math.max(0, stats.sentBitrate);
            latestTxPacketLossRate = Math.max(latestTxPacketLossRate, Math.max(0, stats.txPacketLossRate));
            latestAudioDeviceDelayMs = Math.max(0, stats.audioDeviceDelay);
            latestAudioPlayoutDelayMs = Math.max(0, stats.audioPlayoutDelay);
            latestAecEstimatedDelayMs = Math.max(0, stats.aecEstimatedDelay);
        }

        @Override
        public void onRemoteAudioStats(RemoteAudioStats stats) {
            if (stats == null) {
                return;
            }

            if (stats.uid == activeUid) {
                muteSelfRemoteAudio(stats.uid);
                return;
            }

            latestRemoteAudioLossRate = Math.max(0, stats.audioLossRate);
            latestJitterBufferDelayMs = Math.max(0, stats.jitterBufferDelay);
            latestNetworkTransportDelayMs = Math.max(0, stats.networkTransportDelay);
            latestEndToEndDelayMs = Math.max(0, stats.e2eDelay);
            latestReceivedAudioBitrate = Math.max(0, stats.receivedBitrate);
            noteRemoteAudioStatsActivity(latestReceivedAudioBitrate);
            dispatchRemoteAudio(isRemoteAudioLive());
        }

        @Override
        public void onAudioRouteChanged(int routing) {
            activeAudioRoute = routing;
            applyRouteAwareVoiceProcessing(routing);
            dispatchEvent("route", makePayload("route", mapRouteName(routing)));

            if (isRouteMismatch(routing)) {
                scheduleSpeakerRouteReapply();
            }
        }

        @Override
        public void onTokenPrivilegeWillExpire(String token) {
            dispatchEvent("token-expiring", makePayload());
        }

        @Override
        public void onRequestToken() {
            dispatchEvent("token-expired", makePayload());
        }
    };

    public NativeAgoraCallManager(Context context, Handler handler, Listener listener) {
        this.appContext = context.getApplicationContext();
        this.mainHandler = handler;
        this.listener = listener;
    }

    public boolean startCall(String sessionJson) {
        try {
            JSONObject session = new JSONObject(sessionJson == null ? "{}" : sessionJson);
            String appId = trimToNull(session.optString("app_id"));
            String channel = trimToNull(session.optString("channel"));

            if (appId == null || channel == null) {
                return false;
            }

            activeAppId = appId;
            activeChannel = channel;
            activeToken = trimToNull(session.optString("token"));
            activeAreaCode = normalizeAreaCode(session.optString("area_code"));
            activeExcludedArea = normalizeAreaCode(session.optString("excluded_area"));
            activeAudioEncoderProfile = normalizeAudioEncoderProfile(session.optString("audio_encoder_profile"));
            activeRequestedAudioBitrateKbps = normalizeRequestedAudioBitrate(session.optInt("audio_bitrate_kbps", 18));
            activeRequestedAudioBitrateFloorKbps = normalizeRequestedAudioBitrateFloor(
                session.optInt("audio_bitrate_floor_kbps", 16),
                activeRequestedAudioBitrateKbps
            );
            activeRequestedAudioSampleRate = normalizeRequestedAudioSampleRate(session.optInt("audio_sample_rate", 16000));
            activeUid = Math.max(0, session.optInt("uid", 0));
            preferredAudioRouteName = normalizeAudioRouteName(session.optString("audio_route_preset"));
            speakerEnabled = ROUTE_SPEAKER.equals(preferredAudioRouteName);
            remoteAudioConnected = false;
            remoteAudioDecoding = false;
            resetQualityMetrics();
            joining = true;
            joined = false;
            dispatchRemoteAudio(false);
            dispatchState("connecting");
            ensureEngine();
            configureEngineAudio();

            ChannelMediaOptions mediaOptions = new ChannelMediaOptions();
            mediaOptions.clientRoleType = Constants.CLIENT_ROLE_BROADCASTER;
            mediaOptions.autoSubscribeAudio = true;
            mediaOptions.autoSubscribeVideo = false;
            mediaOptions.publishMicrophoneTrack = true;
            mediaOptions.publishCustomAudioTrack = false;
            mediaOptions.publishMediaPlayerAudioTrack = false;
            mediaOptions.publishMixedAudioTrack = false;
            mediaOptions.publishScreenCaptureAudio = false;
            mediaOptions.enableAudioRecordingOrPlayout = true;
            mediaOptions.channelProfile = Constants.CHANNEL_PROFILE_COMMUNICATION;

            int result = engine.joinChannel(activeToken, activeChannel, activeUid, mediaOptions);

            if (result != Constants.ERR_OK) {
                joining = false;
                dispatchEvent("error", makePayload(
                    "error", result,
                    "message", RtcEngine.getErrorDescription(Math.abs(result))
                ));

                return false;
            }

            applyMuteState();
            applySpeakerRoute();

            return true;
        }
        catch (Throwable exception) {
            Log.w(TAG, "Unable to start native Agora call.", exception);
            dispatchEvent("error", makePayload(
                "message", "Unable to start native Agora call."
            ));

            return false;
        }
    }

    public void endCall() {
        try {
            if (engine != null) {
                engine.leaveChannel();
            }
        }
        catch (Throwable exception) {
            Log.w(TAG, "Unable to leave native Agora channel cleanly.", exception);
        }
        finally {
            joining = false;
            joined = false;
            remoteAudioConnected = false;
            remoteAudioDecoding = false;
            resetQualityMetrics();
            dispatchRemoteAudio(false);
            dispatchState("disconnected");
        }
    }

    public void release() {
        endCall();

        try {
            RtcEngine.destroy();
        }
        catch (Throwable exception) {
            Log.w(TAG, "Unable to destroy Agora engine cleanly.", exception);
        }

        engine = null;
        activeAppId = null;
        activeChannel = null;
        activeToken = null;
        activeAreaCode = null;
        activeExcludedArea = null;
        activeUid = 0;
        activeAudioRoute = DEFAULT_AUDIO_ROUTE;
        preferredAudioRouteName = ROUTE_EARPIECE;
        remoteAudioDecoding = false;
        resetQualityMetrics();
    }

    public void setMuted(boolean muted) {
        this.muted = muted;
        applyMuteState();
    }

    public void setSpeakerEnabled(boolean speakerEnabled) {
        this.speakerEnabled = speakerEnabled;

        if (speakerEnabled) {
            preferredAudioRouteName = ROUTE_SPEAKER;
        }
        else if (ROUTE_SPEAKER.equals(preferredAudioRouteName)) {
            preferredAudioRouteName = ROUTE_EARPIECE;
        }

        applySpeakerRoute();
    }

    public void setAudioRoute(String routeName) {
        preferredAudioRouteName = normalizeAudioRouteName(routeName);
        speakerEnabled = ROUTE_SPEAKER.equals(preferredAudioRouteName);
        applySpeakerRoute();
    }

    public void updateToken(String token) {
        activeToken = trimToNull(token);

        try {
            if (engine != null && activeToken != null) {
                engine.renewToken(activeToken);
            }
        }
        catch (Throwable exception) {
            dispatchEvent("error", makePayload("message", "Unable to renew native call token."));
        }
    }

    public boolean refreshState() {
        applyMuteState();
        applySpeakerRoute();
        updateRemoteAudioConnected();
        dispatchRemoteAudio(isRemoteAudioLive());

        if (joined) {
            dispatchState("connected");
        }
        else if (joining) {
            dispatchState("connecting");
        }

        return true;
    }

    private void ensureEngine() throws Exception {
        if (engine != null) {
            return;
        }

        RtcEngineConfig config = new RtcEngineConfig();
        config.mContext = appContext;
        config.mAppId = activeAppId;
        config.mEventHandler = rtcEventHandler;
        config.mChannelProfile = Constants.CHANNEL_PROFILE_COMMUNICATION;
        config.mAudioScenario = Constants.AudioScenario.getValue(Constants.AudioScenario.DEFAULT);
        config.mAreaCode = resolveAreaCode(activeAreaCode, activeExcludedArea);
        engine = (RtcEngineEx) RtcEngine.create(config);
        loadAudioExtensions();
    }

    private void configureEngineAudio() {
        if (engine == null) {
            return;
        }

        engine.enableAudio();
        engine.enableLocalAudio(true);
        engine.setChannelProfile(Constants.CHANNEL_PROFILE_COMMUNICATION);
        engine.setClientRole(Constants.CLIENT_ROLE_BROADCASTER);
        setDefaultAudioRouteToEarpiece();
        engine.setEnableSpeakerphone(isSpeakerRoute(resolvePreferredAudioRoute()));
        engine.setAudioProfile(
            resolveAgoraAudioProfile(),
            Constants.AudioScenario.getValue(Constants.AudioScenario.DEFAULT)
        );
        engine.setAudioScenario(Constants.AudioScenario.getValue(Constants.AudioScenario.DEFAULT));
        engine.setupAudioAttributes(new AudioAttributes.Builder()
            .setUsage(AudioAttributes.USAGE_VOICE_COMMUNICATION)
            .setContentType(AudioAttributes.CONTENT_TYPE_SPEECH)
            .build());
        engine.setHighQualityAudioParameters(false, false, false);
        engine.setAdvancedAudioOptions(new AdvancedAudioOptions(
            AdvancedAudioOptions.AudioProcessingChannelsEnum.AGORA_AUDIO_MONO_PROCESSING
        ));
        engine.enableInEarMonitoring(false);
        engine.enableExternalAudioSourceLocalPlayback(false);
        applyCallSignalVolume(resolvePreferredAudioRoute());
        applyAinsMode(resolvePreferredAudioRoute());
        applyRouteAwareVoiceProcessing(resolvePreferredAudioRoute());
    }

    private void applyMuteState() {
        try {
            if (engine != null) {
                engine.muteLocalAudioStream(muted);
            }
        }
        catch (Throwable exception) {
            Log.w(TAG, "Unable to update local mute state.", exception);
        }
    }

    private void applySpeakerRoute() {
        try {
            if (engine == null) {
                return;
            }

            int preferredRoute = resolvePreferredAudioRoute();
            boolean speakerRoute = isSpeakerRoute(preferredRoute);

            engine.setEnableSpeakerphone(speakerRoute);

            try {
                engine.setRouteInCommunicationMode(preferredRoute);
            }
            catch (Throwable routeException) {
                engine.setEnableSpeakerphone(speakerRoute);
            }

            engine.setEnableSpeakerphone(speakerRoute);
            activeAudioRoute = preferredRoute;
            applyRouteAwareVoiceProcessing(preferredRoute);
            dispatchEvent("route", makePayload("route", mapRouteName(preferredRoute)));
            scheduleSpeakerRouteReapply();
        }
        catch (Throwable exception) {
            Log.w(TAG, "Unable to update native speaker route.", exception);
        }
    }

    private void scheduleSpeakerRouteReapply() {
        if (mainHandler == null || engine == null) {
            return;
        }

        final int generation = ++routeApplyGeneration;

        mainHandler.postDelayed(new Runnable() {
            @Override
            public void run() {
                if (generation != routeApplyGeneration || engine == null || (!joined && !joining)) {
                    return;
                }

                try {
                    int preferredRoute = resolvePreferredAudioRoute();
                    boolean speakerRoute = isSpeakerRoute(preferredRoute);
                    engine.setEnableSpeakerphone(speakerRoute);
                    engine.setRouteInCommunicationMode(preferredRoute);
                    engine.setEnableSpeakerphone(speakerRoute);
                    activeAudioRoute = preferredRoute;
                    applyRouteAwareVoiceProcessing(preferredRoute);
                    dispatchEvent("route", makePayload("route", mapRouteName(preferredRoute)));
                }
                catch (Throwable exception) {
                    Log.w(TAG, "Unable to reapply native speaker route.", exception);
                }
            }
        }, 240);
    }

    private boolean isRouteMismatch(int routing) {
        int preferredRoute = resolvePreferredAudioRoute();

        if (preferredRoute == SPEAKER_AUDIO_ROUTE) {
            return routing != SPEAKER_AUDIO_ROUTE;
        }

        if (preferredRoute == WIRED_HEADSET_AUDIO_ROUTE) {
            return !isWiredRoute(routing);
        }

        if (preferredRoute == BLUETOOTH_HFP_AUDIO_ROUTE) {
            return !isBluetoothRoute(routing);
        }

        return routing == SPEAKER_AUDIO_ROUTE;
    }

    private void muteSelfRemoteAudio(int uid) {
        try {
            if (engine != null) {
                engine.muteRemoteAudioStream(uid, true);
                engine.adjustUserPlaybackSignalVolume(uid, 0);
            }
        }
        catch (Throwable exception) {
            Log.w(TAG, "Unable to block self remote audio.", exception);
        }
    }

    private void setDefaultAudioRouteToEarpiece() {
        if (engine == null) {
            return;
        }

        try {
            engine.getClass()
                .getMethod("setDefaultAudioRoutetoSpeakerphone", boolean.class)
                .invoke(engine, false);
        }
        catch (Throwable primaryException) {
            try {
                engine.getClass()
                    .getMethod("setDefaultAudioRouteToSpeakerphone", boolean.class)
                    .invoke(engine, false);
            }
            catch (Throwable ignored) {
                Log.w(TAG, "Unable to force the default audio route to the earpiece.", primaryException);
            }
        }
    }

    private void loadAudioExtensions() {
        if (engine == null) {
            return;
        }

        try {
            engine.loadExtensionProvider("ai_echo_cancellation_extension");
        }
        catch (Throwable exception) {
            Log.w(TAG, "Unable to load Agora AI echo cancellation extension.", exception);
        }

        try {
            engine.loadExtensionProvider("ai_noise_suppression_extension");
        }
        catch (Throwable exception) {
            Log.w(TAG, "Unable to load Agora AI noise suppression extension.", exception);
        }
    }

    private void applyRouteAwareVoiceProcessing(int routing) {
        if (engine == null) {
            return;
        }

        // Keep the communication chain in voice mode so hardware/software AEC stays active.
        setIntegerAudioParameter("che.audio.aec.split_srate_for_48k", 16000);
        setBooleanAudioParameter("che.audio.aec.enable", true);
        setBooleanAudioParameter("che.audio.aec.enable_state", true);
        boolean speakerRoute = isSpeakerRoute(routing);
        boolean tightVoiceRoute = isTightVoiceRoute(routing);
        boolean automaticGainAllowed = true;

        setBooleanAudioParameter("che.audio.ans.enable", true);
        setBooleanAudioParameter("che.audio.agc.enable", automaticGainAllowed);
        setBooleanAudioParameter("che.audio.agc.enable_state", automaticGainAllowed);
        setBooleanAudioParameter("che.audio.enable.ns", true);
        setBooleanAudioParameter("che.audio.enable.agc", automaticGainAllowed);
        setIntegerAudioParameter("che.audio.agc.targetlevelBov", speakerRoute ? 11 : 9);
        setIntegerAudioParameter("che.audio.agc.compressionGaindB", speakerRoute ? 0 : 2);
        setBooleanAudioParameter("che.audio.agc.limiter", true);
        setIntegerAudioParameter("che.audio.input_sample_rate", 16000);
        setBooleanAudioParameter("che.audio.sf.enabled", true);
        setIntegerAudioParameter("che.audio.sf.stftType", 6);
        setIntegerAudioParameter("che.audio.sf.ainlpLowLatencyFlag", 1);
        setIntegerAudioParameter("che.audio.sf.ainsLowLatencyFlag", 1);
        setIntegerAudioParameter("che.audio.sf.procChainMode", 1);
        setIntegerAudioParameter("che.audio.sf.nlpDynamicMode", 1);
        setIntegerAudioParameter("che.audio.sf.nlpAlgRoute", tightVoiceRoute ? 0 : 1);
        setIntegerAudioParameter("che.audio.sf.ainlpModelPref", speakerRoute ? 10 : 8);
        setIntegerAudioParameter("che.audio.sf.nsngAlgRoute", 12);
        setIntegerAudioParameter("che.audio.sf.ainsModelPref", speakerRoute ? 10 : 8);
        setIntegerAudioParameter("che.audio.sf.nsngPredefAgg", speakerRoute ? 11 : 10);
        applyCallSignalVolume(routing);
        applyAinsMode(routing);
    }

    private void applyAinsMode(int routing) {
        if (engine == null) {
            return;
        }

        try {
            engine.setAINSMode(
                true,
                isBluetoothRoute(routing) ? AINS_ULTRA_LOW_LATENCY_MODE : AINS_BALANCED_MODE
            );
        }
        catch (Throwable exception) {
            Log.w(TAG, "Unable to apply Agora AINS mode.", exception);
        }
    }

    private void applyCallSignalVolume(int routing) {
        if (engine == null) {
            return;
        }

        int recordingSignalVolume = resolveRecordingSignalVolume(routing);
        int playbackSignalVolume = resolvePlaybackSignalVolume(routing);

        try {
            engine.adjustRecordingSignalVolume(recordingSignalVolume);
            engine.adjustPlaybackSignalVolume(playbackSignalVolume);
            applyLegacyPlayoutSignalVolume(playbackSignalVolume);
        }
        catch (Throwable exception) {
            Log.w(TAG, "Unable to apply Agora signal volume.", exception);
        }
    }

    private void applyLegacyPlayoutSignalVolume(int playbackSignalVolume) {
        try {
            engine.getClass()
                .getMethod("adjustPlayoutSignalVolume", int.class)
                .invoke(engine, playbackSignalVolume);
        }
        catch (NoSuchMethodException ignored) {
            // Agora RTC 4.x exposes adjustPlaybackSignalVolume; older docs mention playout.
        }
        catch (Throwable exception) {
            Log.w(TAG, "Unable to apply legacy Agora playout volume.", exception);
        }
    }

    private int resolvePlaybackSignalVolume(int routing) {
        if (isSpeakerRoute(routing)) {
            return VOICE_SPEAKER_PLAYBACK_SIGNAL_VOLUME;
        }

        if (isBluetoothRoute(routing)) {
            return VOICE_BLUETOOTH_PLAYBACK_SIGNAL_VOLUME;
        }

        return VOICE_TIGHT_ROUTE_PLAYBACK_SIGNAL_VOLUME;
    }

    private int resolveRecordingSignalVolume(int routing) {
        return isSpeakerRoute(routing)
            ? VOICE_SPEAKER_RECORDING_SIGNAL_VOLUME
            : VOICE_TIGHT_ROUTE_RECORDING_SIGNAL_VOLUME;
    }

    private boolean isSpeakerRoute(int routing) {
        return routing == SPEAKER_AUDIO_ROUTE;
    }

    private void setBooleanAudioParameter(String key, boolean value) {
        setAudioParameter(key, value ? "true" : "false");
    }

    private void setIntegerAudioParameter(String key, int value) {
        setAudioParameter(key, String.valueOf(value));
    }

    private void setAudioParameter(String key, String rawValue) {
        if (engine == null || key == null || key.trim().isEmpty() || rawValue == null) {
            return;
        }

        try {
            engine.setParameters("{\"" + key + "\":" + rawValue + "}");
        }
        catch (Throwable exception) {
            Log.w(TAG, "Unable to apply Agora audio parameter: " + key, exception);
        }
    }

    private int resolveAgoraAudioProfile() {
        String profile = normalizeAudioEncoderProfile(activeAudioEncoderProfile);

        if ("music_high_quality_stereo".equals(profile) || "high_quality_stereo".equals(profile)) {
            return Constants.AudioProfile.getValue(Constants.AudioProfile.MUSIC_HIGH_QUALITY_STEREO);
        }

        if ("music_high_quality".equals(profile) || "high_quality".equals(profile)) {
            return Constants.AudioProfile.getValue(Constants.AudioProfile.MUSIC_HIGH_QUALITY);
        }

        if ("music_standard_stereo".equals(profile) || "standard_stereo".equals(profile)) {
            return Constants.AudioProfile.getValue(Constants.AudioProfile.MUSIC_STANDARD_STEREO);
        }

        if ("music_standard".equals(profile)) {
            return Constants.AudioProfile.getValue(Constants.AudioProfile.MUSIC_STANDARD);
        }

        if (activeRequestedAudioSampleRate >= 48000 && activeRequestedAudioBitrateKbps >= 96) {
            return Constants.AudioProfile.getValue(Constants.AudioProfile.MUSIC_HIGH_QUALITY);
        }

        if (activeRequestedAudioSampleRate >= 48000 && activeRequestedAudioBitrateKbps >= 64) {
            return Constants.AudioProfile.getValue(Constants.AudioProfile.MUSIC_STANDARD);
        }

        return Constants.AudioProfile.getValue(Constants.AudioProfile.SPEECH_STANDARD);
    }

    private String normalizeAudioEncoderProfile(String profile) {
        String normalizedProfile = trimToNull(profile);

        if (normalizedProfile == null) {
            return DEFAULT_AUDIO_ENCODER_PROFILE;
        }

        normalizedProfile = normalizedProfile.toLowerCase();

        if (
            "speech_low_quality".equals(normalizedProfile)
            || "speech_standard".equals(normalizedProfile)
            || "music_standard".equals(normalizedProfile)
            || "music_standard_stereo".equals(normalizedProfile)
            || "standard_stereo".equals(normalizedProfile)
            || "music_high_quality".equals(normalizedProfile)
            || "high_quality".equals(normalizedProfile)
            || "music_high_quality_stereo".equals(normalizedProfile)
            || "high_quality_stereo".equals(normalizedProfile)
        ) {
            return normalizedProfile;
        }

        return DEFAULT_AUDIO_ENCODER_PROFILE;
    }

    private int normalizeRequestedAudioBitrate(int bitrateKbps) {
        return Math.max(12, Math.min(128, bitrateKbps));
    }

    private int normalizeRequestedAudioBitrateFloor(int bitrateFloorKbps, int bitrateKbps) {
        return Math.max(12, Math.min(Math.max(12, bitrateKbps), bitrateFloorKbps));
    }

    private int normalizeRequestedAudioSampleRate(int sampleRate) {
        if (sampleRate == 16000 || sampleRate == 32000 || sampleRate == 48000) {
            return sampleRate;
        }

        return 32000;
    }

    private int resolvePreferredAudioRoute() {
        if (ROUTE_SPEAKER.equals(preferredAudioRouteName)) {
            return SPEAKER_AUDIO_ROUTE;
        }

        if (ROUTE_WIRED.equals(preferredAudioRouteName)) {
            return WIRED_HEADSET_AUDIO_ROUTE;
        }

        if (ROUTE_BLUETOOTH.equals(preferredAudioRouteName)) {
            return BLUETOOTH_HFP_AUDIO_ROUTE;
        }

        return EARPIECE_AUDIO_ROUTE;
    }

    private boolean isTightVoiceRoute(int routing) {
        return routing == EARPIECE_AUDIO_ROUTE
            || isWiredRoute(routing)
            || isBluetoothRoute(routing);
    }

    private boolean isWiredRoute(int routing) {
        return routing == WIRED_HEADSET_AUDIO_ROUTE
            || routing == WIRED_HEADSET_NO_MIC_AUDIO_ROUTE
            || routing == Constants.AUDIO_ROUTE_USBDEVICE;
    }

    private boolean isBluetoothRoute(int routing) {
        return routing == BLUETOOTH_HFP_AUDIO_ROUTE
            || routing == BLUETOOTH_A2DP_AUDIO_ROUTE;
    }

    private String normalizeAudioRouteName(String routeName) {
        String normalizedRoute = trimToNull(routeName);

        if (normalizedRoute == null) {
            return ROUTE_EARPIECE;
        }

        normalizedRoute = normalizedRoute.toLowerCase();

        if (
            ROUTE_SPEAKER.equals(normalizedRoute)
            || ROUTE_WIRED.equals(normalizedRoute)
            || ROUTE_BLUETOOTH.equals(normalizedRoute)
            || ROUTE_EARPIECE.equals(normalizedRoute)
        ) {
            return normalizedRoute;
        }

        return ROUTE_EARPIECE;
    }

    private void dispatchState(String state) {
        dispatchEvent("state", makePayload("state", state));
    }

    private void dispatchRemoteAudio(boolean connected) {
        dispatchEvent("remote-audio", makePayload(
            "connected", connected,
            "remote_audio_live", connected,
            "last_remote_audio_active_at_ms", lastRemoteAudioActiveAtMs,
            "last_remote_audio_active_at", isoTimestamp(lastRemoteAudioActiveAtMs),
            "reason", connected ? "media_flow_live" : "media_flow_stale"
        ));
    }

    private void dispatchEvent(final String type, final JSONObject payload) {
        if (listener == null) {
            return;
        }

        Runnable callback = new Runnable() {
            @Override
            public void run() {
                try {
                    listener.onNativeCallEvent(type, payload);
                }
                catch (Throwable exception) {
                    Log.w(TAG, "Unable to dispatch native call event.", exception);
                }
            }
        };

        if (mainHandler != null) {
            mainHandler.post(callback);
            return;
        }

        callback.run();
    }

    private JSONObject makePayload() {
        return new JSONObject();
    }

    private JSONObject makePayload(Object... values) {
        JSONObject payload = new JSONObject();

        if (values == null) {
            return payload;
        }

        for (int index = 0; index + 1 < values.length; index += 2) {
            Object key = values[index];
            Object value = values[index + 1];

            if (!(key instanceof String)) {
                continue;
            }

            try {
                payload.put((String) key, value);
            }
            catch (Throwable ignored) {}
        }

        return payload;
    }

    private String mapRouteName(int route) {
        if (route == Constants.AUDIO_ROUTE_SPEAKERPHONE) {
            return "speaker";
        }

        if (isWiredRoute(route)) {
            return "wired";
        }

        if (isBluetoothRoute(route)) {
            return "bluetooth";
        }

        if (route == Constants.AUDIO_ROUTE_EARPIECE) {
            return "earpiece";
        }

        return "unknown";
    }

    private String deviceModel() {
        String manufacturer = Build.MANUFACTURER == null ? "" : Build.MANUFACTURER.trim();
        String model = Build.MODEL == null ? "" : Build.MODEL.trim();
        String device = (manufacturer + " " + model).trim();

        return device.isEmpty() ? "Android" : device;
    }

    private void resetQualityMetrics() {
        latestRoundTripTimeMs = 0;
        latestTxPacketLossRate = 0;
        latestRxPacketLossRate = 0;
        latestRemoteAudioLossRate = 0;
        latestJitterBufferDelayMs = 0;
        latestNetworkTransportDelayMs = 0;
        latestEndToEndDelayMs = 0;
        latestReceivedAudioBitrate = 0;
        latestSentAudioBitrate = 0;
        latestAudioDeviceDelayMs = 0;
        latestAudioPlayoutDelayMs = 0;
        latestAecEstimatedDelayMs = 0;
        consecutiveWeakQualitySamples = 0;
        consecutivePoorQualitySamples = 0;
        lastRemoteAudioActiveAtMs = 0L;
        lastRemoteAudioDecodeAtMs = 0L;
        lastRemoteAudioBitrateAtMs = 0L;
    }

    private void noteRemoteAudioDecodeActivity() {
        long nowMs = System.currentTimeMillis();

        lastRemoteAudioDecodeAtMs = nowMs;
        lastRemoteAudioActiveAtMs = Math.max(lastRemoteAudioActiveAtMs, nowMs);
        updateRemoteAudioConnected();
    }

    private void noteRemoteAudioStatsActivity(int receivedBitrate) {
        long nowMs = System.currentTimeMillis();

        if (receivedBitrate > 0) {
            lastRemoteAudioBitrateAtMs = nowMs;
            lastRemoteAudioActiveAtMs = Math.max(lastRemoteAudioActiveAtMs, nowMs);
        }

        updateRemoteAudioConnected();
    }

    private void updateRemoteAudioConnected() {
        remoteAudioConnected = isRemoteAudioLive();
    }

    private boolean isRemoteAudioLive() {
        long nowMs = System.currentTimeMillis();
        long freshestActivityAtMs = Math.max(lastRemoteAudioActiveAtMs, Math.max(lastRemoteAudioDecodeAtMs, lastRemoteAudioBitrateAtMs));
        boolean freshActivity = freshestActivityAtMs > 0L && (nowMs - freshestActivityAtMs) <= REMOTE_AUDIO_FRESHNESS_MS;
        boolean activeDecode = remoteAudioDecoding && (lastRemoteAudioDecodeAtMs > 0L) && (nowMs - lastRemoteAudioDecodeAtMs) <= REMOTE_AUDIO_FRESHNESS_MS;
        boolean activeBitrate = latestReceivedAudioBitrate > 0 && lastRemoteAudioBitrateAtMs > 0L && (nowMs - lastRemoteAudioBitrateAtMs) <= REMOTE_AUDIO_FRESHNESS_MS;

        return freshActivity && (activeDecode || activeBitrate);
    }

    private String isoTimestamp(long timestampMs) {
        if (timestampMs <= 0L) {
            return null;
        }

        java.text.SimpleDateFormat format = new java.text.SimpleDateFormat("yyyy-MM-dd'T'HH:mm:ssXXX", java.util.Locale.US);
        format.setTimeZone(java.util.TimeZone.getTimeZone("UTC"));

        return format.format(new java.util.Date(timestampMs));
    }

    private String classifyNetworkQuality(int txQuality, int rxQuality, int packetLossPercent) {
        int quality = Math.max(txQuality, rxQuality);
        boolean hasMediaStats = latestReceivedAudioBitrate > 0
            || latestSentAudioBitrate > 0
            || latestJitterBufferDelayMs > 0
            || latestEndToEndDelayMs > 0
            || packetLossPercent > 0;

        if (quality <= 0 || quality == Constants.QUALITY_UNKNOWN) {
            if (!hasMediaStats) {
                return "unknown";
            }

            if (isPoorMediaQuality(packetLossPercent)) {
                return "poor";
            }

            if (isWeakMediaQuality(packetLossPercent)) {
                return "weak";
            }

            return "good";
        }

        if (quality >= Constants.QUALITY_DOWN) {
            return "reconnecting";
        }

        if (isPoorMediaQuality(packetLossPercent) || quality >= Constants.QUALITY_BAD) {
            return "poor";
        }

        if (isWeakMediaQuality(packetLossPercent) || quality >= Constants.QUALITY_POOR) {
            return "weak";
        }

        return "good";
    }

    private boolean isSevereMediaQuality(int packetLossPercent) {
        return packetLossPercent >= 50
            || latestEndToEndDelayMs >= 1800
            || latestJitterBufferDelayMs >= 900;
    }

    private boolean isPoorMediaQuality(int packetLossPercent) {
        return packetLossPercent >= 25
            || latestEndToEndDelayMs >= 1400
            || latestJitterBufferDelayMs >= 420
            || latestRoundTripTimeMs >= 1400;
    }

    private boolean isWeakMediaQuality(int packetLossPercent) {
        return packetLossPercent >= 10
            || latestEndToEndDelayMs >= 800
            || latestJitterBufferDelayMs >= 220
            || latestRoundTripTimeMs >= 900;
    }

    private String stabilizeNetworkQuality(String networkQuality, boolean severe) {
        if ("reconnecting".equals(networkQuality) || severe) {
            consecutiveWeakQualitySamples = 0;
            consecutivePoorQualitySamples = 0;
            return "reconnecting".equals(networkQuality) ? "reconnecting" : "poor";
        }

        if ("poor".equals(networkQuality)) {
            consecutivePoorQualitySamples += 1;
            consecutiveWeakQualitySamples += 1;

            return consecutivePoorQualitySamples >= 2 ? "poor" : "weak";
        }

        if ("weak".equals(networkQuality)) {
            consecutivePoorQualitySamples = 0;
            consecutiveWeakQualitySamples += 1;

            return consecutiveWeakQualitySamples >= 2 ? "weak" : "good";
        }

        consecutiveWeakQualitySamples = 0;
        consecutivePoorQualitySamples = 0;

        return "unknown".equals(networkQuality) ? "unknown" : "good";
    }

    private String issueForNetworkQuality(String networkQuality) {
        if ("reconnecting".equals(networkQuality)) {
            return "Reconnecting";
        }

        if ("poor".equals(networkQuality)) {
            return "Poor network";
        }

        if ("weak".equals(networkQuality)) {
            return "Weak network";
        }

        return null;
    }

    private String trimToNull(String value) {
        if (value == null) {
            return null;
        }

        String normalized = value.trim();

        return normalized.isEmpty() ? null : normalized;
    }

    private String normalizeAreaCode(String value) {
        String normalized = trimToNull(value);

        if (normalized == null) {
            return null;
        }

        return normalized.replace('-', '_').replace(' ', '_').toUpperCase();
    }

    private int resolveAreaCode(String areaCode, String excludedArea) {
        if ("GLOBAL".equals(areaCode) && "CHINA".equals(excludedArea)) {
            return RtcEngineConfig.AreaCode.AREA_CODE_AS
                | RtcEngineConfig.AreaCode.AREA_CODE_EU
                | RtcEngineConfig.AreaCode.AREA_CODE_NA
                | RtcEngineConfig.AreaCode.AREA_CODE_JP
                | RtcEngineConfig.AreaCode.AREA_CODE_IN;
        }

        if ("CHINA".equals(areaCode)) {
            return RtcEngineConfig.AreaCode.AREA_CODE_CN;
        }

        if ("NORTH_AMERICA".equals(areaCode)) {
            return RtcEngineConfig.AreaCode.AREA_CODE_NA;
        }

        if ("EUROPE".equals(areaCode)) {
            return RtcEngineConfig.AreaCode.AREA_CODE_EU;
        }

        if ("ASIA".equals(areaCode)) {
            return RtcEngineConfig.AreaCode.AREA_CODE_AS;
        }

        if ("JAPAN".equals(areaCode)) {
            return RtcEngineConfig.AreaCode.AREA_CODE_JP;
        }

        if ("INDIA".equals(areaCode)) {
            return RtcEngineConfig.AreaCode.AREA_CODE_IN;
        }

        return RtcEngineConfig.AreaCode.AREA_CODE_GLOB;
    }
}
