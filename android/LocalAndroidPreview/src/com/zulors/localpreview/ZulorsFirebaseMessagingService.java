package com.zulors.app;

import android.app.Notification;
import android.app.NotificationChannel;
import android.app.NotificationManager;
import android.app.PendingIntent;
import android.app.RemoteInput;
import android.content.Context;
import android.content.Intent;
import android.content.SharedPreferences;
import android.media.AudioAttributes;
import android.graphics.Bitmap;
import android.graphics.BitmapFactory;
import android.graphics.BitmapShader;
import android.graphics.Canvas;
import android.graphics.Color;
import android.graphics.Paint;
import android.graphics.Shader;
import android.graphics.drawable.Drawable;
import android.net.Uri;
import android.os.Build;
import android.os.Bundle;
import android.media.RingtoneManager;
import android.media.AudioManager;
import android.text.TextUtils;

import com.google.firebase.messaging.FirebaseMessagingService;
import com.google.firebase.messaging.RemoteMessage;

import java.io.InputStream;
import java.net.HttpURLConnection;
import java.net.URL;
import java.util.ArrayList;
import java.util.List;
import java.util.Map;

public class ZulorsFirebaseMessagingService extends FirebaseMessagingService {
    private static final String CHANNEL_DEFAULT = "zulors_default";
    private static final String CHANNEL_MESSAGES = "zulors_messages";
    private static final String CHANNEL_ACTIVITY = "zulors_activity";
    private static final String CHANNEL_SYSTEM = "zulors_system";
    private static final String CHANNEL_CALLS_LEGACY = "zulors_calls";
    private static final String CHANNEL_CALLS = "zulors_calls_v3";

    private static final String GROUP_MESSAGES = "zulors.group.messages";
    private static final String GROUP_ACTIVITY = "zulors.group.activity";
    private static final String HISTORY_PREFS = "zulors_notification_history";
    private static final String HISTORY_SEPARATOR = "\u001E";
    private static final int MAX_MESSAGE_HISTORY = 5;
    private static final int ZULORS_BLACK = 0xFF111111;
    private static final long INCOMING_CALL_TIMEOUT_MS = 45000L;

    @Override
    public void onNewToken(String token) {
        PushTokenBridge.saveToken(this, token);
        PushTokenBridge.syncLatestToken(this);
    }

    @Override
    public void onMessageReceived(RemoteMessage message) {
        Map<String, String> data = message.getData();
        RemoteMessage.Notification remoteNotification = message.getNotification();

        if (isCallCancelNotification(data)) {
            cancelCallNotification(data);
            return;
        }

        String title = firstNonBlank(
            remoteNotification != null ? remoteNotification.getTitle() : null,
            data.get("title"),
            data.get("sender_name"),
            getString(R.string.app_name)
        );
        String body = firstNonBlank(
            remoteNotification != null ? remoteNotification.getBody() : null,
            data.get("body"),
            data.get("message")
        );

        if (isBlank(body)) {
            return;
        }

        showNotification(title, body, data);
    }

