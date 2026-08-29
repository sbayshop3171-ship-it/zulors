package com.zulors.app;

import android.app.NotificationManager;
import android.content.ComponentName;
import android.content.Context;
import android.content.Intent;
import android.content.SharedPreferences;
import android.graphics.Color;
import android.graphics.drawable.Icon;
import android.net.Uri;
import android.os.Build;
import android.os.Bundle;
import android.telecom.DisconnectCause;
import android.telecom.PhoneAccount;
import android.telecom.PhoneAccountHandle;
import android.telecom.TelecomManager;
import android.telecom.VideoProfile;
import android.util.Log;

import org.json.JSONObject;

import java.io.OutputStream;
import java.net.HttpURLConnection;
import java.net.URL;
import java.nio.charset.StandardCharsets;
import java.util.Arrays;
import java.util.LinkedHashSet;
import java.util.Map;
import java.util.Set;
import java.util.concurrent.ConcurrentHashMap;

public final class ZulorsTelecomCallManager {
    public static final String EXTRA_CALL_BUNDLE = "zulors_telecom_call_bundle";
    public static final String EXTRA_CALL_ID = "zulors_call_id";
    public static final String EXTRA_CHAT_ID = "zulors_chat_id";
    public static final String EXTRA_ACTION_TOKEN = "zulors_action_token";
    public static final String EXTRA_CALLER_NAME = "zulors_caller_name";
    public static final String EXTRA_TITLE = "zulors_title";
    public static final String EXTRA_BODY = "zulors_body";
    public static final String EXTRA_CALL_URL = "zulors_call_url";
    public static final String EXTRA_NOTIFICATION_ID = "zulors_notification_id";

    private static final String TAG = "ZulorsTelecom";
    private static final String PHONE_ACCOUNT_ID = "zulors_self_managed_calls";
    private static final String PREFS = "zulors_telecom_calls";
    private static final String KEY_PREFIX = "call:";
    private static final ConcurrentHashMap<String, ZulorsTelecomConnection> ACTIVE_CONNECTIONS = new ConcurrentHashMap<>();

    private ZulorsTelecomCallManager() {
    }

    public static boolean registerPhoneAccount(Context context) {
        if (Build.VERSION.SDK_INT < Build.VERSION_CODES.O || context == null) {
            return false;
        }

        TelecomManager telecomManager = (TelecomManager) context.getSystemService(Context.TELECOM_SERVICE);

        if (telecomManager == null) {
            return false;
        }

        try {
            PhoneAccountHandle handle = phoneAccountHandle(context);
            PhoneAccount account = new PhoneAccount.Builder(handle, context.getString(R.string.app_name))
                .setCapabilities(PhoneAccount.CAPABILITY_SELF_MANAGED)
                .setShortDescription(context.getString(R.string.app_name) + " calls")
                .setSupportedUriSchemes(Arrays.asList(PhoneAccount.SCHEME_TEL, PhoneAccount.SCHEME_SIP))
                .setHighlightColor(Color.BLACK)
                .setIcon(Icon.createWithResource(context, R.mipmap.ic_launcher))
                .build();

            telecomManager.registerPhoneAccount(account);
            Log.d(TAG, "Registered self-managed Telecom phone account.");

            return true;
        }
        catch (Throwable exception) {
            Log.w(TAG, "Unable to register Telecom phone account.", exception);
            return false;
        }
    }

    public static boolean reportIncomingCall(Context context, Map<String, String> data, String title, String body, int notificationId) {
        if (Build.VERSION.SDK_INT < Build.VERSION_CODES.O || context == null || data == null) {
            return false;
        }

        Bundle callBundle = createCallBundle(context, data, title, body, notificationId);

        if (!hasStableCallId(callBundle)) {
            Log.w(TAG, "Skipping Telecom incoming-call report because call_id is missing.");
            return false;
        }

        if (!registerPhoneAccount(context)) {
            return false;
        }

        TelecomManager telecomManager = (TelecomManager) context.getSystemService(Context.TELECOM_SERVICE);

        if (telecomManager == null) {
            return false;
        }

        saveCallBundle(context, callBundle);

        Bundle incomingExtras = new Bundle();
        incomingExtras.putParcelable(TelecomManager.EXTRA_INCOMING_CALL_ADDRESS, buildTelecomAddress(callBundle));
        incomingExtras.putInt(TelecomManager.EXTRA_INCOMING_VIDEO_STATE, VideoProfile.STATE_AUDIO_ONLY);
        incomingExtras.putBundle(TelecomManager.EXTRA_INCOMING_CALL_EXTRAS, callBundle);

        try {
            telecomManager.addNewIncomingCall(phoneAccountHandle(context), incomingExtras);
            Log.i(TAG, "Reported incoming call to Telecom. callId=" + callId(callBundle));
            return true;
        }
        catch (Throwable exception) {
            Log.w(TAG, "Unable to report incoming call to Telecom.", exception);
            showIncomingCallUi(context, callBundle);
            return false;
        }
    }

