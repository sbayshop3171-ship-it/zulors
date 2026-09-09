package com.zulors.app;

import org.json.JSONArray;
import org.json.JSONObject;
import java.io.File;
import java.io.IOException;
import java.util.HashSet;
import java.util.Set;
import java.util.concurrent.ExecutorCompletionService;
import java.util.concurrent.ExecutorService;
import java.util.concurrent.Executors;
import java.util.concurrent.TimeUnit;
import java.util.concurrent.atomic.AtomicBoolean;
import java.util.concurrent.atomic.AtomicLong;

final class UploadEngine {
    interface Progress { void update(JSONObject publication); }
    private final UploadCoordinator coordinator;
    private final UploadStore store;
    private final String owner;
    private final AtomicBoolean stopped = new AtomicBoolean();
    private volatile String activeUid;
    private final Progress progress;
    private UploadHttp http;

    UploadEngine(UploadCoordinator coordinator, Progress progress) {
        this(coordinator, progress, null);
    }

    UploadEngine(UploadCoordinator coordinator, Progress progress, UploadHttp http) {
        this.coordinator = coordinator; store = coordinator.store; owner = coordinator.account(); this.progress = progress;
        this.http = http;
    }

    void stop() { stopped.set(true); String uid = activeUid; if (uid != null) UploadHttp.cancel(uid); }

    private void check(String uid, boolean cancelling) throws Exception {
        if (stopped.get() || !owner.equals(coordinator.account()) || Thread.currentThread().isInterrupted()) throw new IOException("stopped");
        JSONObject row = store.get(owner, uid);
        if (row == null || (!cancelling && row.getInt("cancel_requested") == 1)) throw new IOException("cancelled");
    }

    private JSONObject api(String uid, String method, String path, JSONObject body, boolean cancelling) throws Exception {
        check(uid, cancelling);
        return http.api(method, path, body, uid);
    }

    boolean run() {
        if (owner.isEmpty() || !coordinator.transferring.compareAndSet(false, true)) return false;
        boolean needsRetry = false;
        try {
            if (http == null) http = new UploadHttp();
            JSONObject capabilities = http.capabilities(owner);
            Set<String> attempted = new HashSet<>();
            while (!stopped.get() && owner.equals(coordinator.account())) {
                JSONObject next = null;
                for (JSONObject row : store.rows(owner)) {
                    String status = row.getString("status"), uid = row.getString("uid");
                    if (!capabilities.optBoolean("enabled") && row.getString("server_id").isEmpty() && row.getInt("create_started") == 0 && row.getInt("cancel_requested") == 0) {
                        if (!UploadPolicy.terminal(status)) store.state(uid, "paused", "feature_disabled", 0);
                        continue;
                    }
                    if (attempted.contains(uid) || UploadPolicy.terminal(status) || status.equals("failed") || status.equals("auth_required") || status.equals("paused")) continue;
                    if (row.getInt("cancel_requested") == 0 && !UploadStore.needsTransfer(row) && (status.equals("processing") || status.equals("publishing"))) continue;
                    if (row.getLong("next_attempt") > System.currentTimeMillis()) { needsRetry = true; continue; }
                    next = row; break;
                }
                if (next == null) break;
                String uid = next.getString("uid"); activeUid = uid; attempted.add(uid);
                try { transfer(next); }
                catch (Exception error) {
                    if (stopped.get() || !owner.equals(coordinator.account())) return true;
                    JSONObject latest = store.get(owner, uid);
                    if (UploadPolicy.terminal(latest.getString("status"))) continue;
                    boolean cancelling = latest.getInt("cancel_requested") == 1;
                    if (cancelling && next.getInt("cancel_requested") == 0) { attempted.remove(uid); continue; }
                    if (error instanceof UploadHttp.Failure) {
                        UploadHttp.Failure failure = (UploadHttp.Failure) error;
                        if (failure.code.equals("auth_required") || failure.code.equals("account_changed")) { coordinator.authRequired(owner); return false; }
                        if (failure.status == 409 && !latest.getString("server_id").isEmpty()) {
                            JSONObject remote = api(uid, "GET", UploadPolicy.API + "/" + latest.getString("server_id"), null, cancelling);
                            store.server(owner, uid, remote);
                            if (UploadPolicy.terminal(remote.optString("status"))) {
                                store.flag(uid, "retry_requested", false);
                                changed(uid, true);
                                continue;
                            }
                        }
                        if (failure.code.equals("session_expired")) store.flag(uid, "retry_requested", true);
                        long delay = failure.status == 409 ? 60000 : failure.delay;
                        store.state(uid, cancelling ? "cancelling" : delay > 0 ? "waiting" : "failed", failure.code, delay > 0 ? System.currentTimeMillis() + delay : 0);
                        needsRetry |= delay > 0;
                    } else if (error instanceof IOException) {
                        store.state(uid, cancelling ? "cancelling" : "waiting", "network_unavailable", System.currentTimeMillis() + 60000); needsRetry = true;
                    } else store.state(uid, "failed", "invalid_upload_response", 0);
                }
                changed(uid, true);
            }
        } catch (UploadHttp.Failure error) {
            try {
                if (error.status == 401) coordinator.authRequired(owner);
                else needsRetry = error.delay > 0;
            } catch (Exception ignored) { needsRetry = true; }
        } catch (Exception error) { needsRetry = true; }
        finally { activeUid = null; coordinator.transferring.set(false); coordinator.changed(true); coordinator.schedule(); }
        return needsRetry;
    }

