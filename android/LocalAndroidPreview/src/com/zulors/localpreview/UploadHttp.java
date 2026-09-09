package com.zulors.app;

import android.webkit.CookieManager;
import org.json.JSONObject;
import java.io.IOException;
import java.io.RandomAccessFile;
import java.net.URLDecoder;
import java.util.Collections;
import java.util.Iterator;
import java.util.Set;
import java.util.concurrent.ConcurrentHashMap;
import java.util.concurrent.TimeUnit;
import okhttp3.Authenticator;
import okhttp3.Call;
import okhttp3.CookieJar;
import okhttp3.MediaType;
import okhttp3.OkHttpClient;
import okhttp3.Request;
import okhttp3.RequestBody;
import okhttp3.Response;
import okio.BufferedSink;

class UploadHttp {
    static final class Failure extends IOException {
        final String code;
        final int status;
        final long delay;
        Failure(String code, int status, long delay) { super(code); this.code = code; this.status = status; this.delay = delay; }
    }
    interface Progress { void bytes(long count) throws IOException; }

    // R2 has no cookie jar, authenticators, redirects or shared API interceptors.
    static OkHttpClient isolatedClient() {
        return new OkHttpClient.Builder().cookieJar(CookieJar.NO_COOKIES)
            .authenticator(Authenticator.NONE).proxyAuthenticator(Authenticator.NONE)
            .followRedirects(false).followSslRedirects(false).retryOnConnectionFailure(false)
            .connectTimeout(20, TimeUnit.SECONDS).readTimeout(60, TimeUnit.SECONDS)
            .writeTimeout(60, TimeUnit.SECONDS).build();
    }
    private static final OkHttpClient API_CLIENT = isolatedClient().newBuilder().callTimeout(90, TimeUnit.SECONDS).build();
    private static final OkHttpClient R2_CLIENT = isolatedClient();
    private static final Set<Call> ACTIVE = Collections.newSetFromMap(new ConcurrentHashMap<Call, Boolean>());
    private final String cookies;
    private final String origin;

    UploadHttp() {
        this(UploadPolicy.origin(BuildConfig.APP_URL), CookieManager.getInstance().getCookie(UploadPolicy.origin(BuildConfig.APP_URL) + "/"));
    }

    UploadHttp(String origin, String cookies) {
        this.origin = origin;
        this.cookies = cookies == null ? "" : cookies;
    }

    static void cancel(String uid) {
        for (Call call : ACTIVE) if (uid == null || uid.equals(call.request().tag(String.class))) call.cancel();
    }

    JSONObject api(String method, String path, JSONObject body, String uid) throws Exception {
        if (!path.equals(UploadPolicy.API) && !path.startsWith(UploadPolicy.API + "/")) throw new Failure("invalid_api_path", 0, 0);
        if (path.contains("..") || path.contains("?") || path.contains("#")) throw new Failure("invalid_api_path", 0, 0);
        Request.Builder request = new Request.Builder().url(origin + path).tag(String.class, uid)
            .header("Accept", "application/json").header("X-Requested-With", "XMLHttpRequest")
            .header("User-Agent", BuildConfig.USER_AGENT_SUFFIX).header("Origin", origin).header("Referer", origin + "/")
            .header("Cookie", cookies);
        if (!method.equals("GET")) {
            String csrf = cookieValue(cookies, "XSRF-TOKEN");
            if (!csrf.isEmpty()) request.header("X-XSRF-TOKEN", csrf);
            if (method.equals("POST") && path.equals(UploadPolicy.API) && uid != null) request.header("Idempotency-Key", uid);
            request.method(method, RequestBody.create(body == null ? "{}" : body.toString(), MediaType.get("application/json; charset=utf-8")));
        }
        Call call = API_CLIENT.newCall(request.build());
        ACTIVE.add(call);
        try (Response response = call.execute()) {
            if (!response.isSuccessful()) throw failure(response, false);
            if (response.code() == 204) return new JSONObject();
            if (response.body() == null) throw new IOException("empty_response");
            JSONObject envelope = new JSONObject(response.body().string());
            Object data = envelope.opt("data");
            return data instanceof JSONObject ? (JSONObject) data : envelope;
        } finally { ACTIVE.remove(call); }
    }