    private void showNotification(String title, String body, Map<String, String> data) {
        ensureNotificationChannels();

        boolean messageNotification = isMessageNotification(data);
        boolean callNotification = isCallNotification(data);
        String channelId = resolveChannelId(data, messageNotification, callNotification);
        String chatId = data.get("chat_id");
        int notificationId = buildNotificationId(data, messageNotification);
        Bundle incomingCallBundle = null;
        PendingIntent pendingIntent = createContentIntent(
            notificationId,
            callNotification && isIncomingCallNotification(data) ? buildCallUrl(data, null) : data.get("url")
        );
        Bitmap largeIcon = loadLargeIcon(data);

        if (callNotification && isIncomingCallNotification(data)) {
            incomingCallBundle = ZulorsTelecomCallManager.createCallBundle(this, data, title, body, notificationId);
            ZulorsTelecomCallManager.reportIncomingCall(this, data, title, body, notificationId);
        }

        Notification.Builder builder = Build.VERSION.SDK_INT >= Build.VERSION_CODES.O
            ? new Notification.Builder(this, channelId)
            : new Notification.Builder(this);

        builder
            .setSmallIcon(R.drawable.ic_zulors_notification)
            .setLargeIcon(largeIcon)
            .setContentTitle(title)
            .setContentText(body)
            .setSubText(getString(R.string.app_name))
            .setTicker(title + ": " + body)
            .setAutoCancel(true)
            .setShowWhen(true)
            .setWhen(System.currentTimeMillis())
            .setContentIntent(pendingIntent)
            .setColor(ZULORS_BLACK)
            .setVisibility(Notification.VISIBILITY_PRIVATE);

        if (callNotification) {
            builder
                .setCategory(Notification.CATEGORY_CALL)
                .setPriority(Notification.PRIORITY_MAX)
                .setDefaults(Notification.DEFAULT_VIBRATE | Notification.DEFAULT_LIGHTS)
                .setVibrate(new long[] {0, 450, 160, 450, 650})
                .setOnlyAlertOnce(false)
                .setVisibility(Notification.VISIBILITY_PUBLIC)
                .setStyle(new Notification.BigTextStyle()
                    .bigText(body)
                    .setSummaryText(getString(R.string.app_name)));

            if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.LOLLIPOP) {
                builder.setSound(defaultCallRingtoneUri(), callRingtoneAttributes());
            }
            else {
                builder.setSound(defaultCallRingtoneUri(), AudioManager.STREAM_RING);
            }

            if (isIncomingCallNotification(data)) {
                addCallActions(builder, data, notificationId);
                builder
                    .setAutoCancel(false)
                    .setOngoing(true);

                if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.O) {
                    builder.setTimeoutAfter(INCOMING_CALL_TIMEOUT_MS);
                }
            }
        }
        else if (messageNotification) {
            builder
                .setCategory(Notification.CATEGORY_MESSAGE)
                .setGroup(GROUP_MESSAGES)
                .setPriority(Notification.PRIORITY_HIGH)
                .setDefaults(Notification.DEFAULT_ALL)
                .setOnlyAlertOnce(true)
                .setStyle(createMessageStyle(title, body, chatId));

            if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.O && !isBlank(chatId)) {
                builder.setShortcutId("chat-" + chatId);
            }

            addMessageActions(builder, data, notificationId);
        }
        else {
            builder
                .setCategory(Notification.CATEGORY_SOCIAL)
                .setGroup(GROUP_ACTIVITY)
                .setPriority(Notification.PRIORITY_DEFAULT)
                .setDefaults(Notification.DEFAULT_SOUND | Notification.DEFAULT_LIGHTS)
                .setStyle(new Notification.BigTextStyle()
                    .bigText(body)
                    .setSummaryText(getString(R.string.app_name)));
        }

        int badgeCount = parsePositiveInt(firstNonBlank(data.get("badge_count"), data.get("unread_count")));

        if (badgeCount > 0) {
            builder.setNumber(badgeCount);
        }

        if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.O) {
            builder.setBadgeIconType(Notification.BADGE_ICON_LARGE);
        }

        if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.Q) {
            builder.setAllowSystemGeneratedContextualActions(true);
        }

        NotificationManager manager = (NotificationManager) getSystemService(Context.NOTIFICATION_SERVICE);

        if (manager != null) {
            manager.notify(notificationId, builder.build());
        }
    }

    private PendingIntent createContentIntent(int notificationId, String url) {
        Intent intent = new Intent(this, MainActivity.class);
        intent.setAction("OPEN_ZULORS_NOTIFICATION");
        intent.addFlags(Intent.FLAG_ACTIVITY_CLEAR_TOP | Intent.FLAG_ACTIVITY_SINGLE_TOP);

        if (!isBlank(url)) {
            intent.putExtra(MainActivity.EXTRA_PUSH_URL, url);
        }

        int pendingFlags = PendingIntent.FLAG_UPDATE_CURRENT;

        if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.M) {
            pendingFlags |= PendingIntent.FLAG_IMMUTABLE;
        }

        return PendingIntent.getActivity(this, notificationId, intent, pendingFlags);
    }

    private void addMessageActions(Notification.Builder builder, Map<String, String> data, int notificationId) {
        String actionToken = data.get("action_token");

        if (isBlank(actionToken)) {
            return;
        }

        builder.addAction(createReplyAction(data, notificationId, actionToken));
        builder.addAction(createSimpleAction(
            "Mark as read",
            ZulorsNotificationActionReceiver.ACTION_READ,
            notificationId + 2,
            data,
            actionToken
        ));
        builder.addAction(createSimpleAction(
            "Mute",
            ZulorsNotificationActionReceiver.ACTION_MUTE,
            notificationId + 3,
            data,
            actionToken
        ));
    }

    private void addCallActions(Notification.Builder builder, Map<String, String> data, int notificationId) {
        String actionToken = data.get("action_token");

        if (isBlank(actionToken)) {
            return;
        }

        builder.addAction(createSimpleAction(
            "Answer",
            ZulorsNotificationActionReceiver.ACTION_CALL_ANSWER,
            notificationId + 1,
            data,
            actionToken
        ));
        builder.addAction(createSimpleAction(
            "Decline",
            ZulorsNotificationActionReceiver.ACTION_CALL_DECLINE,
            notificationId + 2,
            data,
            actionToken
        ));
        builder.addAction(createOpenCallAction(
            "Message",
            notificationId + 3,
            data,
            "message"
        ));
    }

    private Notification.Action createOpenCallAction(String title, int requestCode, Map<String, String> data, String action) {
        Notification.Action.Builder actionBuilder = new Notification.Action.Builder(
            R.drawable.ic_zulors_notification,
            title,
            createCallContentIntent(requestCode, data, action)
        );

        if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.P && "answer".equals(action)) {
            actionBuilder.setSemanticAction(Notification.Action.SEMANTIC_ACTION_CALL);
        }

        return actionBuilder.build();
    }

    private Notification.Action createReplyAction(Map<String, String> data, int requestCode, String actionToken) {
        Intent intent = createActionIntent(ZulorsNotificationActionReceiver.ACTION_REPLY, data, actionToken);
        PendingIntent pendingIntent = createActionPendingIntent(requestCode + 1, intent, true);
        RemoteInput remoteInput = new RemoteInput.Builder(ZulorsNotificationActionReceiver.KEY_TEXT_REPLY)
            .setLabel("Reply")
            .build();

        Notification.Action.Builder actionBuilder = new Notification.Action.Builder(
            R.drawable.ic_zulors_notification,
            "Reply",
            pendingIntent
        ).addRemoteInput(remoteInput);

        if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.N) {
            actionBuilder.setAllowGeneratedReplies(true);
        }

        if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.P) {
            actionBuilder.setSemanticAction(Notification.Action.SEMANTIC_ACTION_REPLY);
        }

        return actionBuilder.build();
    }

    private Notification.Action createSimpleAction(String title, String action, int requestCode, Map<String, String> data, String actionToken) {
        Notification.Action.Builder actionBuilder = new Notification.Action.Builder(
            R.drawable.ic_zulors_notification,
            title,
            createActionPendingIntent(requestCode, createActionIntent(action, data, actionToken), false)
        );

        if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.P && ZulorsNotificationActionReceiver.ACTION_READ.equals(action)) {
            actionBuilder.setSemanticAction(Notification.Action.SEMANTIC_ACTION_MARK_AS_READ);
        }

        if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.P && ZulorsNotificationActionReceiver.ACTION_CALL_ANSWER.equals(action)) {
            actionBuilder.setSemanticAction(Notification.Action.SEMANTIC_ACTION_CALL);
        }

        return actionBuilder.build();
    }

    private Intent createActionIntent(String action, Map<String, String> data, String actionToken) {
        Intent intent = new Intent(this, ZulorsNotificationActionReceiver.class);

        intent.setAction(action);
        intent.putExtra(ZulorsNotificationActionReceiver.EXTRA_ACTION_TOKEN, actionToken);
        intent.putExtra(ZulorsNotificationActionReceiver.EXTRA_CHAT_ID, data.get("chat_id"));
        intent.putExtra(ZulorsNotificationActionReceiver.EXTRA_CALL_ID, firstNonBlank(data.get("call_id"), data.get("call_uuid")));
        intent.putExtra(ZulorsNotificationActionReceiver.EXTRA_NOTIFICATION_ID, buildNotificationId(data, true));
        intent.putExtra(
            ZulorsTelecomCallManager.EXTRA_CALL_BUNDLE,
            ZulorsTelecomCallManager.createCallBundle(
                this,
                data,
                firstNonBlank(data.get("title"), data.get("caller_name"), data.get("sender_name"), getString(R.string.app_name)),
                firstNonBlank(data.get("body"), data.get("message"), "Incoming voice call"),
                buildNotificationId(data, true)
            )
        );
        intent.putExtra(
            MainActivity.EXTRA_PUSH_URL,
            ZulorsNotificationActionReceiver.ACTION_CALL_ANSWER.equals(action)
                ? buildCallUrl(data, "answer")
                : data.get("url")
        );

        return intent;
    }

    private PendingIntent createCallContentIntent(int requestCode, Map<String, String> data, String action) {
        Intent intent = new Intent(this, MainActivity.class);

        intent.setAction("OPEN_ZULORS_NOTIFICATION");
        intent.addFlags(Intent.FLAG_ACTIVITY_CLEAR_TOP | Intent.FLAG_ACTIVITY_SINGLE_TOP);
        intent.putExtra(
            MainActivity.EXTRA_PUSH_URL,
            "message".equals(action) ? buildMessageUrl(data) : buildCallUrl(data, action)
        );

        int pendingFlags = PendingIntent.FLAG_UPDATE_CURRENT;

        if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.M) {
            pendingFlags |= PendingIntent.FLAG_IMMUTABLE;
        }

        return PendingIntent.getActivity(this, requestCode, intent, pendingFlags);
    }

    private String buildCallUrl(Map<String, String> data, String action) {
        return ZulorsTelecomCallManager.buildCallUrl(data, action);
    }

    private String buildMessageUrl(Map<String, String> data) {
        String chatId = data.get("chat_id");

        if (!isBlank(chatId)) {
            String baseUrl = BuildConfig.APP_URL;

            while (baseUrl.endsWith("/")) {
                baseUrl = baseUrl.substring(0, baseUrl.length() - 1);
            }

            return baseUrl + "/messenger/c/" + chatId;
        }

        return firstNonBlank(data.get("url"), BuildConfig.APP_URL);
    }

    private PendingIntent createActionPendingIntent(int requestCode, Intent intent, boolean mutable) {
        int pendingFlags = PendingIntent.FLAG_UPDATE_CURRENT;

        if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.M) {
            pendingFlags |= mutable ? PendingIntent.FLAG_MUTABLE : PendingIntent.FLAG_IMMUTABLE;
        }

        return PendingIntent.getBroadcast(this, requestCode, intent, pendingFlags);
    }

    private Notification.Style createMessageStyle(String senderName, String body, String chatId) {
        List<String> messages = rememberMessageHistory(chatId, body);

        if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.N) {
            Notification.MessagingStyle style = new Notification.MessagingStyle(getString(R.string.app_name))
                .setConversationTitle(senderName);

            long timestamp = System.currentTimeMillis() - (messages.size() * 1000L);

            for (String message : messages) {
                style.addMessage(message, timestamp, senderName);
                timestamp += 1000L;
            }

            return style;
        }

        Notification.InboxStyle style = new Notification.InboxStyle()
            .setBigContentTitle(senderName)
            .setSummaryText(getString(R.string.app_name));

        for (String message : messages) {
            style.addLine(message);
        }

        return style;
    }

    private List<String> rememberMessageHistory(String chatId, String body) {
        ArrayList<String> messages = new ArrayList<>();

        if (isBlank(chatId)) {
            messages.add(body);
            return messages;
        }

        SharedPreferences prefs = getSharedPreferences(HISTORY_PREFS, MODE_PRIVATE);
        String key = "chat:" + chatId;
        String existing = prefs.getString(key, "");

        if (!isBlank(existing)) {
            String[] parts = existing.split(HISTORY_SEPARATOR);

            for (String part : parts) {
                if (!isBlank(part)) {
                    messages.add(part);
                }
            }
        }

        messages.add(body);

        while (messages.size() > MAX_MESSAGE_HISTORY) {
            messages.remove(0);
        }

        prefs.edit().putString(key, TextUtils.join(HISTORY_SEPARATOR, messages)).apply();

        return messages;
    }

    private Bitmap loadLargeIcon(Map<String, String> data) {
        Bitmap remoteAvatar = loadRemoteAvatar(firstNonBlank(
            data.get("sender_avatar_url"),
            data.get("caller_avatar_url"),
            data.get("avatar_url"),
            data.get("image_url")
        ));

        if (remoteAvatar != null) {
            return remoteAvatar;
        }

        return drawableToBitmap(getApplicationInfo().loadIcon(getPackageManager()));
    }

    private Bitmap loadRemoteAvatar(String avatarUrl) {
        if (isBlank(avatarUrl)) {
            return null;
        }

        try {
            Uri uri = Uri.parse(avatarUrl);
            String scheme = uri.getScheme();

            if (!"https".equalsIgnoreCase(scheme) && !"http".equalsIgnoreCase(scheme)) {
                return null;
            }

            HttpURLConnection connection = (HttpURLConnection) new URL(avatarUrl).openConnection();
            connection.setConnectTimeout(1200);
            connection.setReadTimeout(1500);
            connection.setInstanceFollowRedirects(true);

            try (InputStream stream = connection.getInputStream()) {
                Bitmap bitmap = BitmapFactory.decodeStream(stream);

                if (bitmap == null) {
                    return null;
                }

                return circularCrop(bitmap);
            }
        }
        catch (Throwable ignored) {
            return null;
        }
    }

    private Bitmap circularCrop(Bitmap source) {
        int size = Math.min(source.getWidth(), source.getHeight());
        int left = (source.getWidth() - size) / 2;
        int top = (source.getHeight() - size) / 2;
        Bitmap squared = Bitmap.createBitmap(source, left, top, size, size);
        Bitmap output = Bitmap.createBitmap(size, size, Bitmap.Config.ARGB_8888);
        Canvas canvas = new Canvas(output);
        Paint paint = new Paint(Paint.ANTI_ALIAS_FLAG);

        paint.setShader(new BitmapShader(squared, Shader.TileMode.CLAMP, Shader.TileMode.CLAMP));
        canvas.drawCircle(size / 2f, size / 2f, size / 2f, paint);

        return output;
    }

    private Bitmap drawableToBitmap(Drawable drawable) {
        int size = Math.max(96, Math.max(drawable.getIntrinsicWidth(), drawable.getIntrinsicHeight()));
        Bitmap bitmap = Bitmap.createBitmap(size, size, Bitmap.Config.ARGB_8888);
        Canvas canvas = new Canvas(bitmap);

        drawable.setBounds(0, 0, canvas.getWidth(), canvas.getHeight());
        drawable.draw(canvas);

        return bitmap;
    }

    private void ensureNotificationChannels() {
        if (Build.VERSION.SDK_INT < Build.VERSION_CODES.O) {
            return;
        }

        NotificationManager manager = (NotificationManager) getSystemService(Context.NOTIFICATION_SERVICE);

        if (manager == null) {
            return;
        }

        createChannel(manager, CHANNEL_DEFAULT, "Zulors", "General Zulors notifications", NotificationManager.IMPORTANCE_DEFAULT, true);
        createChannel(manager, CHANNEL_MESSAGES, "Messages", "Direct messages and chat updates", NotificationManager.IMPORTANCE_HIGH, true);
        createChannel(manager, CHANNEL_ACTIVITY, "Activity", "Likes, comments, follows, mentions and social updates", NotificationManager.IMPORTANCE_DEFAULT, true);
        createChannel(manager, CHANNEL_SYSTEM, "Important", "Security, account, payment and admin updates", NotificationManager.IMPORTANCE_HIGH, true);
        createChannel(manager, CHANNEL_CALLS_LEGACY, "Calls", "Incoming audio and video calls", NotificationManager.IMPORTANCE_HIGH, true);
        createCallChannel(manager);
    }

    private void createChannel(NotificationManager manager, String id, String name, String description, int importance, boolean showBadge) {
        if (manager.getNotificationChannel(id) != null) {
            return;
        }

        NotificationChannel channel = new NotificationChannel(id, name, importance);

        channel.setDescription(description);
        channel.enableLights(true);
        channel.setLightColor(Color.WHITE);
        channel.enableVibration(importance >= NotificationManager.IMPORTANCE_HIGH);
        channel.setShowBadge(showBadge);

        manager.createNotificationChannel(channel);
    }

    private void createCallChannel(NotificationManager manager) {
        if (manager.getNotificationChannel(CHANNEL_CALLS) != null) {
            return;
        }

        NotificationChannel channel = new NotificationChannel(
            CHANNEL_CALLS,
            "Calls",
            NotificationManager.IMPORTANCE_HIGH
        );

        channel.setDescription("Incoming audio and video calls");
        channel.enableLights(true);
        channel.setLightColor(Color.WHITE);
        channel.enableVibration(true);
        channel.setVibrationPattern(new long[] {0, 450, 160, 450, 650});
        channel.setSound(defaultCallRingtoneUri(), callRingtoneAttributes());
        channel.setLockscreenVisibility(Notification.VISIBILITY_PUBLIC);
        channel.setShowBadge(true);

        manager.createNotificationChannel(channel);
    }

    private AudioAttributes callRingtoneAttributes() {
        return new AudioAttributes.Builder()
            .setUsage(AudioAttributes.USAGE_NOTIFICATION_RINGTONE)
            .setContentType(AudioAttributes.CONTENT_TYPE_SONIFICATION)
            .build();
    }

    private String resolveChannelId(Map<String, String> data, boolean messageNotification, boolean callNotification) {
        String channelId = data.get("channel_id");

        String type = data.get("type");

        if (callNotification) {
            return CHANNEL_CALLS;
        }

        if (!isBlank(channelId)) {
            return channelId;
        }

        if (messageNotification) {
            return CHANNEL_MESSAGES;
        }

        if (callNotification) {
            return CHANNEL_CALLS;
        }

        if (!isBlank(type) && (type.startsWith("important.") || type.startsWith("wallet."))) {
            return CHANNEL_SYSTEM;
        }

        if (!isBlank(type) && type.startsWith("call.")) {
            return CHANNEL_CALLS;
        }

        return CHANNEL_ACTIVITY;
    }

    private boolean isMessageNotification(Map<String, String> data) {
        String type = data.get("type");
        String channelId = data.get("channel_id");

        return CHANNEL_MESSAGES.equals(channelId)
            || (!isBlank(type) && (type.startsWith("chat.") || type.contains("message")));
    }

    private boolean isCallNotification(Map<String, String> data) {
        String type = data.get("type");
        String channelId = data.get("channel_id");

        return CHANNEL_CALLS.equals(channelId)
            || CHANNEL_CALLS_LEGACY.equals(channelId)
            || (!isBlank(type) && type.startsWith("call."));
    }

    private boolean isIncomingCallNotification(Map<String, String> data) {
        return "call.incoming".equals(data.get("type"));
    }

    private boolean isCallCancelNotification(Map<String, String> data) {
        return "call.cancel".equals(data.get("type"))
            || "true".equals(data.get("cancel_notification"));
    }

    private void cancelCallNotification(Map<String, String> data) {
        NotificationManager manager = (NotificationManager) getSystemService(Context.NOTIFICATION_SERVICE);

        if (manager == null || isBlank(data.get("call_id"))) {
            return;
        }

        int notificationId = buildNotificationId(data, false);

        manager.cancel(notificationId);
        ZulorsTelecomCallManager.cancelIncomingCall(this, data.get("call_id"), notificationId);
    }

    private Uri defaultCallRingtoneUri() {
        Uri ringtoneUri = RingtoneManager.getDefaultUri(RingtoneManager.TYPE_RINGTONE);

        if (ringtoneUri != null) {
            return ringtoneUri;
        }

        return RingtoneManager.getDefaultUri(RingtoneManager.TYPE_NOTIFICATION);
    }

    private int buildNotificationId(Map<String, String> data, boolean messageNotification) {
        if (!isBlank(data.get("call_id"))) {
            return 400000000 + Math.abs(data.get("call_id").hashCode() % 100000000);
        }

        if (messageNotification && !isBlank(data.get("chat_id"))) {
            return 200000000 + Math.abs(data.get("chat_id").hashCode() % 100000000);
        }

        if (!isBlank(data.get("message_id"))) {
            return 300000000 + Math.abs(data.get("message_id").hashCode() % 100000000);
        }

        return (int) (System.currentTimeMillis() & 0x0FFFFFFF);
    }

    private int parsePositiveInt(String value) {
        if (isBlank(value)) {
            return 0;
        }

        try {
            return Math.max(0, Integer.parseInt(value));
        }
        catch (NumberFormatException ignored) {
            return 0;
        }
    }

    private String firstNonBlank(String... values) {
        for (String value : values) {
            if (!isBlank(value)) {
                return value.trim();
            }
        }

        return "";
    }

    private boolean isBlank(String value) {
        return value == null || value.trim().isEmpty();
    }
}
