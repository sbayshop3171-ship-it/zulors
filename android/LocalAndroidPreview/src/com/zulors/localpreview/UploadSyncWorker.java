package com.zulors.app;

import android.content.Context;
import androidx.annotation.NonNull;
import androidx.work.Worker;
import androidx.work.WorkerParameters;

public final class UploadSyncWorker extends Worker {
    public UploadSyncWorker(@NonNull Context context, @NonNull WorkerParameters params) { super(context, params); }
    @NonNull @Override public Result doWork() {
        try { return UploadCoordinator.get(getApplicationContext()).syncRemote() ? Result.retry() : Result.success(); }
        catch (Exception error) { return Result.retry(); }
    }
}