    public static Bundle createCallBundle(Context context, Map<String, String> data, String title, String body, int notificationId) {
        Bundle bundle = new Bundle();
        String callId = firstNonBlank(data.get("call_id"), data.get("call_uuid"));
        String callerName = firstNonBlank(data.get("caller_name"), data.get("sender_name"), title, context.getString(R.string.app_name));

        bundle.putString(EXTRA_CALL_ID, callId);
        bundle.putString(EXTRA_CHAT_ID, data.get("chat_id"));
        bundle.putString(EXTRA_ACTION_TOKEN, data.get("action_token"));
        bundle.putString(EXTRA_CALLER_NAME, callerName);
        bundle.putString(EXTRA_TITLE, firstNonBlank(title, callerName));
        bundle.putString(EXTRA_BODY, firstNonBlank(body, "Incoming voice call"));
        bundle.putString(EXTRA_CALL_URL, buildCallUrl(data, null));
        bundle.putInt(EXTRA_NOTIFICATION_ID, notificationId);

        return bundle;
    }

    public static Bundle extractCallBundle(Intent intent) {
        if (intent == null) {
            return new Bundle();
        }

        Bundle bundle = intent.getBundleExtra(EXTRA_CALL_BUNDLE);

        if (bundle != null) {
            return bundle;
        }

        bundle = new Bundle();
        bundle.putString(EXTRA_CALL_ID, intent.getStringExtra(EXTRA_CALL_ID));
        bundle.putString(EXTRA_CHAT_ID, intent.getStringExtra(EXTRA_CHAT_ID));
        bundle.putString(EXTRA_ACTION_TOKEN, intent.getStringExtra(EXTRA_ACTION_TOKEN));
        bundle.putString(EXTRA_CALLER_NAME, intent.getStringExtra(EXTRA_CALLER_NAME));
        bundle.putString(EXTRA_TITLE, intent.getStringExtra(EXTRA_TITLE));
        bundle.putString(EXTRA_BODY, intent.getStringExtra(EXTRA_BODY));
        bundle.putString(EXTRA_CALL_URL, intent.getStringExtra(EXTRA_CALL_URL));
        bundle.putInt(EXTRA_NOTIFICATION_ID, intent.getIntExtra(EXTRA_NOTIFICATION_ID, 0));

        return bundle;
    }

    public static Bundle extractCallBundle(Bundle requestExtras, Context context) {
        if (requestExtras == null) {
            return new Bundle();
        }

        Bundle callBundle = requestExtras.getBundle(TelecomManager.EXTRA_INCOMING_CALL_EXTRAS);
        String requestedCallId = requestExtras.getString(EXTRA_CALL_ID);

        if (callBundle != null) {
            if (!isBlank(requestedCallId) && !requestedCallId.equals(callId(callBundle))) {
                Bundle stored = loadCallBundle(context, requestedCallId);

                if (stored != null && requestedCallId.equals(callId(stored))) {
                    return stored;
                }
            }

            return callBundle;
        }

        String callId = requestedCallId;

        if (!isBlank(callId)) {
            Bundle stored = loadCallBundle(context, callId);

            if (stored != null && callId.equals(callId(stored))) {
                return stored;
            }
        }

        return requestExtras;
    }

    public static void showIncomingCallUi(Context context, Bundle callBundle) {
        if (context == null || callBundle == null) {
            return;
        }

        saveCallBundle(context, callBundle);
        Log.i(TAG, "Suppressed custom incoming call UI; keeping notification-only flow active. callId=" + callId(callBundle));
    }

