package com.zulors.app;

import android.app.Notification;
import android.app.NotificationManager;
import android.content.Context;
import android.os.Build;
import android.service.notification.StatusBarNotification;

import androidx.annotation.NonNull;
import androidx.work.BackoffPolicy;
import androidx.work.Constraints;
import androidx.work.Data;
import androidx.work.ExistingWorkPolicy;
import androidx.work.NetworkType;
import androidx.work.OneTimeWorkRequest;
import androidx.work.WorkManager;
import androidx.work.Worker;
import androidx.work.WorkerParameters;

import java.util.concurrent.TimeUnit;

public final class NotificationAvatarWorker extends Worker {
    private static final String INPUT_NOTIFICATION_ID = "notification_id";
    private static final String INPUT_AVATAR_URL = "avatar_url";

    public NotificationAvatarWorker(@NonNull Context context, @NonNull WorkerParameters parameters) {
        super(context, parameters);
    }

    static void enqueue(Context context, int notificationId, String avatarUrl) {
        if (context == null || Build.VERSION.SDK_INT < Build.VERSION_CODES.N
            || avatarUrl == null || avatarUrl.trim().isEmpty() || avatarUrl.length() > 4096) {
            return;
        }

        Data input = new Data.Builder()
            .putInt(INPUT_NOTIFICATION_ID, notificationId)
            .putString(INPUT_AVATAR_URL, avatarUrl)
            .build();
        Constraints constraints = new Constraints.Builder()
            .setRequiredNetworkType(NetworkType.CONNECTED)
            .build();
        OneTimeWorkRequest request = new OneTimeWorkRequest.Builder(NotificationAvatarWorker.class)
            .setInputData(input)
            .setConstraints(constraints)
            .setBackoffCriteria(BackoffPolicy.EXPONENTIAL, 10, TimeUnit.SECONDS)
            .build();

        WorkManager.getInstance(context.getApplicationContext()).enqueueUniqueWork(
            "zulors-notification-avatar-" + notificationId,
            ExistingWorkPolicy.REPLACE,
            request
        );
    }

    @NonNull
    @Override
    public Result doWork() {
        if (Build.VERSION.SDK_INT < Build.VERSION_CODES.N) {
            return Result.success();
        }

        Context context = getApplicationContext();
        int notificationId = getInputData().getInt(INPUT_NOTIFICATION_ID, 0);
        String avatarUrl = getInputData().getString(INPUT_AVATAR_URL);
        NotificationManager manager = (NotificationManager) context.getSystemService(Context.NOTIFICATION_SERVICE);

        if (notificationId == 0 || manager == null || avatarUrl == null || !isNotificationActive(manager, notificationId)) {
            return Result.success();
        }

        android.graphics.Bitmap avatar = NotificationAvatarCache.fetchAndCache(context, avatarUrl);
        if (avatar == null) {
            return Result.success();
        }

        try {
            for (StatusBarNotification active : manager.getActiveNotifications()) {
                if (active.getId() != notificationId) {
                    continue;
                }

                Notification.Builder builder = Notification.Builder.recoverBuilder(context, active.getNotification());
                manager.notify(notificationId, builder.setLargeIcon(avatar).setOnlyAlertOnce(true).build());
                return Result.success();
            }
        }
        catch (Throwable ignored) {
            // An avatar is optional; keep the original notification visible.
        }

        return Result.success();
    }

    private boolean isNotificationActive(NotificationManager manager, int notificationId) {
        try {
            for (StatusBarNotification active : manager.getActiveNotifications()) {
                if (active.getId() == notificationId) {
                    return true;
                }
            }
        }
        catch (Throwable ignored) {
            // A removed notification should never be recreated by optional image work.
        }
        return false;
    }
}
