package com.zulors.app;

import android.content.Context;
import org.json.JSONArray;
import org.json.JSONObject;
import org.junit.After;
import org.junit.Before;
import org.junit.Test;
import org.junit.runner.RunWith;
import org.robolectric.RobolectricTestRunner;
import org.robolectric.RuntimeEnvironment;
import org.robolectric.annotation.Config;
import org.robolectric.util.ReflectionHelpers;
import java.io.File;
import java.io.FileOutputStream;
import java.util.UUID;
import java.util.concurrent.CountDownLatch;
import java.util.concurrent.TimeUnit;
import java.util.concurrent.atomic.AtomicInteger;
import static org.junit.Assert.*;

@RunWith(RobolectricTestRunner.class)
@Config(manifest = Config.NONE, sdk = 28)
public class UploadEngineTest {
    private UploadCoordinator coordinator;
    private UploadStore store;
    private JSONObject descriptor;
    private String uid;
    private FakeHttp http;

    @Before public void setup() throws Exception {
        Context context = RuntimeEnvironment.getApplication();
        context.deleteDatabase("media-publications.db");
        ReflectionHelpers.setStaticField(UploadCoordinator.class, "instance", null);
        coordinator = UploadCoordinator.get(context);
        ReflectionHelpers.setField(coordinator, "account", "17");
        coordinator.enabled = true;
        store = coordinator.store;
        String handle = UUID.randomUUID().toString();
        try (FileOutputStream output = new FileOutputStream(store.path(handle))) { output.write(new byte[30]); }
        JSONObject item = new JSONObject().put("client_uid", UUID.randomUUID().toString()).put("native_file_id", handle)
            .put("name", "image.jpg").put("mime", "image/jpeg").put("type", "image").put("size", 30);
        store.addFile("17", item);
        uid = UUID.randomUUID().toString();
        descriptor = new JSONObject().put("client_uid", uid).put("kind", "post").put("items", new JSONArray().put(item));
        store.enqueue("17", descriptor);
        http = new FakeHttp(descriptor);
    }

    @After public void close() throws Exception {
        coordinator.commands.shutdown(); coordinator.sync.shutdown();
        coordinator.commands.awaitTermination(5, TimeUnit.SECONDS); coordinator.sync.awaitTermination(5, TimeUnit.SECONDS);
        store.close(); ReflectionHelpers.setStaticField(UploadCoordinator.class, "instance", null);
    }

    private boolean run() { return new UploadEngine(coordinator, publication -> {}, http).run(); }
    private void admitted() throws Exception { store.flag(uid, "create_started", true); store.server("17", uid, http.publication); }

    @Test public void disabledAdmissionDoesNotInterruptAdmittedUploads() throws Exception {
        admitted(); http.enabled = false;
        assertFalse(run());
        assertEquals(1, http.completes); assertEquals(0, http.creates);
        assertEquals("processing", store.get("17", uid).getString("status"));
        assertFalse(coordinator.transferring.get());
    }

    @Test public void disabledAdmissionRetainsLocalOnlyQueue() throws Exception {
        http.enabled = false;
        assertFalse(run());
        assertEquals(0, http.creates); assertEquals(0, http.puts.get());
        assertEquals("paused", store.get("17", uid).getString("status"));
        assertTrue(store.path(descriptor.getJSONArray("items").getJSONObject(0).getString("native_file_id")).exists());
    }

    @Test public void uploadedObjectStillAcknowledgesCompleteWithoutRetransfer() throws Exception {
        admitted(); http.alreadyUploaded = true;
        assertFalse(run());
        assertEquals(0, http.puts.get()); assertEquals(1, http.completes);
        assertEquals(7, http.completion.getInt("generation")); assertEquals(0, http.completion.getJSONArray("parts").length());
    }

    @Test public void multipartUploadsNeverExceedTwoPartsAndPersistEtags() throws Exception {
        admitted(); http.multipart = true;
        assertFalse(run());
        assertEquals(3, http.puts.get()); assertEquals(2, http.maximum.get());
        assertEquals(3, http.completion.getJSONArray("parts").length());
        assertEquals(3, store.parts(uid, descriptor.getJSONArray("items").getJSONObject(0).getString("client_uid"), 7).length());
    }

    @Test public void rateLimitDoesNotSpinAndPreservesRetryAfter() throws Exception {
        admitted(); http.resumeFailure = new UploadHttp.Failure("waiting", 429, 90000);
        long before = System.currentTimeMillis();
        assertTrue(run());
        assertEquals("waiting", store.get("17", uid).getString("status"));
        assertTrue(store.get("17", uid).getLong("next_attempt") >= before + 90000);
        assertTrue(run()); assertEquals(1, http.resumes); assertEquals(0, http.puts.get());
    }

    @Test public void expiredSessionRetriesBeforeResuming() throws Exception {
        admitted(); http.resumeFailure = new UploadHttp.Failure("session_expired", 410, 60000);
        assertTrue(run()); assertEquals(1, store.get("17", uid).getInt("retry_requested"));
        http.resumeFailure = null; store.state(uid, "queued", null, 0);
        assertFalse(run()); assertEquals(1, http.retries); assertEquals(1, http.completes);
    }

    @Test public void authenticationFailurePausesWithoutLosingFiles() throws Exception {
        admitted(); http.authFailure = true;
        assertFalse(run()); assertEquals("auth_required", store.get("17", uid).getString("status"));
        assertEquals(0, http.resumes); assertEquals(0, http.puts.get());
    }

    @Test public void cancellationResolvesLostCreateResponseWithoutUploading() throws Exception {
        store.flag(uid, "create_started", true); store.flag(uid, "cancel_requested", true); store.state(uid, "cancelling", null, 0);
        assertFalse(run()); assertEquals(1, http.creates); assertEquals(1, http.deletes); assertEquals(0, http.puts.get());
        assertEquals("cancelled", store.get("17", uid).getString("status"));
    }

