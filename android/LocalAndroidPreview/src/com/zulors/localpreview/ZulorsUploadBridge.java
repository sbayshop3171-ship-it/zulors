package com.zulors.app;

import android.app.Activity;
import android.app.Dialog;
import android.content.ClipData;
import android.content.Intent;
import android.graphics.Color;
import android.net.Uri;
import android.os.Handler;
import android.os.Looper;
import android.view.Gravity;
import android.view.WindowManager;
import android.webkit.WebResourceRequest;
import android.webkit.WebResourceResponse;
import android.webkit.WebView;
import android.widget.FrameLayout;
import android.widget.MediaController;
import android.webkit.CookieManager;
import android.widget.TextView;
import android.widget.VideoView;
import androidx.webkit.JavaScriptReplyProxy;
import androidx.webkit.WebViewCompat;
import androidx.webkit.WebViewFeature;
import org.json.JSONArray;
import org.json.JSONObject;
import java.util.ArrayList;
import java.util.Collections;
import java.util.HashMap;
import java.util.List;
import java.util.Map;

final class ZulorsUploadBridge implements UploadCoordinator.Listener {
    static final int PICK_REQUEST = 8041;
    private final Activity activity;
    private final WebView webView;
    private final UploadCoordinator coordinator;
    private final Handler main = new Handler(Looper.getMainLooper());
    private volatile String topUrl = "";
    private volatile int document;
    private volatile boolean destroyed;
    private JavaScriptReplyProxy events;
    private Pick pendingPick;
    private Dialog previewDialog;

    private static final class Pick {
        final Object id;
        final String account;
        final JavaScriptReplyProxy proxy;
        final int document;
        final int limit;
        Pick(Object id, String account, JavaScriptReplyProxy proxy, int document, int limit) {
            this.id = id; this.account = account; this.proxy = proxy; this.document = document; this.limit = limit;
        }
    }

    ZulorsUploadBridge(Activity activity, WebView webView) {
        this.activity = activity; this.webView = webView;
        coordinator = UploadCoordinator.get(activity);
        if (!WebViewFeature.isFeatureSupported(WebViewFeature.WEB_MESSAGE_LISTENER)) return;
        WebViewCompat.addWebMessageListener(webView, "ZulorsUploadBridge", Collections.singleton(UploadPolicy.origin(BuildConfig.APP_URL)),
            (view, message, sourceOrigin, isMainFrame, proxy) -> {
                if (destroyed || !isMainFrame || !UploadPolicy.trusted(sourceOrigin.toString(), BuildConfig.APP_URL)
                    || !UploadPolicy.trusted(view.getUrl(), BuildConfig.APP_URL)) return;
                String data = message.getData();
                if (data == null || data.length() > 256 * 1024) return;
                try {
                    JSONObject request = new JSONObject(data);
                    Object id = request.get("id");
                    if (!(id instanceof String) && !(id instanceof Number)) return;
                    String method = request.getString("method");
                    JSONObject payload = request.optJSONObject("payload");
                    if (payload == null) payload = new JSONObject();
                    final JSONObject args = payload;
                    final int currentDocument = document;
                    events = proxy;
                    if (method.equals("pick")) { pick(id, args, proxy); return; }
                    if (method.equals("setAccount")) {
                        String account = args.isNull("user_id") ? "" : args.optString("user_id");
                        if (!account.equals(coordinator.account())) coordinator.pauseAccount();
                    }
                    final long expectedEpoch = coordinator.accountEpoch;
                    final boolean admission = method.equals("enqueue") && coordinator.enabled;
                    if (admission) coordinator.reserveAdmission();
                    coordinator.commands.execute(() -> {
                        try {
                            if (destroyed || currentDocument != document) return;
                            if (expectedEpoch != coordinator.accountEpoch) throw new IllegalArgumentException("account_changed");
                            if (!coordinator.accountVerified && (method.equals("retry") || method.equals("cancel"))) throw new UploadHttp.Failure("auth_required", 401, 0);
                            Object result;
                            switch (method) {
                                case "capabilities": result = coordinator.capabilities(); break;
                                case "setAccount": result = coordinator.setAccount(args, expectedEpoch); break;
                                case "enqueue": result = coordinator.enqueue(args.optJSONObject("publication") != null ? args.getJSONObject("publication") : args); break;
                                case "playPreview": result = openPreview(args, currentDocument, expectedEpoch); break;
                                case "list": result = coordinator.accountVerified ? coordinator.store.list(coordinator.account()) : new JSONArray(); break;
                                case "retry": result = coordinator.retry(identity(args)); break;
                                case "cancel": result = coordinator.cancel(identity(args)); break;
                                default: throw new IllegalArgumentException("unsupported_method");
                            }
                            if (expectedEpoch != coordinator.accountEpoch) throw new IllegalArgumentException("account_changed");
                            reply(proxy, id, result, null, currentDocument);
                        } catch (Exception error) { reply(proxy, id, null, error, currentDocument); }
                        finally { if (admission) coordinator.finishAdmission(); }
                    });
                } catch (Exception ignored) { /* Invalid bridge envelopes have no trustworthy request id. */ }
            });
        coordinator.listeners.add(this);
    }