    public static boolean answerCall(Context context, Bundle callBundle, boolean openCallScreen) {
        if (context == null || !hasStableCallId(callBundle)) {
            Log.w(TAG, "Refusing to answer incoming call without a valid call_id.");
            return false;
        }

        ZulorsCallSessionManager callSessionManager = ZulorsCallSessionManager.getInstance(context);

        if (!callSessionManager.hasRecordAudioPermission()) {
            Log.w(TAG, "Opening app call flow without RECORD_AUDIO; permission will be handled in-app. callId=" + callId(callBundle));
            callSessionManager.rememberPendingCall(callBundle, false);
            markConnectionAnswered(callBundle);
            cancelLocalNotification(context, callBundle);
            postPushActionAsync(context, "/api/push-actions/answer-call", callBundle);

            if (openCallScreen) {
                openCallScreen(context, callBundle, "answer");
            }

            return true;
        }

        callSessionManager.rememberPendingCall(callBundle, true);
        markConnectionAnswered(callBundle);
        cancelLocalNotification(context, callBundle);
        postPushActionAsync(context, "/api/push-actions/answer-call", callBundle);

        if (openCallScreen) {
            openCallScreen(context, callBundle, "answer");
        }

        return true;
    }

    public static void declineCall(Context context, Bundle callBundle) {
        if (context == null || !hasStableCallId(callBundle)) {
            Log.w(TAG, "Refusing to decline incoming call without a valid call_id.");
            return;
        }

        markConnectionDisconnected(callBundle, DisconnectCause.REJECTED);
        cancelLocalNotification(context, callBundle);
        postPushActionAsync(context, "/api/push-actions/decline-call", callBundle);
        removeCallBundle(context, callId(callBundle));
        ZulorsCallSessionManager.getInstance(context).clearCall(callId(callBundle), "declined");
    }

    public static void onNotificationActionCompleted(Context context, Intent intent, boolean answered) {
        Bundle callBundle = extractCallBundle(intent);
        String callId = intent != null ? intent.getStringExtra(ZulorsNotificationActionReceiver.EXTRA_CALL_ID) : null;

        if (isBlank(callBundle.getString(EXTRA_CALL_ID)) && !isBlank(callId)) {
            Bundle stored = loadCallBundle(context, callId);

            if (stored != null) {
                callBundle = stored;
            }
            else {
                callBundle.putString(EXTRA_CALL_ID, callId);
            }
        }

        if (answered) {
            ZulorsCallSessionManager.getInstance(context).rememberPendingCall(callBundle, true);
            markConnectionAnswered(callBundle);
        }
        else {
            markConnectionDisconnected(callBundle, DisconnectCause.REJECTED);
            removeCallBundle(context, callId(callBundle));
            ZulorsCallSessionManager.getInstance(context).clearCall(callId(callBundle), "notification_decline");
        }
    }

    public static void cancelIncomingCall(Context context, String callId, int notificationId) {
        Bundle callBundle = loadCallBundle(context, callId);

        if (callBundle == null) {
            callBundle = new Bundle();
            callBundle.putString(EXTRA_CALL_ID, callId);
            callBundle.putInt(EXTRA_NOTIFICATION_ID, notificationId);
        }

        markConnectionDisconnected(callBundle, DisconnectCause.MISSED);
        cancelLocalNotification(context, callBundle);
        removeCallBundle(context, callId);
        ZulorsCallSessionManager.getInstance(context).clearCall(callId, "incoming_cancel");
    }

    public static void registerConnection(String callId, ZulorsTelecomConnection connection) {
        if (!isBlank(callId) && connection != null) {
            ACTIVE_CONNECTIONS.put(callId, connection);
        }
    }

    public static void unregisterConnection(String callId) {
        if (!isBlank(callId)) {
            ACTIVE_CONNECTIONS.remove(callId);
        }
    }

    public static String buildCallUrl(Map<String, String> data, String action) {
        String url = data.get("url");
        String chatId = data.get("chat_id");
        String callId = firstNonBlank(data.get("call_id"), data.get("call_uuid"));

        if (isBlank(url) && !isBlank(chatId)) {
            String baseUrl = BuildConfig.APP_URL;

            while (baseUrl.endsWith("/")) {
                baseUrl = baseUrl.substring(0, baseUrl.length() - 1);
            }

            url = baseUrl + "/messenger/c/" + chatId;
        }

        if (isBlank(url)) {
            url = BuildConfig.APP_URL;
        }

        return updateCallUrl(url, callId, action);
    }

    public static String callId(Bundle callBundle) {
        return callBundle == null ? null : callBundle.getString(EXTRA_CALL_ID);
    }