    @Test public void sameJobDrainsSecondPublicationEnqueuedWhileBackgrounded() throws Exception {
        admitted();
        http.afterComplete = () -> {
            coordinator.visible = false;
            String handle = UUID.randomUUID().toString();
            try (FileOutputStream output = new FileOutputStream(store.path(handle))) { output.write(new byte[30]); }
            JSONObject item = new JSONObject(descriptor.getJSONArray("items").getJSONObject(0).toString())
                .put("native_file_id", handle).put("client_uid", UUID.randomUUID().toString());
            store.addFile("17", item);
            store.enqueue("17", new JSONObject(descriptor.toString()).put("client_uid", UUID.randomUUID().toString()).put("items", new JSONArray().put(item)));
            return null;
        };
        assertFalse(run()); assertEquals(2, http.completes); assertEquals(2, http.puts.get());
        assertEquals(2, store.list("17").length());
        for (JSONObject row : store.rows("17")) assertEquals("processing", row.getString("status"));
    }

    @Test public void continuationDoesNotDependOnActivityVisibility() throws Exception {
        coordinator.visible = false;
        assertTrue(UploadScheduling.needsContinuation(coordinator, false));
        store.state(uid, "failed", "test", 0);
        assertFalse(UploadScheduling.needsContinuation(coordinator, false));
        ReflectionHelpers.setField(coordinator, "pendingAdmissions", 1);
        assertTrue(UploadScheduling.needsContinuation(coordinator, false));
    }

    @Test public void retryConflictDiscoversServerExpiryAndRetainsRestartableFiles() throws Exception {
        admitted(); store.flag(uid, "retry_requested", true);
        http.retryConflict = true; http.publication.put("status", "cancelled").put("error", "Upload expired. Start a new publication.");
        assertFalse(run());
        assertEquals("failed", store.get("17", uid).getString("status"));
        assertTrue(store.view(store.get("17", uid)).getBoolean("restart_required"));
        assertTrue(store.path(descriptor.getJSONArray("items").getJSONObject(0).getString("native_file_id")).exists());
        assertEquals(1, http.retries);
    }

    private static final class FakeHttp extends UploadHttp {
        JSONObject publication;
        java.util.concurrent.Callable<Void> afterComplete;
        boolean enabled = true, alreadyUploaded, multipart, authFailure, retryConflict;
        Failure resumeFailure;
        int creates, deletes, resumes, retries, completes;
        JSONObject completion;
        final AtomicInteger puts = new AtomicInteger(), active = new AtomicInteger(), maximum = new AtomicInteger();
        final CountDownLatch firstPair = new CountDownLatch(2);

        FakeHttp(JSONObject descriptor) throws Exception {
            super("https://zulors.com", "");
            publication = new JSONObject(descriptor.toString()).put("id", "server-publication").put("status", "uploading");
            publication.getJSONArray("items").getJSONObject(0).put("id", "server-item").put("status", "pending");
        }

        @Override JSONObject capabilities(String expected) throws Exception {
            if (authFailure) throw new Failure("auth_required", 401, 0);
            return new JSONObject().put("user_id", "17").put("enabled", enabled);
        }

        @Override JSONObject api(String method, String path, JSONObject body, String uid) throws Exception {
            if (method.equals("DELETE")) { deletes++; return new JSONObject(publication.toString()).put("status", "cancelled"); }
            if (method.equals("POST") && path.equals(UploadPolicy.API)) {
                creates++; assertFalse(body.getJSONArray("items").getJSONObject(0).has("native_file_id"));
                if (!body.getString("client_uid").equals(publication.getString("client_uid"))) {
                    publication = new JSONObject(body.toString()).put("id", "server-" + uid).put("status", "uploading");
                    publication.getJSONArray("items").getJSONObject(0).put("id", "server-item").put("status", "pending");
                }
            }
            if (path.endsWith("/retry")) { retries++; if (retryConflict) throw new Failure("request_failed_409", 409, 0); }
            if (path.endsWith("/resume")) {
                resumes++;
                if (resumeFailure != null) throw resumeFailure;
                JSONObject result = new JSONObject().put("generation", 7).put("completed_parts", new JSONArray());
                if (alreadyUploaded) return result.put("status", "uploaded").put("upload", JSONObject.NULL);
                JSONObject upload = new JSONObject().put("upload_url", "https://storage.test/object").put("upload_method", "PUT");
                if (multipart) {
                    JSONArray parts = new JSONArray();
                    for (int i = 0; i < 3; i++) parts.put(new JSONObject(upload.toString()).put("part_number", i + 1).put("start", i * 10).put("end", (i + 1) * 10));
                    upload.put("parts", parts);
                }
                return result.put("status", "uploading").put("upload", upload);
            }
            if (path.endsWith("/complete")) {
                completes++; completion = body;
                publication.put("status", "processing"); publication.getJSONArray("items").getJSONObject(0).put("status", "uploaded");
                if (afterComplete != null) { java.util.concurrent.Callable<Void> callback = afterComplete; afterComplete = null; callback.call(); }
            }
            return new JSONObject(publication.toString());
        }

        @Override String put(String uid, File file, JSONObject part, Progress progress) throws Exception {
            puts.incrementAndGet(); int count = active.incrementAndGet(); maximum.accumulateAndGet(count, Math::max);
            try {
                if (multipart) { firstPair.countDown(); assertTrue(firstPair.await(3, TimeUnit.SECONDS)); }
                progress.bytes(part.getLong("end") - part.getLong("start"));
                return "\"etag-" + part.getInt("part_number") + "\"";
            } finally { active.decrementAndGet(); }
        }
    }
}
