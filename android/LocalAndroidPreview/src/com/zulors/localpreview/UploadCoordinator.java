package com.zulors.app;

import android.content.Context;
import android.content.SharedPreferences;
import android.os.Handler;
import android.os.Looper;
import android.webkit.CookieManager;
import org.json.JSONArray;
import org.json.JSONObject;
import java.util.Map;
import java.util.concurrent.CopyOnWriteArraySet;
import java.util.concurrent.ExecutorService;
import java.util.concurrent.Executors;
import java.util.concurrent.atomic.AtomicBoolean;

final class UploadCoordinator {
    interface Listener { void changed(); }
    private static UploadCoordinator instance;
    final Context context;
    final UploadStore store;
    final ExecutorService commands = Executors.newSingleThreadExecutor();
    final ExecutorService sync = Executors.newSingleThreadExecutor();
    final AtomicBoolean transferring = new AtomicBoolean();
    final CopyOnWriteArraySet<Listener> listeners = new CopyOnWriteArraySet<>();
    private final SharedPreferences preferences;
    private final Handler main = new Handler(Looper.getMainLooper());
    private volatile String account;
    volatile boolean enabled;
    volatile boolean accountVerified;
    volatile long accountEpoch;
    private int pendingAdmissions;
    volatile boolean visible;
    private long lastChanged;

    static synchronized UploadCoordinator get(Context context) {
        if (instance == null) instance = new UploadCoordinator(context.getApplicationContext());
        return instance;
    }

    private UploadCoordinator(Context context) {
        this.context = context;
        store = new UploadStore(context);
        preferences = context.getSharedPreferences("media-publication-account", Context.MODE_PRIVATE);
        account = preferences.getString("user_id", "");
        commands.execute(store::cleanup);
    }

    String account() { return account; }

    synchronized void pauseAccount() {
        accountEpoch++;
        enabled = false;
        accountVerified = false;
        account = "";
        preferences.edit().remove("user_id").commit();
        UploadHttp.cancel(null);
        UploadScheduling.stop(context);
        UploadNotifications.clear(context);
        changed(true);
    }

    JSONObject setAccount(JSONObject payload, long expectedEpoch) throws Exception {
        if (expectedEpoch != accountEpoch) throw new IllegalArgumentException("account_changed");
        String requested = payload.isNull("user_id") ? "" : payload.optString("user_id");
        if (requested.isEmpty()) return new JSONObject().put("user_id", JSONObject.NULL).put("enabled", false);
        UploadHttp http = new UploadHttp();
        JSONObject caps = http.capabilities(requested);
        synchronized (this) {
            if (expectedEpoch != accountEpoch) throw new IllegalArgumentException("account_changed");
            account = requested;
            enabled = caps.optBoolean("enabled");
            accountVerified = true;
            preferences.edit().putString("user_id", account).commit();
        }
        CookieManager.getInstance().flush();
        for (JSONObject row : store.rows(account)) {
            if (caps.optBoolean("enabled") || !row.getString("server_id").isEmpty() || row.getInt("create_started") == 1) {
                if (row.getString("status").equals("auth_required") || row.getString("status").equals("paused")) {
                    store.state(row.getString("uid"), row.getInt("cancel_requested") == 1 ? "cancelling" : "queued", null, 0);
                }
            }
        }
        reopen();
        changed(true);
        return caps;
    }

    JSONObject capabilities() throws Exception {
        JSONObject caps = new UploadHttp().capabilities(null);
        caps.put("supported", true).put("protocol_version", 1).put("native_picker", true).put("upload_concurrency", 2);
        return caps;
    }

    JSONObject enqueue(JSONObject payload) throws Exception {
        String owner = account;
        if (owner.isEmpty() || !accountVerified) throw new UploadHttp.Failure("auth_required", 401, 0);
        JSONObject existing = store.get(owner, UploadPolicy.uuid(payload.getString("client_uid")));
        if (existing != null) { schedule(); return store.view(existing); }
        if (!enabled) throw new IllegalArgumentException("feature_disabled");
        JSONObject result = store.enqueue(owner, payload);
        schedule();
        changed(true);
        return result;
    }

    JSONObject find(String id) throws Exception {
        String owner = account;
        for (JSONObject row : store.rows(owner)) if (id.equals(row.getString("uid")) || id.equals(row.getString("server_id"))) return row;
        throw new IllegalArgumentException("publication_unavailable");
    }

    JSONObject retry(String id) throws Exception {
        JSONObject row = find(id);
        if (UploadPolicy.terminal(row.getString("status")) || row.getInt("cancel_requested") == 1) return store.view(row);
        String uid = row.getString("uid");
        if (row.getJSONObject("snapshot").optString("status").equals("cancelled")) {
            if (!enabled) throw new IllegalArgumentException("feature_disabled");
            JSONObject replacement = store.restart(row.getString("account"), uid);
            schedule(); changed(true); return replacement;
        }
        store.flag(uid, "retry_requested", true);
        store.state(uid, "queued", null, 0);
        schedule(); changed(true);
        return store.view(store.get(account, uid));
    }

    JSONObject cancel(String id) throws Exception {
        JSONObject row = find(id);
        if (UploadPolicy.terminal(row.getString("status"))) return store.view(row);
        String uid = row.getString("uid");
        store.flag(uid, "cancel_requested", true);
        store.state(uid, "cancelling", null, 0);
        UploadHttp.cancel(uid);
        // The tombstone survives a create response lost during cancellation or process death.
        if (row.getInt("create_started") == 0 && row.getString("server_id").isEmpty()) {
            store.state(uid, "cancelled", null, 0); store.releaseFiles(uid);
        }
        UploadScheduling.sync(context);
        schedule(); changed(true);
        return store.view(store.get(account, uid));
    }

