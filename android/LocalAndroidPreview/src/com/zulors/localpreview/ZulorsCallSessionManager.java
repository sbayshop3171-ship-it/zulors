package com.zulors.app;

import android.Manifest;
import android.content.Context;
import android.content.Intent;
import android.content.SharedPreferences;
import android.content.pm.PackageManager;
import android.net.Uri;
import android.os.Build;
import android.os.Bundle;
import android.os.Handler;
import android.os.Looper;
import android.util.Log;
import android.webkit.CookieManager;

import org.json.JSONObject;

import java.io.BufferedReader;
import java.io.InputStream;
import java.io.InputStreamReader;
import java.io.OutputStream;
import java.net.HttpURLConnection;
import java.net.URL;
import java.net.URLDecoder;
import java.nio.charset.StandardCharsets;
import java.util.Locale;
import java.util.concurrent.ExecutorService;
import java.util.concurrent.Executors;

public final class ZulorsCallSessionManager {
    public interface EventSink {
        void onNativeCallEvent(String type, JSONObject payload);
    }

    private static final String TAG = "ZulorsCallSessionMgr";
    private static final String PREFS = "zulors_active_call_session";
    private static final String KEY_ACTIVE = "active";
    private static final String KEY_CALL_ID = "call_id";
    private static final String KEY_CALL_URL = "call_url";
    private static final String KEY_SESSION_JSON = "session_json";
    private static final String KEY_MUTED = "muted";
    private static final String KEY_SPEAKER_ENABLED = "speaker_enabled";
    private static final String KEY_AUDIO_ROUTE = "audio_route";
    private static final String KEY_LAST_STATE = "last_state";
    private static final String ROUTE_EARPIECE = "earpiece";
    private static final long HEARTBEAT_INTERVAL_MS = 10000L;
    private static final ExecutorService HEARTBEAT_EXECUTOR = Executors.newSingleThreadExecutor();

    private static volatile ZulorsCallSessionManager instance;

    private final Context appContext;
    private final Handler mainHandler;
    private final NativeAgoraCallManager nativeAgoraCallManager;
    private final SharedPreferences prefs;
    private final Runnable nativeHeartbeatLoopRunnable;

    private volatile EventSink eventSink;
    private boolean active;
    private boolean muted;
    private boolean speakerEnabled;
    private String audioRoute = ROUTE_EARPIECE;
    private String activeCallId;
    private String activeCallUrl;
    private String lastKnownState = "idle";
    private String activeSessionJson;
    private String appVisibility = "foreground";
    private String networkState = "stable";
    private boolean remoteAudioLive = false;
    private long lastEngineActivityAtMs = 0L;
    private int reconnectCount = 0;
    private boolean nativeHeartbeatLoopScheduled = false;
    private boolean nativeHeartbeatInFlight = false;
    private boolean nativeHeartbeatFlushPending = false;

    private ZulorsCallSessionManager(Context context) {
        this.appContext = context.getApplicationContext();
        this.mainHandler = new Handler(Looper.getMainLooper());
        this.prefs = appContext.getSharedPreferences(PREFS, Context.MODE_PRIVATE);
        this.nativeHeartbeatLoopRunnable = new Runnable() {
            @Override
            public void run() {
                synchronized (ZulorsCallSessionManager.this) {
                    nativeHeartbeatLoopScheduled = false;
                }

                sendNativeHeartbeat(false);

                synchronized (ZulorsCallSessionManager.this) {
                    if (shouldSendNativeHeartbeat()) {
                        scheduleNativeHeartbeatLoop(HEARTBEAT_INTERVAL_MS);
                    }
                }
            }
        };
        restoreSnapshot();
        this.nativeAgoraCallManager = new NativeAgoraCallManager(
            appContext,
            mainHandler,
            new NativeAgoraCallManager.Listener() {
                @Override
                public void onNativeCallEvent(String type, JSONObject payload) {
                    handleNativeCallEvent(type, payload);
                }
            }
        );
    }

    public static ZulorsCallSessionManager getInstance(Context context) {
        if (instance == null) {
            synchronized (ZulorsCallSessionManager.class) {
                if (instance == null) {
                    instance = new ZulorsCallSessionManager(context);
                }
            }
        }

        return instance;
    }

