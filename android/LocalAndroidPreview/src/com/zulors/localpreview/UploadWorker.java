package com.zulors.app;

import android.content.Context;
import android.content.pm.ServiceInfo;
import android.os.Build;
import androidx.annotation.NonNull;
import androidx.work.ForegroundInfo;
import androidx.work.Worker;
import androidx.work.WorkerParameters;

public final class UploadWorker extends Worker {
    private volatile UploadEngine engine;
    public UploadWorker(@NonNull Context context, @NonNull WorkerParameters params) { super(context, params); }

    @NonNull @Override public Result doWork() {
        if (Build.VERSION.SDK_INT >= 34) return Result.success();
        Context context = getApplicationContext();
        try {
            ForegroundInfo foreground = Build.VERSION.SDK_INT >= 29
                ? new ForegroundInfo(UploadNotifications.TRANSFER_ID, UploadNotifications.transfer(context, null), ServiceInfo.FOREGROUND_SERVICE_TYPE_DATA_SYNC)
                : new ForegroundInfo(UploadNotifications.TRANSFER_ID, UploadNotifications.transfer(context, null));
            setForegroundAsync(foreground).get();
            engine = new UploadEngine(UploadCoordinator.get(context), publication -> UploadNotifications.progress(context, publication));
            if (isStopped()) { engine.stop(); return Result.retry(); }
            boolean retry = engine.run();
            return UploadScheduling.needsContinuation(UploadCoordinator.get(context), retry) ? Result.retry() : Result.success();
        } catch (Exception error) { return Result.retry(); }
        finally {
            new android.os.Handler(android.os.Looper.getMainLooper()).postDelayed(() -> UploadCoordinator.get(context).schedule(), 1000);
        }
    }

    @Override public void onStopped() { if (engine != null) engine.stop(); }
}
