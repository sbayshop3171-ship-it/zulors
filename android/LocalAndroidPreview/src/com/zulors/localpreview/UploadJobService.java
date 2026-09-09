package com.zulors.app;

import android.app.job.JobParameters;
import android.app.job.JobService;
import android.os.Handler;
import android.os.Looper;
import androidx.annotation.RequiresApi;
import java.util.concurrent.ExecutorService;
import java.util.concurrent.Executors;

@RequiresApi(34)
public final class UploadJobService extends JobService {
    private final ExecutorService executor = Executors.newSingleThreadExecutor();
    private UploadEngine engine;
    private volatile JobParameters running;

    @Override public boolean onStartJob(JobParameters params) {
        running = params;
        setNotification(params, UploadNotifications.TRANSFER_ID, UploadNotifications.transfer(this, null), JOB_END_NOTIFICATION_POLICY_DETACH);
        engine = new UploadEngine(UploadCoordinator.get(this), publication -> UploadNotifications.progress(this, publication));
        UploadEngine task = engine;
        executor.execute(() -> {
            boolean retry = task.run();
            new Handler(Looper.getMainLooper()).post(() -> {
                if (running != params) return;
                running = null; engine = null;
                UploadCoordinator coordinator = UploadCoordinator.get(this);
                synchronized (coordinator) {
                    // Preserve the existing UIDT grant for a queued or still-committing second Publish.
                    jobFinished(params, UploadScheduling.needsContinuation(coordinator, retry));
                }
                UploadNotifications.endTransfer(this);
                if (!retry) UploadCoordinator.get(this).schedule();
            });
        });
        return true;
    }

    @Override public boolean onStopJob(JobParameters params) {
        running = null;
        if (engine != null) engine.stop();
        UploadNotifications.endTransfer(this);
        return params.getStopReason() != JobParameters.STOP_REASON_USER;
    }

    @Override public void onDestroy() {
        if (engine != null) engine.stop();
        executor.shutdown();
        super.onDestroy();
    }
}
