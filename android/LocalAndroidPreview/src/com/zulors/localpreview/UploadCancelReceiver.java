package com.zulors.app;

import android.content.BroadcastReceiver;
import android.content.Context;
import android.content.Intent;

public final class UploadCancelReceiver extends BroadcastReceiver {
    @Override public void onReceive(Context context, Intent intent) {
        UploadCoordinator coordinator = UploadCoordinator.get(context);
        String owner = intent.getStringExtra("user_id"), uid = intent.getStringExtra("client_uid");
        if (owner == null || !owner.equals(coordinator.account()) || uid == null) return;
        PendingResult result = goAsync();
        coordinator.commands.execute(() -> {
            try { if (owner.equals(coordinator.account())) coordinator.cancel(uid); }
            catch (Exception ignored) { }
            finally { result.finish(); }
        });
    }
}