    private static PhoneAccountHandle phoneAccountHandle(Context context) {
        return new PhoneAccountHandle(
            new ComponentName(context.getApplicationContext(), ZulorsConnectionService.class),
            PHONE_ACCOUNT_ID
        );
    }

    private static Uri buildTelecomAddress(Bundle callBundle) {
        String callId = firstNonBlank(callId(callBundle), "unknown");
        String sanitized = callId.replaceAll("[^0-9A-Za-z.+_-]", "");

        if (isBlank(sanitized)) {
            sanitized = "zulors";
        }

        return Uri.fromParts(PhoneAccount.SCHEME_SIP, sanitized + "@zulors.com", null);
    }

    private static void markConnectionAnswered(Bundle callBundle) {
        ZulorsTelecomConnection connection = ACTIVE_CONNECTIONS.get(callId(callBundle));

        if (connection != null) {
            connection.markAnsweredFromApp();
        }
    }

    private static void markConnectionDisconnected(Bundle callBundle, int disconnectCause) {
        ZulorsTelecomConnection connection = ACTIVE_CONNECTIONS.get(callId(callBundle));

        if (connection != null) {
            connection.markDisconnectedFromApp(disconnectCause);
        }
    }

    private static void openCallScreen(Context context, Bundle callBundle, String action) {
        if (context == null || callBundle == null) {
            return;
        }

        Intent launchIntent = new Intent(context, MainActivity.class);
        launchIntent.setAction("OPEN_ZULORS_NOTIFICATION");
        launchIntent.addFlags(Intent.FLAG_ACTIVITY_NEW_TASK | Intent.FLAG_ACTIVITY_CLEAR_TOP | Intent.FLAG_ACTIVITY_SINGLE_TOP);
        launchIntent.putExtra(
            MainActivity.EXTRA_PUSH_URL,
            updateCallUrl(
                callBundle.getString(EXTRA_CALL_URL),
                callId(callBundle),
                action
            )
        );
        context.startActivity(launchIntent);
    }

    private static String updateCallUrl(String url, String callId, String action) {
        if (isBlank(url)) {
            url = BuildConfig.APP_URL;
        }

        Uri uri = Uri.parse(url);
        Uri.Builder builder = uri.buildUpon().clearQuery();
        Set<String> queryNames = new LinkedHashSet<>(uri.getQueryParameterNames());

        for (String queryName : queryNames) {
            if ("call".equals(queryName) || "action".equals(queryName) || "intent".equals(queryName)) {
                continue;
            }

            for (String value : uri.getQueryParameters(queryName)) {
                builder.appendQueryParameter(queryName, value);
            }
        }

        if (!isBlank(callId)) {
            builder.appendQueryParameter("call", callId);
        }

        if (!isBlank(action)) {
            builder.appendQueryParameter("action", action);
            builder.appendQueryParameter("intent", "answer");
        }
        else {
            builder.appendQueryParameter("intent", "incoming");
        }

        return builder.build().toString();
    }

    private static void postPushActionAsync(Context context, String endpoint, Bundle callBundle) {
        if (context == null) {
            return;
        }

        String token = callBundle == null ? null : callBundle.getString(EXTRA_ACTION_TOKEN);

        if (isBlank(token)) {
            Log.w(TAG, "Skipping Telecom push action because action_token is missing. endpoint=" + endpoint);
            return;
        }

        new Thread(new Runnable() {
            @Override
            public void run() {
                try {
                    JSONObject payload = new JSONObject();
                    payload.put("token", token);
                    postJson(endpoint, payload);
                }
                catch (Throwable exception) {
                    Log.w(TAG, "Unable to post Telecom call action.", exception);
                }
            }
        }).start();
    }

    private static boolean postJson(String path, JSONObject payload) throws Exception {
        HttpURLConnection connection = (HttpURLConnection) new URL(resolveEndpoint(path)).openConnection();

        connection.setRequestMethod("POST");
        connection.setDoOutput(true);
        connection.setConnectTimeout(5000);
        connection.setReadTimeout(8000);
        connection.setRequestProperty("Accept", "application/json");
        connection.setRequestProperty("Content-Type", "application/json; charset=utf-8");
        connection.setRequestProperty("User-Agent", BuildConfig.USER_AGENT_SUFFIX);

        byte[] body = payload.toString().getBytes(StandardCharsets.UTF_8);

        try (OutputStream stream = connection.getOutputStream()) {
            stream.write(body);
        }

        int status = connection.getResponseCode();
        connection.disconnect();

        return status >= 200 && status < 300;
    }