    private String identity(JSONObject payload) {
        return payload.optString("native_id", payload.optString("client_uid", payload.optString("id")));
    }

    private JSONObject openPreview(JSONObject payload, int expectedDocument, long expectedEpoch) throws Exception {
        if (!coordinator.accountVerified || coordinator.account().isEmpty()) throw new UploadHttp.Failure("auth_required", 401, 0);

        Uri source;
        String handle = payload.optString("native_file_id", "");
        if (!handle.isEmpty()) {
            JSONObject metadata = coordinator.store.file(coordinator.account(), handle);
            java.io.File file = coordinator.store.path(handle);
            if (!file.isFile() || file.length() != metadata.optLong("size")) throw new IllegalArgumentException("file_unavailable");
            source = Uri.fromFile(file);
        } else {
            source = Uri.parse(payload.optString("url", ""));
            String host = source.getHost() == null ? "" : source.getHost().toLowerCase(java.util.Locale.ROOT);
            boolean allowed = UploadPolicy.trusted(source.toString(), BuildConfig.APP_URL)
                || ("https".equals(source.getScheme()) && "media.zulors.com".equals(host));
            if (!allowed || source.getUserInfo() != null || source.getFragment() != null) throw new IllegalArgumentException("preview_unavailable");
        }

        Map<String, String> headers = new HashMap<>();
        String cookie = CookieManager.getInstance().getCookie(source.toString());
        if (cookie != null && !cookie.isEmpty()) headers.put("Cookie", cookie);
        Uri finalSource = source;
        main.post(() -> showNativePreview(finalSource, headers, expectedDocument, expectedEpoch));
        return new JSONObject().put("opened", true);
    }

