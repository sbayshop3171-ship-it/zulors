package com.zulors.app;

import android.app.Notification;
import android.app.NotificationChannel;
import android.app.NotificationManager;
import android.app.PendingIntent;
import android.app.Service;
import android.content.Context;
import android.content.Intent;
import android.content.pm.ServiceInfo;
import android.os.Build;
import android.os.IBinder;
import android.os.PowerManager;
import android.util.Log;

public class ZulorsCallForegroundService extends Service {
    public static final String ACTION_START = "com.zulors.app.action.START_CALL_FOREGROUND";
    public static final String ACTION_STOP = "com.zulors.app.action.STOP_CALL_FOREGROUND";

    private static final String TAG = "ZulorsCallService";
    private static final String CHANNEL_ID = "zulors_ongoing_call";
    private static final int NOTIFICATION_ID = 7341;
    private static final long CALL_WAKE_LOCK_TIMEOUT_MS = 3L * 60L * 60L * 1000L;

    private PowerManager.WakeLock partialWakeLock;

    @Override
    public void onCreate() {
        super.onCreate();
        ensureNotificationChannel();
    }

    @Override
    public int onStartCommand(Intent intent, int flags, int startId) {
        String action = intent == null ? ACTION_START : intent.getAction();

        if (ACTION_STOP.equals(action)) {
            releasePartialWakeLock();
            stopForeground(true);
            stopSelf();

            return START_NOT_STICKY;
        }

        try {
            Notification notification = buildCallNotification();

            if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.Q) {
                startForeground(
                    NOTIFICATION_ID,
                    notification,
                    ServiceInfo.FOREGROUND_SERVICE_TYPE_MICROPHONE
                        | ServiceInfo.FOREGROUND_SERVICE_TYPE_PHONE_CALL
                );
            }
            else {
                startForeground(NOTIFICATION_ID, notification);
            }

            acquirePartialWakeLock();
        }
        catch (Throwable exception) {
            Log.w(TAG, "Unable to keep the call foreground service alive.", exception);
            releasePartialWakeLock();
            stopSelf();

            return START_NOT_STICKY;
        }

        return START_STICKY;
    }

    @Override
    public IBinder onBind(Intent intent) {
        return null;
    }

    @Override
    public void onDestroy() {
        releasePartialWakeLock();
        super.onDestroy();
    }

    private void acquirePartialWakeLock() {
        if (partialWakeLock != null && partialWakeLock.isHeld()) {
            return;
        }

        PowerManager powerManager = (PowerManager) getSystemService(Context.POWER_SERVICE);

        if (powerManager == null) {
            return;
        }

        partialWakeLock = powerManager.newWakeLock(PowerManager.PARTIAL_WAKE_LOCK, "Zulors:CallPartialWakeLock");
        partialWakeLock.setReferenceCounted(false);
        partialWakeLock.acquire(CALL_WAKE_LOCK_TIMEOUT_MS);
    }

    private void releasePartialWakeLock() {
        try {
            if (partialWakeLock != null && partialWakeLock.isHeld()) {
                partialWakeLock.release();
            }
        }
        catch (Throwable exception) {
            Log.w(TAG, "Unable to release call partial wake lock.", exception);
        }

        partialWakeLock = null;
    }

    private void ensureNotificationChannel() {
        if (Build.VERSION.SDK_INT < Build.VERSION_CODES.O) {
            return;
        }

        NotificationManager notificationManager = (NotificationManager) getSystemService(Context.NOTIFICATION_SERVICE);

        if (notificationManager == null || notificationManager.getNotificationChannel(CHANNEL_ID) != null) {
            return;
        }

        NotificationChannel channel = new NotificationChannel(
            CHANNEL_ID,
            "Ongoing calls",
            NotificationManager.IMPORTANCE_LOW
        );
        channel.setDescription("Keeps Zulors calls connected while the screen is off.");
        channel.setSound(null, null);
        notificationManager.createNotificationChannel(channel);
    }

    private Notification buildCallNotification() {
        Intent launchIntent = getPackageManager().getLaunchIntentForPackage(getPackageName());

        if (launchIntent == null) {
            launchIntent = new Intent(this, MainActivity.class);
        }

        launchIntent.addFlags(Intent.FLAG_ACTIVITY_SINGLE_TOP | Intent.FLAG_ACTIVITY_CLEAR_TOP);

        int pendingIntentFlags = PendingIntent.FLAG_UPDATE_CURRENT;

        if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.M) {
            pendingIntentFlags |= PendingIntent.FLAG_IMMUTABLE;
        }

        PendingIntent contentIntent = PendingIntent.getActivity(this, 0, launchIntent, pendingIntentFlags);
        Notification.Builder builder = Build.VERSION.SDK_INT >= Build.VERSION_CODES.O
            ? new Notification.Builder(this, CHANNEL_ID)
            : new Notification.Builder(this);

        builder.setSmallIcon(R.drawable.ic_zulors_notification)
            .setContentTitle("Zulors call in progress")
            .setContentText("Tap to return to your call.")
            .setContentIntent(contentIntent)
            .setCategory(Notification.CATEGORY_CALL)
            .setOngoing(true)
            .setShowWhen(false);

        if (Build.VERSION.SDK_INT < Build.VERSION_CODES.O) {
            builder.setPriority(Notification.PRIORITY_LOW);
        }

        return builder.build();
    }
}