    JSONObject capabilities(String expected) throws Exception {
        JSONObject result = api("GET", UploadPolicy.API + "/capabilities", null, null);
        if (result.isNull("user_id") || result.optString("user_id").isEmpty()) throw new Failure("auth_required", 401, 0);
        if (expected != null && !expected.equals(result.optString("user_id"))) throw new Failure("account_changed", 401, 0);
        return result;
    }

    String put(String uid, java.io.File file, JSONObject part, Progress progress) throws Exception {
        String target = part.getString("upload_url");
        UploadPolicy.validateUploadUrl(target, BuildConfig.APP_URL);
        if (!"PUT".equals(part.optString("upload_method", "PUT"))) throw new Failure("invalid_upload_method", 0, 0);
        long start = part.getLong("start"), end = part.getLong("end");
        if (start < 0 || end <= start || end > file.length()) throw new Failure("invalid_upload_range", 0, 0);
        JSONObject headers = part.optJSONObject("upload_headers");
        Request.Builder request = new Request.Builder().url(target).tag(String.class, uid);
        if (headers != null) for (Iterator<String> keys = headers.keys(); keys.hasNext();) {
            String key = keys.next();
            if (UploadPolicy.safeUploadHeader(key)) request.header(key, headers.getString(key));
        }
        RequestBody stream = new RequestBody() {
            @Override public MediaType contentType() { return null; }
            @Override public long contentLength() { return end - start; }
            @Override public void writeTo(BufferedSink sink) throws IOException {
                try (RandomAccessFile input = new RandomAccessFile(file, "r")) {
                    input.seek(start);
                    byte[] buffer = new byte[64 * 1024];
                    long remaining = end - start;
                    progress.bytes(0);
                    while (remaining > 0) {
                        if (Thread.currentThread().isInterrupted()) throw new IOException("stopped");
                        int count = input.read(buffer, 0, (int) Math.min(buffer.length, remaining));
                        if (count < 0) throw new IOException("file_truncated");
                        sink.write(buffer, 0, count);
                        remaining -= count;
                        progress.bytes(count);
                    }
                }
            }
        };
        Call call = R2_CLIENT.newCall(request.put(stream).build());
        ACTIVE.add(call);
        try (Response response = call.execute()) {
            if (!response.isSuccessful()) throw failure(response, true);
            String etag = response.header("ETag");
            if (etag == null || etag.isEmpty()) throw new IOException("missing_etag");
            return etag;
        } finally { ACTIVE.remove(call); }
    }

    private static Failure failure(Response response, boolean r2) {
        int status = response.code();
        if (status == 429) {
            long seconds = 60;
            try { seconds = Math.max(60, Long.parseLong(response.header("Retry-After", "60"))); } catch (NumberFormatException ignored) { }
            return new Failure("waiting", status, Math.min(seconds, 86400) * 1000);
        }
        if (r2 && (status == 400 || status == 401 || status == 403 || status == 404 || status == 410)) return new Failure("upload_expired", status, 60000);
        if (status == 401 || status == 419) return new Failure("auth_required", status, 0);
        if (status == 410) return new Failure("session_expired", status, 60000);
        if (status >= 500 || status == 408) return new Failure("waiting", status, 60000);
        return new Failure("request_failed_" + status, status, 0);
    }

    private static String cookieValue(String cookies, String name) {
        for (String pair : cookies.split(";")) {
            String[] parts = pair.trim().split("=", 2);
            if (parts.length == 2 && parts[0].equals(name)) {
                try { return URLDecoder.decode(parts[1], "UTF-8"); } catch (Exception ignored) { return parts[1]; }
            }
        }
        return "";
    }
}
