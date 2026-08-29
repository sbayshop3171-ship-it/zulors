package com.zulors.app;

import android.app.NotificationManager;
import android.app.RemoteInput;
import android.content.BroadcastReceiver;
import android.content.Context;
import android.content.Intent;
import android.os.Bundle;
import android.os.SystemClock;
import android.util.Log;

import org.json.JSONObject;

import java.io.OutputStream;
import java.net.HttpURLConnection;
import java.net.URL;
import java.nio.charset.StandardCharsets;

public class ZulorsNotificationActionReceiver extends BroadcastReceiver {
    public static final String ACTION_REPLY = "com.zulors.app.action.REPLY";
    public static final String ACTION_READ = "com.zulors.app.action.READ";
    public static final String ACTION_MUTE = "com.zulors.app.action.MUTE";
    public static final String ACTION_CALL_ANSWER = "com.zulors.app.action.CALL_ANSWER";
    public static final String ACTION_CALL_DECLINE = "com.zulors.app.action.CALL_DECLINE";
    public static final String KEY_TEXT_REPLY = "zulors_text_reply";
    public static final String EXTRA_ACTION_TOKEN = "zulors_action_token";
    public static final String EXTRA_NOTIFICATION_ID = "zulors_notification_id";
    public static final String EXTRA_CHAT_ID = "zulors_chat_id";
    public static final String EXTRA_CALL_ID = "zulors_call_id";

    private static final String TAG = "ZulorsPushAction";
    private static final long DUPLICATE_CALL_ACTION_WINDOW_MS = 2500L;
    private static final Object CALL_ACTION_LOCK = new Object();
    private static String lastCallActionKey = "";
    private static long lastCallActionAtMs = 0L;

    @Override
    public void onReceive(Context context, Intent intent) {
        PendingResult pendingResult = goAsync();

        new Thread(new Runnable() {
            @Override
            public void run() {
                try {
                    handleAction(context, intent);
                }
                finally {
                    pendingResult.finish();
                }
            }
        }).start();
    }

    private void handleAction(Context context, Intent intent) {
        if (intent == null || intent.getAction() == null) {
            return;
        }

        String token = intent.getStringExtra(EXTRA_ACTION_TOKEN);

        if (isBlank(token)) {
            return;
        }

        try {
            JSONObject payload = new JSONObject();
            payload.put("token", token);

            String endpoint;

            if (ACTION_REPLY.equals(intent.getAction())) {
                CharSequence replyText = readReplyText(intent);

                if (replyText == null || isBlank(replyText.toString())) {
                    return;
                }

                endpoint = "/api/push-actions/reply";
                payload.put("content", replyText.toString());
            }
            else if (ACTION_READ.equals(intent.getAction())) {
                endpoint = "/api/push-actions/read";
            }
            else if (ACTION_MUTE.equals(intent.getAction())) {
                endpoint = "/api/push-actions/mute-chat";
                payload.put("duration_minutes", 480);
            }
            else if (ACTION_CALL_ANSWER.equals(intent.getAction())) {
                if (isDuplicateCallAction(intent, "answer")) {
                    return;
                }

                endpoint = "/api/push-actions/answer-call";
                clearLocalNotification(context, intent);
                ZulorsTelecomCallManager.onNotificationActionCompleted(context, intent, true);
                openCallScreen(context, intent);
            }
            else if (ACTION_CALL_DECLINE.equals(intent.getAction())) {
                if (isDuplicateCallAction(intent, "decline")) {
                    return;
                }

                endpoint = "/api/push-actions/decline-call";
                clearLocalNotification(context, intent);
                ZulorsTelecomCallManager.onNotificationActionCompleted(context, intent, false);
            }
            else {
                return;
            }

            boolean posted = postJson(endpoint, payload);

            if (posted && !ACTION_CALL_ANSWER.equals(intent.getAction()) && !ACTION_CALL_DECLINE.equals(intent.getAction())) {
                clearLocalNotification(context, intent);
            }
        }
        catch (Throwable exception) {
            Log.w(TAG, "Notification action failed.", exception);
        }
    }

    private CharSequence readReplyText(Intent intent) {
        Bundle remoteInput = RemoteInput.getResultsFromIntent(intent);

        if (remoteInput == null) {
            return null;
        }

        return remoteInput.getCharSequence(KEY_TEXT_REPLY);
    }

    private boolean postJson(String path, JSONObject payload) throws Exception {
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

    private String resolveEndpoint(String path) {
        String baseUrl = BuildConfig.APP_URL;

        while (baseUrl.endsWith("/")) {
            baseUrl = baseUrl.substring(0, baseUrl.length() - 1);
        }

        return baseUrl + path;
    }

    private void clearLocalNotification(Context context, Intent intent) {
        NotificationManager manager = (NotificationManager) context.getSystemService(Context.NOTIFICATION_SERVICE);

        if (manager != null) {
            int notificationId = intent.getIntExtra(EXTRA_NOTIFICATION_ID, 0);

            if (notificationId != 0) {
                manager.cancel(notificationId);
            }
        }

        String chatId = intent.getStringExtra(EXTRA_CHAT_ID);

        if (!isBlank(chatId)) {
            context.getSharedPreferences("zulors_notification_history", Context.MODE_PRIVATE)
                .edit()
                .remove("chat:" + chatId)
                .apply();
        }
    }

    private void openCallScreen(Context context, Intent intent) {
        Intent launchIntent = new Intent(context, MainActivity.class);

        launchIntent.setAction("OPEN_ZULORS_NOTIFICATION");
        launchIntent.addFlags(Intent.FLAG_ACTIVITY_NEW_TASK | Intent.FLAG_ACTIVITY_CLEAR_TOP | Intent.FLAG_ACTIVITY_SINGLE_TOP);
        launchIntent.putExtra(MainActivity.EXTRA_PUSH_URL, intent.getStringExtra(MainActivity.EXTRA_PUSH_URL));
        context.startActivity(launchIntent);
    }

    private boolean isDuplicateCallAction(Intent intent, String actionName) {
        String callId = intent == null ? null : intent.getStringExtra(EXTRA_CALL_ID);

        if (isBlank(callId) || isBlank(actionName)) {
            return false;
        }

        String actionKey = actionName + ":" + callId;
        long now = SystemClock.elapsedRealtime();

        synchronized (CALL_ACTION_LOCK) {
            if (actionKey.equals(lastCallActionKey) && (now - lastCallActionAtMs) < DUPLICATE_CALL_ACTION_WINDOW_MS) {
                Log.i(TAG, "Ignored duplicate call notification action. key=" + actionKey);
                return true;
            }

            lastCallActionKey = actionKey;
            lastCallActionAtMs = now;
        }

        return false;
    }

    private boolean isBlank(String value) {
        return value == null || value.trim().isEmpty();
    }
}