    public synchronized void attachEventSink(EventSink sink) {
        eventSink = sink;
        Log.d(TAG, "Attached native call event sink. active=" + active + " state=" + lastKnownState);
        refreshState();
        dispatchRouteSnapshot();
    }

    public synchronized void detachEventSink(EventSink sink) {
        if (eventSink == sink) {
            eventSink = null;
            Log.d(TAG, "Detached native call event sink.");
        }
    }

    public synchronized boolean isNativeRtcSupported() {
        return nativeAgoraCallManager != null;
    }

    public synchronized boolean hasActiveCall() {
        return active;
    }

    public synchronized boolean isSpeakerEnabled() {
        return speakerEnabled;
    }

    public synchronized String getAudioRoute() {
        return audioRoute;
    }

    public synchronized void setAppVisibility(boolean visible) {
        appVisibility = visible ? "foreground" : "background";

        if (active) {
            requestNativeHeartbeatFlush();
        }
    }

    public synchronized void rememberPendingCall(Bundle callBundle, boolean startForegroundIfPermitted) {
        if (callBundle == null) {
            return;
        }

        activeCallId = trimToNull(callBundle.getString(ZulorsTelecomCallManager.EXTRA_CALL_ID));
        activeCallUrl = trimToNull(callBundle.getString(ZulorsTelecomCallManager.EXTRA_CALL_URL));
        active = !isBlank(activeCallId);
        lastKnownState = active ? "accepted" : lastKnownState;
        persistSnapshot();
        Log.i(TAG, "Remembered pending call. callId=" + activeCallId + " state=" + lastKnownState);

        if (startForegroundIfPermitted && hasRecordAudioPermission()) {
            startForegroundCallService();
        }
    }

    public synchronized void clearCall(String callId, String reason) {
        String normalizedCallId = trimToNull(callId);

        if (
            !active
            && isBlank(activeCallId)
            && nativeAgoraCallManager == null
        ) {
            return;
        }

        if (
            normalizedCallId != null
            && activeCallId != null
            && !normalizedCallId.equals(activeCallId)
        ) {
            return;
        }

        Log.i(TAG, "Clearing active call. callId=" + activeCallId + " reason=" + reason);

        try {
            nativeAgoraCallManager.release();
        }
        catch (Throwable exception) {
            Log.w(TAG, "Unable to release native Agora call while clearing state.", exception);
        }

        active = false;
        muted = false;
        speakerEnabled = false;
        audioRoute = ROUTE_EARPIECE;
        activeCallId = null;
        activeCallUrl = null;
        activeSessionJson = null;
        lastKnownState = "idle";
        networkState = "stable";
        remoteAudioLive = false;
        lastEngineActivityAtMs = 0L;
        reconnectCount = 0;
        persistSnapshot();
        stopNativeHeartbeatLoop();
        stopForegroundCallService();
    }

    public synchronized boolean startNativeCall(String sessionJson) {
        String normalizedSession = trimToNull(sessionJson);

        if (normalizedSession == null) {
            return false;
        }

        if (!hasRecordAudioPermission()) {
            Log.w(TAG, "Refusing to start native Agora call without RECORD_AUDIO permission.");
            return false;
        }

        String mediaType = extractSessionField(normalizedSession, "media_type");

        if (!isBlank(mediaType) && !"audio".equalsIgnoreCase(mediaType)) {
            Log.w(TAG, "Rejected non-audio native call session. mediaType=" + mediaType);
            return false;
        }

        activeSessionJson = normalizedSession;
        activeCallId = firstNonBlank(extractSessionField(normalizedSession, "call_id"), activeCallId);
        activeCallUrl = firstNonBlank(extractSessionField(normalizedSession, "call_url"), activeCallUrl);
        active = true;
        lastKnownState = "connecting";
        networkState = "stable";
        remoteAudioLive = false;
        lastEngineActivityAtMs = System.currentTimeMillis();
        persistSnapshot();
        startForegroundCallService();
        startNativeHeartbeatLoop();

        nativeAgoraCallManager.setMuted(muted);
        nativeAgoraCallManager.setSpeakerEnabled(speakerEnabled);
        nativeAgoraCallManager.setAudioRoute(audioRoute);

        boolean started = nativeAgoraCallManager.startCall(normalizedSession);

        if (!started) {
            Log.w(TAG, "Native Agora call failed to start. callId=" + activeCallId);
            clearCall(activeCallId, "native_start_failed");
        }
        else {
            Log.i(TAG, "Started native Agora call. callId=" + activeCallId);
        }

        return started;
    }