    private void transfer(JSONObject row) throws Exception {
        String uid = row.getString("uid"), id = row.getString("server_id");
        boolean cancelling = row.getInt("cancel_requested") == 1;
        if (id.isEmpty()) {
            if (cancelling && row.getInt("create_started") == 0) {
                store.state(uid, "cancelled", null, 0); store.releaseFiles(uid); return;
            }
            JSONObject descriptor = new JSONObject(row.getJSONObject("descriptor").toString());
            JSONArray items = descriptor.getJSONArray("items");
            for (int i = 0; i < items.length(); i++) { items.getJSONObject(i).remove("native_file_id"); items.getJSONObject(i).remove("preview_url"); }
            store.flag(uid, "create_started", true);
            JSONObject publication = api(uid, "POST", UploadPolicy.API, descriptor, cancelling);
            store.server(owner, uid, publication);
            id = publication.getString("id");
        }
        String base = UploadPolicy.API + "/" + id;
        if (cancelling || store.get(owner, uid).getInt("cancel_requested") == 1) {
            try { api(uid, "DELETE", base, null, true); }
            catch (UploadHttp.Failure error) { if (error.status != 404 && error.status != 410) throw error; }
            store.state(uid, "cancelled", null, 0); store.releaseFiles(uid); return;
        }
        if (row.getInt("retry_requested") == 1) {
            store.server(owner, uid, api(uid, "POST", base + "/retry", new JSONObject(), false));
            store.flag(uid, "retry_requested", false);
        } else store.server(owner, uid, api(uid, "GET", base, null, false));
        JSONObject current = store.get(owner, uid);
        String status = current.getString("status");
        if (UploadPolicy.terminal(status) || status.equals("failed") || (!UploadStore.needsTransfer(current) && (status.equals("processing") || status.equals("publishing")))) return;
        store.state(uid, "uploading", null, 0); changed(uid, true);
        JSONArray local = row.getJSONObject("descriptor").getJSONArray("items");
        for (int i = 0; i < local.length(); i++) {
            JSONObject item = local.getJSONObject(i), remote = null;
            JSONArray serverItems = store.get(owner, uid).getJSONObject("snapshot").getJSONArray("items");
            for (int j = 0; j < serverItems.length(); j++) if (item.getString("client_uid").equals(serverItems.getJSONObject(j).optString("client_uid"))) remote = serverItems.getJSONObject(j);
            if (remote == null) throw new IllegalArgumentException("missing_item");
            if (UploadPolicy.uploaded(remote.optString("status"))) continue;
            String path = base + "/items/" + remote.getString("id");
            JSONObject resume = api(uid, "POST", path + "/resume", new JSONObject(), false);
            if ("uploaded".equals(resume.optString("status")) && resume.isNull("upload")) {
                store.server(owner, uid, api(uid, "POST", path + "/complete", new JSONObject().put("generation", resume.getInt("generation")).put("parts", new JSONArray()), false));
                continue;
            }
            if (UploadPolicy.uploaded(resume.optString("status"))) continue;
            if (resume.isNull("upload")) throw new IOException("upload_not_ready");
            int generation = resume.getInt("generation");
            JSONArray completed = upload(uid, item, resume, generation);
            JSONObject complete = new JSONObject().put("generation", generation).put("parts", completed);
            store.server(owner, uid, api(uid, "POST", path + "/complete", complete, false));
            changed(uid, true);
        }
        JSONObject publication = api(uid, "GET", base, null, false);
        store.server(owner, uid, publication);
        // No polling or foreground lifetime is needed after all bytes are acknowledged.
        if (publication.optString("status").equals("uploading")) store.state(uid, "processing", null, 0);
        UploadNotifications.publication(coordinator.context, owner, store.view(store.get(owner, uid)));
    }

