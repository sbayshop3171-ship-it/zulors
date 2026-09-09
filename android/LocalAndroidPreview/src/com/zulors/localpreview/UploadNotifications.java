package com.zulors.app;

import android.app.Notification;
import android.app.NotificationChannel;
import android.app.NotificationManager;
import android.app.PendingIntent;
import android.content.Context;
import android.content.Intent;
import android.os.Build;
import android.service.notification.StatusBarNotification;
import org.json.JSONArray;
import org.json.JSONObject;

final class UploadNotifications {
    static final String CHANNEL = "zulors_media_uploads";
    static final String TAG_PREFIX = "media_publication:";
    static final int TRANSFER_ID = 74010;
    private static final int PUBLICATION_ID = 74011;
    private static long lastTransferUpdate;

    static boolean isUpload(StatusBarNotification notification) {
        return notification.getId() == TRANSFER_ID || (notification.getTag() != null && notification.getTag().startsWith(TAG_PREFIX));
    }

    static Notification transfer(Context context, JSONObject publication) {
        String uid = publication == null ? "" : publication.optString("client_uid");
        int progress = 0;
        JSONArray items = publication == null ? null : publication.optJSONArray("items");
        if (items != null && items.length() > 0) {
            long bytes = 0, total = 0;
            for (int i = 0; i < items.length(); i++) {
                JSONObject item = items.optJSONObject(i);
                if (item == null) continue;
                long size = item.optLong("size"); total += size;
                bytes += size * (UploadPolicy.uploaded(item.optString("status")) ? 100 : Math.min(100, item.optInt("progress"))) / 100;
            }
            if (total > 0) progress = (int) Math.min(100, bytes * 100 / total);
        }
        Notification.Builder builder = builder(context).setContentTitle("Uploading media").setContentText("Upload in progress")
            .setCategory(Notification.CATEGORY_PROGRESS).setOngoing(true).setProgress(100, progress, publication == null)
            .setContentIntent(open(context, BuildConfig.APP_URL, TRANSFER_ID));
        if (!uid.isEmpty()) {
            Intent intent = new Intent(context, UploadCancelReceiver.class).setAction("com.zulors.app.CANCEL_UPLOAD")
                .putExtra("client_uid", uid).putExtra("user_id", publication.optString("user_id"))
                .setData(android.net.Uri.parse("zulors-upload://cancel/" + uid));
            PendingIntent cancel = PendingIntent.getBroadcast(context, 0, intent, PendingIntent.FLAG_UPDATE_CURRENT | PendingIntent.FLAG_IMMUTABLE);
            builder.addAction(new Notification.Action.Builder(android.R.drawable.ic_menu_close_clear_cancel, "Cancel", cancel).build());
        }
        return builder.build();
    }

    static synchronized void progress(Context context, JSONObject publication) {
        long now = android.os.SystemClock.elapsedRealtime();
        if (now - lastTransferUpdate < 1000) return;
        lastTransferUpdate = now;
        try { manager(context).notify(TRANSFER_ID, transfer(context, publication)); } catch (SecurityException ignored) { }
    }

    static void publication(Context context, String owner, JSONObject publication) {
        if (owner.isEmpty() || !owner.equals(UploadCoordinator.get(context).account())) return;
        String status = publication.optString("status"), uid = publication.optString("client_uid");
        if (status.equals("cancelled")) { manager(context).cancel(TAG_PREFIX + uid, PUBLICATION_ID); return; }
        String title;
        if (status.equals("published")) title = "Published";
        else if (status.equals("processing") || status.equals("publishing")) title = "Processing media";
        else if (status.equals("failed")) title = "Upload needs attention";
        else if (status.equals("auth_required")) title = "Sign in to continue uploading";
        else if (status.equals("waiting")) title = "Waiting to upload";
        else return;
        String url = BuildConfig.APP_URL;
        JSONObject result = publication.optJSONObject("result");
        if (result != null && UploadPolicy.trusted(result.optString("url"), BuildConfig.APP_URL)) url = result.optString("url");
        Notification notification = builder(context).setContentTitle(title).setContentText("Zulors")
            .setOngoing(false).setAutoCancel(true).setContentIntent(open(context, url, uid.hashCode())).build();
        try { manager(context).notify(TAG_PREFIX + uid, PUBLICATION_ID, notification); } catch (SecurityException ignored) { }
    }

    static void endTransfer(Context context) { manager(context).cancel(TRANSFER_ID); }

    static void clear(Context context) {
        for (StatusBarNotification notification : manager(context).getActiveNotifications()) {
            if (isUpload(notification)) manager(context).cancel(notification.getTag(), notification.getId());
        }
    }

    private static Notification.Builder builder(Context context) {
        if (Build.VERSION.SDK_INT >= 26) {
            NotificationChannel channel = new NotificationChannel(CHANNEL, "Media uploads", NotificationManager.IMPORTANCE_LOW);
            channel.setSound(null, null); channel.enableVibration(false); manager(context).createNotificationChannel(channel);
        }
        return (Build.VERSION.SDK_INT >= 26 ? new Notification.Builder(context, CHANNEL) : new Notification.Builder(context))
            .setSmallIcon(R.drawable.ic_zulors_notification).setOnlyAlertOnce(true).setShowWhen(false)
            .setVisibility(Notification.VISIBILITY_PRIVATE);
    }

    private static PendingIntent open(Context context, String url, int request) {
        Intent intent = new Intent(context, MainActivity.class).putExtra(MainActivity.EXTRA_PUSH_URL, url)
            .addFlags(Intent.FLAG_ACTIVITY_SINGLE_TOP | Intent.FLAG_ACTIVITY_CLEAR_TOP);
        return PendingIntent.getActivity(context, request, intent, PendingIntent.FLAG_UPDATE_CURRENT | PendingIntent.FLAG_IMMUTABLE);
    }

    private static NotificationManager manager(Context context) { return (NotificationManager) context.getSystemService(Context.NOTIFICATION_SERVICE); }
}