    public synchronized void endNativeCall() {
        Log.i(TAG, "Ending native Agora call. callId=" + activeCallId);
        stopNativeHeartbeatLoop();
        clearCall(activeCallId, "native_end");
    }

    public synchronized void setMuted(boolean nextMuted) {
        muted = nextMuted;
        persistSnapshot();
        nativeAgoraCallManager.setMuted(nextMuted);
        noteEngineActivity();
        requestNativeHeartbeatFlush();
    }

    public synchronized void setSpeakerEnabled(boolean enabled) {
        speakerEnabled = enabled;
        persistSnapshot();
        nativeAgoraCallManager.setSpeakerEnabled(enabled);
        noteEngineActivity();
        requestNativeHeartbeatFlush();
    }

    public synchronized void rememberSpeakerEnabled(boolean enabled) {
        speakerEnabled = enabled;
        persistSnapshot();
    }

    public synchronized void setAudioRoute(String routeName) {
        audioRoute = normalizeAudioRoute(routeName);
        persistSnapshot();
        nativeAgoraCallManager.setAudioRoute(audioRoute);
        noteEngineActivity();
        requestNativeHeartbeatFlush();
    }

    public synchronized void rememberAudioRoute(String routeName) {
        audioRoute = normalizeAudioRoute(routeName);
        persistSnapshot();
    }

    public synchronized void updateToken(String token) {
        nativeAgoraCallManager.updateToken(token);
    }

    public synchronized void refreshState() {
        if (!active) {
            return;
        }

        noteEngineActivity();
        nativeAgoraCallManager.refreshState();
        requestNativeHeartbeatFlush();
    }