    private static String resolveEndpoint(String path) {
        String baseUrl = BuildConfig.APP_URL;

        while (baseUrl.endsWith("/")) {
            baseUrl = baseUrl.substring(0, baseUrl.length() - 1);
        }

        return baseUrl + path;
    }

    private static void cancelLocalNotification(Context context, Bundle callBundle) {
        NotificationManager manager = (NotificationManager) context.getSystemService(Context.NOTIFICATION_SERVICE);

        if (manager == null || callBundle == null) {
            return;
        }

        int notificationId = callBundle.getInt(EXTRA_NOTIFICATION_ID, 0);

        if (notificationId != 0) {
            manager.cancel(notificationId);
        }
    }

    private static void saveCallBundle(Context context, Bundle callBundle) {
        if (context == null || callBundle == null || isBlank(callId(callBundle))) {
            return;
        }

        try {
            JSONObject object = new JSONObject();
            putJson(object, EXTRA_CALL_ID, callBundle.getString(EXTRA_CALL_ID));
            putJson(object, EXTRA_CHAT_ID, callBundle.getString(EXTRA_CHAT_ID));
            putJson(object, EXTRA_ACTION_TOKEN, callBundle.getString(EXTRA_ACTION_TOKEN));
            putJson(object, EXTRA_CALLER_NAME, callBundle.getString(EXTRA_CALLER_NAME));
            putJson(object, EXTRA_TITLE, callBundle.getString(EXTRA_TITLE));
            putJson(object, EXTRA_BODY, callBundle.getString(EXTRA_BODY));
            putJson(object, EXTRA_CALL_URL, callBundle.getString(EXTRA_CALL_URL));
            object.put(EXTRA_NOTIFICATION_ID, callBundle.getInt(EXTRA_NOTIFICATION_ID, 0));

            prefs(context).edit()
                .putString(KEY_PREFIX + callId(callBundle), object.toString())
                .apply();
        }
        catch (Throwable exception) {
            Log.w(TAG, "Unable to save Telecom call bundle.", exception);
        }
    }

    private static Bundle loadCallBundle(Context context, String callId) {
        if (context == null || isBlank(callId)) {
            return null;
        }

        String value = prefs(context).getString(KEY_PREFIX + callId, null);

        if (isBlank(value)) {
            return null;
        }

        try {
            JSONObject object = new JSONObject(value);
            Bundle bundle = new Bundle();
            bundle.putString(EXTRA_CALL_ID, object.optString(EXTRA_CALL_ID, callId));
            bundle.putString(EXTRA_CHAT_ID, object.optString(EXTRA_CHAT_ID, null));
            bundle.putString(EXTRA_ACTION_TOKEN, object.optString(EXTRA_ACTION_TOKEN, null));
            bundle.putString(EXTRA_CALLER_NAME, object.optString(EXTRA_CALLER_NAME, null));
            bundle.putString(EXTRA_TITLE, object.optString(EXTRA_TITLE, null));
            bundle.putString(EXTRA_BODY, object.optString(EXTRA_BODY, null));
            bundle.putString(EXTRA_CALL_URL, object.optString(EXTRA_CALL_URL, null));
            bundle.putInt(EXTRA_NOTIFICATION_ID, object.optInt(EXTRA_NOTIFICATION_ID, 0));

            if (!callId.equals(bundle.getString(EXTRA_CALL_ID))) {
                return null;
            }

            return bundle;
        }
        catch (Throwable exception) {
            Log.w(TAG, "Unable to load Telecom call bundle.", exception);
            return null;
        }
    }

    private static void removeCallBundle(Context context, String callId) {
        if (context != null && !isBlank(callId)) {
            prefs(context).edit().remove(KEY_PREFIX + callId).apply();
        }
    }

    private static SharedPreferences prefs(Context context) {
        return context.getSharedPreferences(PREFS, Context.MODE_PRIVATE);
    }

    private static void putJson(JSONObject object, String key, String value) throws Exception {
        if (!isBlank(value)) {
            object.put(key, value);
        }
    }

    private static String firstNonBlank(String... values) {
        if (values == null) {
            return null;
        }

        for (String value : values) {
            if (!isBlank(value)) {
                return value;
            }
        }

        return null;
    }

    private static boolean isBlank(String value) {
        return value == null || value.trim().isEmpty();
    }

    private static boolean hasStableCallId(Bundle callBundle) {
        return !isBlank(callId(callBundle));
    }
}