    void schedule() { main.post(() -> UploadScheduling.transfers(context, visible)); }

    synchronized void reserveAdmission() {
        pendingAdmissions++;
        UploadScheduling.transfers(context, visible);
    }

    synchronized void finishAdmission() { pendingAdmissions = Math.max(0, pendingAdmissions - 1); schedule(); }
    synchronized boolean hasAdmissions() { return pendingAdmissions > 0; }

    void reopen() {
        if (account.isEmpty()) return;
        sync.execute(() -> {
            try { syncRemote(); } catch (Exception ignored) { UploadScheduling.sync(context); }
            schedule();
        });
    }

    boolean syncRemote() throws Exception {
        String owner = account;
        if (owner.isEmpty()) return false;
        UploadHttp http = new UploadHttp();
        try { http.capabilities(owner); }
        catch (UploadHttp.Failure error) {
            if (error.status == 401) { authRequired(owner); return false; }
            throw error;
        }
        boolean retry = false;
        for (JSONObject row : store.rows(owner)) {
            if (!owner.equals(account)) return false;
            String uid = row.getString("uid"), status = row.getString("status"), id = row.getString("server_id");
            if (UploadPolicy.terminal(status) && row.getInt("sync_requested") == 0) continue;
            if (row.getLong("next_attempt") > System.currentTimeMillis()) { retry = true; continue; }
            if (row.getInt("cancel_requested") == 1 && !status.equals("published")) {
                try {
                    if (id.isEmpty() && row.getInt("create_started") == 1) {
                        JSONObject descriptor = new JSONObject(row.getJSONObject("descriptor").toString());
                        JSONArray items = descriptor.getJSONArray("items");
                        for (int i = 0; i < items.length(); i++) { items.getJSONObject(i).remove("native_file_id"); items.getJSONObject(i).remove("preview_url"); }
                        JSONObject publication = http.api("POST", UploadPolicy.API, descriptor, uid);
                        store.server(owner, uid, publication); id = publication.getString("id");
                    }
                    if (!owner.equals(account)) return false;
                    if (!id.isEmpty()) http.api("DELETE", UploadPolicy.API + "/" + id, null, uid);
                    store.state(uid, "cancelled", null, 0); store.releaseFiles(uid);
                } catch (UploadHttp.Failure error) {
                    if (error.status == 401) { authRequired(owner); return false; }
                    if (error.status == 409 && !id.isEmpty()) {
                        store.server(owner, uid, http.api("GET", UploadPolicy.API + "/" + id, null, uid));
                    } else if (error.status == 404 || error.status == 410) {
                        store.state(uid, "cancelled", null, 0); store.releaseFiles(uid);
                    } else {
                        store.state(uid, "cancelling", error.code, System.currentTimeMillis() + Math.max(60000, error.delay)); retry = true;
                    }
                }
            } else if (!id.isEmpty() && (status.equals("processing") || status.equals("publishing") || status.equals("auth_required") || row.getInt("sync_requested") == 1)) {
                try {
                    JSONObject publication = http.api("GET", UploadPolicy.API + "/" + id, null, uid);
                    store.server(owner, uid, publication);
                    store.flag(uid, "sync_requested", false);
                    UploadNotifications.publication(context, owner, store.view(store.get(owner, uid)));
                } catch (UploadHttp.Failure error) {
                    if (error.status == 401) { authRequired(owner); return false; }
                    if (error.delay > 0) { store.state(uid, status, error.code, System.currentTimeMillis() + error.delay); retry = true; }
                    else throw error;
                }
            }
        }
        changed(true);
        return retry;
    }

    void authRequired(String owner) throws Exception {
        if (owner.equals(account)) { enabled = false; UploadHttp.cancel(null); }
        for (JSONObject row : store.rows(owner)) if (!UploadPolicy.terminal(row.getString("status"))) store.state(row.getString("uid"), "auth_required", "auth_required", 0);
        changed(true);
    }

    boolean push(Map<String, String> data) {
        if (!"media_publication".equals(data.get("type"))) return false;
        String owner = data.get("user_id");
        if (owner == null || owner.isEmpty() || !owner.equals(account)) return true;
        commands.execute(() -> {
            if (!owner.equals(account)) return;
            try {
                JSONObject row = null;
                for (JSONObject candidate : store.rows(owner)) if (candidate.getString("uid").equals(data.get("client_uid")) || candidate.getString("server_id").equals(data.get("publication_id"))) { row = candidate; break; }
                if (row != null && "published".equals(data.get("status"))) {
                    store.flag(row.getString("uid"), "sync_requested", true);
                    JSONObject publication = row.getJSONObject("snapshot");
                    publication.put("id", data.get("publication_id")).put("status", "published");
                    if (data.get("url") != null && UploadPolicy.trusted(data.get("url"), BuildConfig.APP_URL)) publication.put("result", new JSONObject().put("url", data.get("url")));
                    store.server(owner, row.getString("uid"), publication);
                    UploadNotifications.publication(context, owner, store.view(store.get(owner, row.getString("uid"))));
                    changed(true);
                }
                UploadScheduling.sync(context);
            } catch (Exception ignored) { UploadScheduling.sync(context); }
        });
        return true;
    }

    synchronized void changed(boolean force) {
        long now = android.os.SystemClock.elapsedRealtime();
        if (!force && now - lastChanged < 1000) return;
        lastChanged = now;
        for (Listener listener : listeners) main.post(listener::changed);
    }
}