    public synchronized void startForegroundCallService() {
        if (!hasRecordAudioPermission()) {
            Log.w(TAG, "Skipped foreground call service start because RECORD_AUDIO is missing.");
            return;
        }

        try {
            Intent serviceIntent = new Intent(appContext, ZulorsCallForegroundService.class);
            serviceIntent.setAction(ZulorsCallForegroundService.ACTION_START);

            if (!isBlank(activeCallId)) {
                serviceIntent.putExtra(ZulorsTelecomCallManager.EXTRA_CALL_ID, activeCallId);
            }

            if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.O) {
                appContext.startForegroundService(serviceIntent);
            }
            else {
                appContext.startService(serviceIntent);
            }

            Log.d(TAG, "Requested foreground call service start. callId=" + activeCallId);
        }
        catch (Throwable exception) {
            Log.w(TAG, "Unable to start foreground call service.", exception);
        }
    }

    public synchronized void stopForegroundCallService() {
        try {
            Intent serviceIntent = new Intent(appContext, ZulorsCallForegroundService.class);
            serviceIntent.setAction(ZulorsCallForegroundService.ACTION_STOP);
            appContext.startService(serviceIntent);
        }
        catch (Throwable exception) {
            Log.w(TAG, "Unable to send stop action to foreground call service.", exception);
        }

        try {
            appContext.stopService(new Intent(appContext, ZulorsCallForegroundService.class));
            Log.d(TAG, "Requested foreground call service stop.");
        }
        catch (Throwable exception) {
            Log.w(TAG, "Unable to stop foreground call service.", exception);
        }
    }

    public synchronized boolean hasRecordAudioPermission() {
        return Build.VERSION.SDK_INT < Build.VERSION_CODES.M
            || appContext.checkSelfPermission(Manifest.permission.RECORD_AUDIO) == PackageManager.PERMISSION_GRANTED;
    }

    private synchronized void handleNativeCallEvent(String type, JSONObject payload) {
        String normalizedType = trimToNull(type);

        if ("state".equals(normalizedType)) {
            String nextState = payload == null ? null : trimToNull(payload.optString("state", null));
            String previousState = lastKnownState;

            if (nextState != null) {
                lastKnownState = nextState.toLowerCase();
            }

            if ("connecting".equals(lastKnownState) || "connected".equals(lastKnownState) || "reconnecting".equals(lastKnownState)) {
                active = true;
                noteEngineActivity();
                startNativeHeartbeatLoop();
                startForegroundCallService();
            }
            else if ("disconnected".equals(lastKnownState) || "failed".equals(lastKnownState)) {
                active = false;
                activeSessionJson = null;
                activeCallId = null;
                activeCallUrl = null;
                remoteAudioLive = false;
                stopNativeHeartbeatLoop();
                stopForegroundCallService();
            }

            if ("reconnecting".equals(lastKnownState) && !"reconnecting".equals(previousState)) {
                reconnectCount += 1;
                networkState = "reconnecting";
            }
            else if ("connected".equals(lastKnownState)) {
                networkState = "stable";
            }

            persistSnapshot();
            requestNativeHeartbeatFlush();
        }
        else if ("route".equals(normalizedType) && payload != null) {
            audioRoute = normalizeAudioRoute(payload.optString("route", audioRoute));
            noteEngineActivity();
            persistSnapshot();
            requestNativeHeartbeatFlush();
        }
        else if ("remote-audio".equals(normalizedType)) {
            remoteAudioLive = payload != null
                && (payload.optBoolean("remote_audio_live", false) || payload.optBoolean("connected", false));
            noteEngineActivity();
            requestNativeHeartbeatFlush();
        }
        else if ("quality".equals(normalizedType) && payload != null) {
            String quality = trimToNull(payload.optString("network_quality", null));

            if (quality != null) {
                networkState = "good".equals(quality) ? "stable" : quality.toLowerCase(Locale.US);
            }

            remoteAudioLive = payload.optBoolean("remote_audio_live", payload.optBoolean("remote_audio_playing", remoteAudioLive));
            noteEngineActivity();
            requestNativeHeartbeatFlush();
        }

        final EventSink sink = eventSink;

        if (sink == null || normalizedType == null) {
            return;
        }

        mainHandler.post(new Runnable() {
            @Override
            public void run() {
                try {
                    sink.onNativeCallEvent(normalizedType, payload == null ? new JSONObject() : payload);
                }
                catch (Throwable exception) {
                    Log.w(TAG, "Unable to forward native call event.", exception);
                }
            }
        });
    }

    private synchronized void dispatchRouteSnapshot() {
        if (eventSink == null || isBlank(audioRoute)) {
            return;
        }

        JSONObject payload = new JSONObject();

        try {
            payload.put("route", audioRoute);
        }
        catch (Throwable ignored) {}

        handleNativeCallEvent("route", payload);
    }

    private synchronized void restoreSnapshot() {
        active = prefs.getBoolean(KEY_ACTIVE, false);
        muted = prefs.getBoolean(KEY_MUTED, false);
        speakerEnabled = prefs.getBoolean(KEY_SPEAKER_ENABLED, false);
        audioRoute = normalizeAudioRoute(prefs.getString(KEY_AUDIO_ROUTE, ROUTE_EARPIECE));
        activeCallId = trimToNull(prefs.getString(KEY_CALL_ID, null));
        activeCallUrl = trimToNull(prefs.getString(KEY_CALL_URL, null));
        activeSessionJson = trimToNull(prefs.getString(KEY_SESSION_JSON, null));
        lastKnownState = firstNonBlank(
            trimToNull(prefs.getString(KEY_LAST_STATE, null)),
            active ? "accepted" : "idle"
        );
        networkState = "stable";
        remoteAudioLive = false;
        lastEngineActivityAtMs = active ? System.currentTimeMillis() : 0L;
    }

    private synchronized void persistSnapshot() {
        prefs.edit()
            .putBoolean(KEY_ACTIVE, active)
            .putString(KEY_CALL_ID, activeCallId)
            .putString(KEY_CALL_URL, activeCallUrl)
            .putString(KEY_SESSION_JSON, activeSessionJson)
            .putBoolean(KEY_MUTED, muted)
            .putBoolean(KEY_SPEAKER_ENABLED, speakerEnabled)
            .putString(KEY_AUDIO_ROUTE, audioRoute)
            .putString(KEY_LAST_STATE, lastKnownState)
            .apply();
    }

    private synchronized void noteEngineActivity() {
        lastEngineActivityAtMs = System.currentTimeMillis();
    }

    private synchronized boolean shouldSendNativeHeartbeat() {
        return active
            && !isBlank(activeCallId)
            && ("connecting".equals(lastKnownState) || "connected".equals(lastKnownState) || "reconnecting".equals(lastKnownState));
    }

    private synchronized void startNativeHeartbeatLoop() {
        if (!shouldSendNativeHeartbeat()) {
            return;
        }

        scheduleNativeHeartbeatLoop(0L);
    }

    private synchronized void stopNativeHeartbeatLoop() {
        if (mainHandler != null) {
            mainHandler.removeCallbacks(nativeHeartbeatLoopRunnable);
        }

        nativeHeartbeatLoopScheduled = false;
        nativeHeartbeatFlushPending = false;
    }

    private synchronized void scheduleNativeHeartbeatLoop(long delayMs) {
        if (mainHandler == null || !shouldSendNativeHeartbeat()) {
            return;
        }

        mainHandler.removeCallbacks(nativeHeartbeatLoopRunnable);
        nativeHeartbeatLoopScheduled = true;
        mainHandler.postDelayed(nativeHeartbeatLoopRunnable, Math.max(0L, delayMs));
    }

    private synchronized void requestNativeHeartbeatFlush() {
        if (!shouldSendNativeHeartbeat()) {
            return;
        }

        nativeHeartbeatFlushPending = true;
        sendNativeHeartbeat(true);
    }

    private void sendNativeHeartbeat(boolean urgent) {
        final String heartbeatUrl;
        final String cookies;
        final String heartbeatBody;

        synchronized (this) {
            if (!shouldSendNativeHeartbeat()) {
                return;
            }

            if (nativeHeartbeatInFlight) {
                nativeHeartbeatFlushPending = true;
                return;
            }

            heartbeatUrl = heartbeatUrl(activeCallId);
            cookies = CookieManager.getInstance().getCookie(heartbeatUrl);
            heartbeatBody = buildNativeHeartbeatPayload().toString();
            nativeHeartbeatInFlight = true;
            nativeHeartbeatFlushPending = false;

            if (!urgent && !nativeHeartbeatLoopScheduled) {
                scheduleNativeHeartbeatLoop(HEARTBEAT_INTERVAL_MS);
            }
        }

        HEARTBEAT_EXECUTOR.execute(new Runnable() {
            @Override
            public void run() {
                postNativeHeartbeat(heartbeatUrl, cookies, heartbeatBody);

                synchronized (ZulorsCallSessionManager.this) {
                    nativeHeartbeatInFlight = false;

                    if (nativeHeartbeatFlushPending && shouldSendNativeHeartbeat()) {
                        nativeHeartbeatFlushPending = false;
                        sendNativeHeartbeat(true);
                    }
                }
            }
        });
    }

    private synchronized JSONObject buildNativeHeartbeatPayload() {
        JSONObject payload = new JSONObject();

        try {
            payload.put("status", "connected".equals(lastKnownState) ? "connected" : "connecting");
            payload.put("media_provider", "agora");
            payload.put("network_state", firstNonBlank(networkState, "stable"));
            payload.put("call_engine", "android-native");
            payload.put("route", firstNonBlank(audioRoute, ROUTE_EARPIECE));
            payload.put("speaker_enabled", speakerEnabled);
            payload.put("muted", muted);
            payload.put("reconnect_count", reconnectCount);
            payload.put("remote_audio_live", remoteAudioLive);
            payload.put("app_visibility", firstNonBlank(appVisibility, "foreground"));

            if (lastEngineActivityAtMs > 0L) {
                payload.put("engine_activity_at", isoTimestamp(lastEngineActivityAtMs));
            }
        }
        catch (Throwable ignored) {}

        return payload;
    }

    private void postNativeHeartbeat(String heartbeatUrl, String cookies, String heartbeatBody) {
        HttpURLConnection connection = null;

        try {
            URL url = new URL(heartbeatUrl);
            connection = (HttpURLConnection) url.openConnection();
            connection.setRequestMethod("POST");
            connection.setConnectTimeout(10000);
            connection.setReadTimeout(10000);
            connection.setDoOutput(true);
            connection.setRequestProperty("Accept", "application/json");
            connection.setRequestProperty("Content-Type", "application/json; charset=utf-8");
            connection.setRequestProperty("X-Requested-With", "XMLHttpRequest");
            connection.setRequestProperty("User-Agent", System.getProperty("http.agent", "") + " " + BuildConfig.USER_AGENT_SUFFIX);

            if (!isBlank(cookies)) {
                connection.setRequestProperty("Cookie", cookies);
                String csrfToken = cookieValue(cookies, "XSRF-TOKEN");

                if (!isBlank(csrfToken)) {
                    connection.setRequestProperty("X-XSRF-TOKEN", csrfToken);
                }
            }

            byte[] body = heartbeatBody.getBytes(StandardCharsets.UTF_8);
            connection.setFixedLengthStreamingMode(body.length);

            try (OutputStream outputStream = connection.getOutputStream()) {
                outputStream.write(body);
            }

            consumeResponse(connection);
        }
        catch (Throwable exception) {
            Log.w(TAG, "Unable to post native call heartbeat.", exception);
        }
        finally {
            if (connection != null) {
                connection.disconnect();
            }
        }
    }

    private String heartbeatUrl(String callId) {
        Uri baseUri = Uri.parse(BuildConfig.APP_URL);

        return baseUri.buildUpon()
            .encodedPath("/api/messenger/calls/" + callId + "/heartbeat")
            .encodedQuery(null)
            .fragment(null)
            .build()
            .toString();
    }

    private static void consumeResponse(HttpURLConnection connection) throws Exception {
        if (connection == null) {
            return;
        }

        InputStream stream = connection.getResponseCode() >= 200 && connection.getResponseCode() < 400
            ? connection.getInputStream()
            : connection.getErrorStream();

        if (stream == null) {
            return;
        }

        try (BufferedReader reader = new BufferedReader(new InputStreamReader(stream, StandardCharsets.UTF_8))) {
            while (reader.readLine() != null) {
                // Drain the response so the connection can close cleanly.
            }
        }
    }

    private String cookieValue(String cookies, String key) {
        if (isBlank(cookies) || isBlank(key)) {
            return null;
        }

        String prefix = key + "=";

        for (String part : cookies.split(";")) {
            String normalizedPart = part == null ? "" : part.trim();

            if (!normalizedPart.startsWith(prefix)) {
                continue;
            }

            try {
                return URLDecoder.decode(normalizedPart.substring(prefix.length()), StandardCharsets.UTF_8.name());
            }
            catch (Throwable ignored) {
                return normalizedPart.substring(prefix.length());
            }
        }

        return null;
    }

    private static String isoTimestamp(long timestampMs) {
        java.text.SimpleDateFormat format = new java.text.SimpleDateFormat("yyyy-MM-dd'T'HH:mm:ssXXX", Locale.US);
        format.setTimeZone(java.util.TimeZone.getTimeZone("UTC"));

        return format.format(new java.util.Date(timestampMs));
    }

    private String extractSessionField(String sessionJson, String field) {
        if (sessionJson == null || field == null) {
            return null;
        }

        try {
            return trimToNull(new JSONObject(sessionJson).optString(field, null));
        }
        catch (Throwable exception) {
            Log.w(TAG, "Unable to parse native call session JSON.", exception);
            return null;
        }
    }

    private String normalizeAudioRoute(String routeName) {
        String normalized = trimToNull(routeName);

        if (normalized == null) {
            return ROUTE_EARPIECE;
        }

        normalized = normalized.toLowerCase();

        if (
            "speaker".equals(normalized)
            || "wired".equals(normalized)
            || "bluetooth".equals(normalized)
            || ROUTE_EARPIECE.equals(normalized)
        ) {
            return normalized;
        }

        return ROUTE_EARPIECE;
    }

    private String firstNonBlank(String... values) {
        if (values == null) {
            return null;
        }

        for (String value : values) {
            String normalized = trimToNull(value);

            if (normalized != null) {
                return normalized;
            }
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

    private boolean isBlank(String value) {
        return trimToNull(value) == null;
    }
}