    private JSONArray upload(String uid, JSONObject item, JSONObject resume, int generation) throws Exception {
        String itemUid = item.getString("client_uid");
        File file = store.path(item.getString("native_file_id"));
        if (!file.isFile() || file.length() != item.getLong("size")) throw new IllegalArgumentException("file_unavailable");
        JSONObject upload = resume.getJSONObject("upload");
        JSONArray parts = upload.optJSONArray("parts");
        boolean multipart = parts != null && parts.length() > 0;
        if (!multipart) parts = new JSONArray().put(new JSONObject(upload.toString()).put("part_number", 1).put("start", 0).put("end", file.length()));
        store.resetParts(uid, itemUid);
        Set<Integer> done = new HashSet<>();
        JSONArray remoteParts = resume.optJSONArray("completed_parts");
        if (multipart && remoteParts != null) for (int i = 0; i < remoteParts.length(); i++) {
            JSONObject part = remoteParts.getJSONObject(i); done.add(part.getInt("part_number"));
            store.part(uid, itemUid, generation, part.getInt("part_number"), part.getString("etag"));
        }
        AtomicLong bytes = new AtomicLong(); AtomicLong lastProgress = new AtomicLong();
        long expectedStart = 0;
        for (int i = 0; i < parts.length(); i++) {
            JSONObject part = parts.getJSONObject(i);
            if (part.getLong("start") != expectedStart || part.getLong("end") <= expectedStart || part.getInt("part_number") != i + 1) throw new IllegalArgumentException("invalid_parts");
            expectedStart = part.getLong("end");
            if (done.contains(part.getInt("part_number"))) bytes.addAndGet(part.getLong("end") - part.getLong("start"));
        }
        if (expectedStart != file.length()) throw new IllegalArgumentException("invalid_parts");
        ExecutorService pool = Executors.newFixedThreadPool(2);
        ExecutorCompletionService<Void> results = new ExecutorCompletionService<>(pool);
        int submitted = 0;
        try {
            for (int i = 0; i < parts.length(); i++) {
                JSONObject part = parts.getJSONObject(i);
                if (done.contains(part.getInt("part_number"))) continue;
                submitted++;
                results.submit(() -> {
                    check(uid, false);
                    String etag = http.put(uid, file, part, count -> {
                        try {
                            if (count == 0) check(uid, false);
                            if (stopped.get() || !owner.equals(coordinator.account()) || Thread.currentThread().isInterrupted()) throw new IOException("stopped");
                            long loaded = bytes.addAndGet(count), now = android.os.SystemClock.elapsedRealtime(), previous = lastProgress.get();
                            if (now - previous >= 1000 && lastProgress.compareAndSet(previous, now)) {
                                store.progress(owner, uid, itemUid, (int) Math.min(99, loaded * 100 / file.length())); changed(uid, false);
                            }
                        } catch (Exception error) { throw new IOException("stopped", error); }
                    });
                    store.part(uid, itemUid, generation, part.getInt("part_number"), etag);
                    return null;
                });
            }
            for (int i = 0; i < submitted; i++) {
                try { results.take().get(); }
                catch (java.util.concurrent.ExecutionException error) {
                    if (error.getCause() instanceof Exception) throw (Exception) error.getCause();
                    throw new IOException("upload_failed", error);
                }
            }
        } finally {
            pool.shutdownNow();
            UploadHttp.cancel(uid);
            while (!pool.awaitTermination(1, TimeUnit.SECONDS)) { UploadHttp.cancel(uid); }
        }
        store.progress(owner, uid, itemUid, 100);
        return multipart ? store.parts(uid, itemUid, generation) : new JSONArray();
    }

    private void changed(String uid, boolean force) throws Exception {
        coordinator.changed(force);
        JSONObject publication = store.view(store.get(owner, uid));
        progress.update(publication);
        UploadNotifications.publication(coordinator.context, owner, publication);
    }
}