    private void showNativePreview(Uri source, Map<String, String> headers, int expectedDocument, long expectedEpoch) {
        if (destroyed || document != expectedDocument || expectedEpoch != coordinator.accountEpoch
            || !coordinator.accountVerified || !UploadPolicy.trusted(webView.getUrl(), BuildConfig.APP_URL)) return;
        try {
            if (previewDialog != null && previewDialog.isShowing()) previewDialog.dismiss();
            Dialog dialog = new Dialog(activity);
            FrameLayout frame = new FrameLayout(activity);
            frame.setBackgroundColor(Color.BLACK);

            VideoView video = new VideoView(activity);
            frame.addView(video, new FrameLayout.LayoutParams(FrameLayout.LayoutParams.MATCH_PARENT, FrameLayout.LayoutParams.MATCH_PARENT));
            MediaController controls = new MediaController(activity);
            controls.setAnchorView(video);
            video.setMediaController(controls);
            video.setOnPreparedListener(player -> video.start());

            TextView close = new TextView(activity);
            close.setText("×");
            close.setContentDescription("Close video preview");
            close.setTextColor(Color.WHITE);
            close.setTextSize(30);
            close.setGravity(Gravity.CENTER);
            close.setBackgroundColor(0x99000000);
            FrameLayout.LayoutParams closeParams = new FrameLayout.LayoutParams(56, 56, Gravity.TOP | Gravity.END);
            closeParams.setMargins(0, 20, 20, 0);
            frame.addView(close, closeParams);
            close.setOnClickListener(view -> dialog.dismiss());

            dialog.setContentView(frame);
            dialog.setOnDismissListener(ignored -> {
                try { video.stopPlayback(); } catch (Exception ignoredError) { }
                if (previewDialog == dialog) previewDialog = null;
            });
            dialog.show();
            if (dialog.getWindow() != null) {
                dialog.getWindow().setBackgroundDrawableResource(android.R.color.black);
                dialog.getWindow().setLayout(WindowManager.LayoutParams.MATCH_PARENT, WindowManager.LayoutParams.MATCH_PARENT);
                dialog.getWindow().addFlags(WindowManager.LayoutParams.FLAG_FULLSCREEN);
            }
            video.setOnErrorListener((player, what, extra) -> {
                android.widget.Toast.makeText(activity, "This video format is not supported by this device.", android.widget.Toast.LENGTH_LONG).show();
                dialog.dismiss();
                return true;
            });
            previewDialog = dialog;
            if (source.getScheme() != null && source.getScheme().equals("file")) video.setVideoPath(source.getPath());
            else video.setVideoURI(source, headers);
        } catch (Exception ignored) {
            android.widget.Toast.makeText(activity, "Could not open this video on the device.", android.widget.Toast.LENGTH_LONG).show();
        }
    }

    private void pick(Object id, JSONObject payload, JavaScriptReplyProxy proxy) {
        int currentDocument = document;
        try {
            if (coordinator.account().isEmpty()) throw new UploadHttp.Failure("auth_required", 401, 0);
            if (!coordinator.enabled) throw new IllegalArgumentException("feature_disabled");
            if (pendingPick != null) throw new IllegalArgumentException("picker_busy");
            if (!coordinator.visible) throw new IllegalArgumentException("app_not_visible");
            boolean multiple = payload.optBoolean("multiple", true);
            JSONArray types = payload.optJSONArray("types");
            if (types == null) types = payload.optJSONArray("kinds");
            if (types == null && payload.has("type") && !payload.optString("type").equals("media")) types = new JSONArray().put(payload.optString("type"));
            List<String> mimes = new ArrayList<>();
            boolean image = types == null, video = types == null;
            if (types != null) for (int i = 0; i < types.length(); i++) {
                image |= types.optString(i).equals("image"); video |= types.optString(i).equals("video");
            }
            if (image) { mimes.add("image/jpeg"); mimes.add("image/png"); mimes.add("image/webp"); }
            if (video) mimes.add("video/*");
            if (mimes.isEmpty()) throw new IllegalArgumentException("unsupported_media");
            Intent intent = new Intent(Intent.ACTION_OPEN_DOCUMENT).addCategory(Intent.CATEGORY_OPENABLE).setType("*/*")
                .putExtra(Intent.EXTRA_MIME_TYPES, mimes.toArray(new String[0])).putExtra(Intent.EXTRA_ALLOW_MULTIPLE, multiple)
                .addFlags(Intent.FLAG_GRANT_READ_URI_PERMISSION);
            pendingPick = new Pick(id, coordinator.account(), proxy, currentDocument, multiple ? 30 : 1);
            activity.startActivityForResult(intent, PICK_REQUEST);
        } catch (Exception error) { pendingPick = null; reply(proxy, id, null, error, currentDocument); }
    }

