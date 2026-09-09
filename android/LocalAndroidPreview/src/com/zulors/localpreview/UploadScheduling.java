package com.zulors.app;

import android.app.job.JobInfo;
import android.app.job.JobScheduler;
import android.content.ComponentName;
import android.content.Context;
import android.os.Build;
import androidx.work.BackoffPolicy;
import androidx.work.Constraints;
import androidx.work.ExistingWorkPolicy;
import androidx.work.NetworkType;
import androidx.work.OneTimeWorkRequest;
import androidx.work.WorkManager;
import java.util.concurrent.TimeUnit;
import org.json.JSONObject;

final class UploadScheduling {
    private static final int JOB = 74012;
    private static final String TRANSFER = "zulors-media-transfer";
    private static final String SYNC = "zulors-media-sync";

    static void transfers(Context context, boolean visible) {
        UploadCoordinator coordinator = UploadCoordinator.get(context);
        if (coordinator.account().isEmpty() || coordinator.transferring.get()) return;
        try {
            boolean pending = coordinator.hasAdmissions();
            long total = 0;
            for (JSONObject row : coordinator.store.rows(coordinator.account())) {
                String status = row.getString("status");
                if (UploadPolicy.terminal(status) || status.equals("failed") || status.equals("auth_required") || status.equals("paused")) continue;
                if (row.getInt("cancel_requested") == 0 && !UploadStore.needsTransfer(row) && (status.equals("processing") || status.equals("publishing"))) continue;
                pending = true;
                org.json.JSONArray items = row.getJSONObject("descriptor").getJSONArray("items");
                for (int i = 0; i < items.length(); i++) total += items.getJSONObject(i).getLong("size");
            }
            if (!pending) return;
            if (Build.VERSION.SDK_INT >= 34) {
                JobScheduler scheduler = (JobScheduler) context.getSystemService(Context.JOB_SCHEDULER_SERVICE);
                if (!visible || scheduler.getPendingJob(JOB) != null) return;
                scheduler.schedule(uidt(context, total));
            } else {
                OneTimeWorkRequest request = new OneTimeWorkRequest.Builder(UploadWorker.class)
                    .setConstraints(network()).setBackoffCriteria(BackoffPolicy.EXPONENTIAL, 60, TimeUnit.SECONDS).build();
                WorkManager.getInstance(context).enqueueUniqueWork(TRANSFER, ExistingWorkPolicy.KEEP, request);
            }
        } catch (RuntimeException error) {
            // The durable queue is retained when Android refuses a background UIDT start.
            coordinator.changed(true);
        } catch (Exception ignored) { coordinator.changed(true); }
    }

    static void sync(Context context) {
        WorkManager.getInstance(context).enqueueUniqueWork(SYNC, ExistingWorkPolicy.APPEND_OR_REPLACE,
            new OneTimeWorkRequest.Builder(UploadSyncWorker.class).setConstraints(network())
                .setBackoffCriteria(BackoffPolicy.EXPONENTIAL, 60, TimeUnit.SECONDS).build());
    }

    @androidx.annotation.RequiresApi(34)
    static JobInfo uidt(Context context, long bytes) {
        return new JobInfo.Builder(JOB, new ComponentName(context, UploadJobService.class))
            .setUserInitiated(true).setRequiredNetworkType(JobInfo.NETWORK_TYPE_ANY).setPersisted(true)
            .setEstimatedNetworkBytes(0, Math.max(1, bytes)).setBackoffCriteria(60000, JobInfo.BACKOFF_POLICY_EXPONENTIAL).build();
    }

    static boolean needsContinuation(UploadCoordinator coordinator, boolean retry) {
        if (retry || coordinator.hasAdmissions()) return true;
        try {
            for (JSONObject row : coordinator.store.rows(coordinator.account())) {
                String status = row.getString("status");
                if (UploadPolicy.terminal(status) || status.equals("failed") || status.equals("auth_required") || status.equals("paused")) continue;
                if (row.getInt("cancel_requested") == 1 || UploadStore.needsTransfer(row)) return true;
            }
        } catch (Exception error) { return true; }
        return false;
    }

    static void stop(Context context) {
        if (Build.VERSION.SDK_INT >= 34) ((JobScheduler) context.getSystemService(Context.JOB_SCHEDULER_SERVICE)).cancel(JOB);
        else WorkManager.getInstance(context).cancelUniqueWork(TRANSFER);
    }

    private static Constraints network() { return new Constraints.Builder().setRequiredNetworkType(NetworkType.CONNECTED).build(); }
}