    boolean activityResult(int requestCode, int resultCode, Intent data) {
        if (requestCode != PICK_REQUEST) return false;
        Pick pick = pendingPick; pendingPick = null;
        if (pick == null) return true;
        List<Uri> uris = new ArrayList<>();
        if (resultCode == Activity.RESULT_OK && data != null) {
            ClipData clips = data.getClipData();
            if (clips != null) for (int i = 0; i < clips.getItemCount(); i++) uris.add(clips.getItemAt(i).getUri());
            else if (data.getData() != null) uris.add(data.getData());
        }
        coordinator.commands.execute(() -> {
            try {
                if (!pick.account.equals(coordinator.account()) || pick.document != document) throw new IllegalArgumentException("account_changed");
                if (uris.size() > pick.limit) throw new IllegalArgumentException("too_many_items");
                JSONArray items = new JSONArray();
                for (Uri uri : uris) {
                    if (!pick.account.equals(coordinator.account())) throw new IllegalArgumentException("account_changed");
                    items.put(UploadFiles.importFile(activity.getApplicationContext(), coordinator.store, pick.account, uri));
                }
                if (!pick.account.equals(coordinator.account())) throw new IllegalArgumentException("account_changed");
                reply(pick.proxy, pick.id, new JSONObject().put("items", items), null, pick.document);
            } catch (Exception error) { reply(pick.proxy, pick.id, null, error, pick.document); }
        });
        return true;
    }

    private void reply(JavaScriptReplyProxy proxy, Object id, Object result, Exception error, int expectedDocument) {
        final long epoch = coordinator.accountEpoch;
        main.post(() -> {
            if (destroyed || document != expectedDocument || epoch != coordinator.accountEpoch || !UploadPolicy.trusted(webView.getUrl(), BuildConfig.APP_URL)) return;
            try {
                JSONObject response = new JSONObject().put("id", id).put("result", result == null ? JSONObject.NULL : result).put("error", JSONObject.NULL);
                if (error != null) {
                    String code = error instanceof UploadHttp.Failure ? ((UploadHttp.Failure) error).code
                        : error instanceof IllegalArgumentException ? error.getMessage() : "native_upload_error";
                    if (code == null || !code.matches("[a-z_0-9]+")) code = "native_upload_error";
                    response.put("error", new JSONObject().put("code", code).put("message", code));
                }
                proxy.postMessage(response.toString());
            } catch (Exception ignored) { }
        });
    }

    @Override public void changed() {
        JavaScriptReplyProxy proxy = events; int currentDocument = document;
        if (proxy == null || destroyed) return;
        coordinator.commands.execute(() -> {
            try {
                final long epoch = coordinator.accountEpoch;
                String message = new JSONObject().put("event", "uploadsChanged").put("items", coordinator.accountVerified ? coordinator.store.list(coordinator.account()) : new JSONArray()).toString();
                main.post(() -> {
                    if (!destroyed && currentDocument == document && epoch == coordinator.accountEpoch && UploadPolicy.trusted(webView.getUrl(), BuildConfig.APP_URL)) {
                        try { proxy.postMessage(message); } catch (Exception ignored) { }
                    }
                });
            } catch (Exception ignored) { }
        });
    }

    void pageStarted(String url) { topUrl = url; document++; events = null; }
    void resumed() {
        coordinator.visible = true;
        coordinator.reopen();
        // Let Android finish bringing the activity window to the foreground before
        // retrying a UIDT schedule that may have been rejected during onResume().
        main.postDelayed(() -> {
            if (!destroyed && coordinator.visible) coordinator.schedule();
        }, 750);
    }
    void paused() { coordinator.visible = false; }
    WebResourceResponse intercept(WebResourceRequest request) { return UploadFiles.preview(coordinator.store, coordinator.accountVerified ? coordinator.account() : "", topUrl, request); }
    void destroy() {
        destroyed = true; coordinator.visible = false; coordinator.listeners.remove(this); events = null; pendingPick = null;
        main.post(() -> { if (previewDialog != null) { previewDialog.dismiss(); previewDialog = null; } });
    }
}
