package com.zulors.app;

import android.content.ContentValues;
import android.content.Context;
import android.database.Cursor;
import android.database.sqlite.SQLiteDatabase;
import android.database.sqlite.SQLiteOpenHelper;
import org.json.JSONArray;
import org.json.JSONObject;
import java.io.File;
import java.util.ArrayList;
import java.util.List;

final class UploadStore extends SQLiteOpenHelper {
    final File directory;

    UploadStore(Context context) {
        super(context, "media-publications.db", null, 1);
        directory = new File(context.getNoBackupFilesDir(), "media-publications");
        if (!directory.isDirectory() && !directory.mkdirs()) throw new IllegalStateException("storage_unavailable");
        setWriteAheadLoggingEnabled(true);
    }

    @Override public void onConfigure(SQLiteDatabase db) {
        db.setForeignKeyConstraintsEnabled(true);
        db.execSQL("PRAGMA synchronous=FULL");
    }

    @Override public void onCreate(SQLiteDatabase db) {
        db.execSQL("CREATE TABLE publications (uid TEXT PRIMARY KEY, account TEXT NOT NULL, descriptor TEXT NOT NULL, snapshot TEXT NOT NULL, status TEXT NOT NULL, server_id TEXT NOT NULL DEFAULT '', create_started INTEGER NOT NULL DEFAULT 0, next_attempt INTEGER NOT NULL DEFAULT 0, cancel_requested INTEGER NOT NULL DEFAULT 0, retry_requested INTEGER NOT NULL DEFAULT 0, sync_requested INTEGER NOT NULL DEFAULT 0, created INTEGER NOT NULL)");
        db.execSQL("CREATE INDEX publications_account ON publications(account, created)");
        db.execSQL("CREATE TABLE files (handle TEXT PRIMARY KEY, account TEXT NOT NULL, metadata TEXT NOT NULL, publication TEXT REFERENCES publications(uid), created INTEGER NOT NULL)");
        db.execSQL("CREATE TABLE parts (publication TEXT NOT NULL REFERENCES publications(uid), item TEXT NOT NULL, generation INTEGER NOT NULL, number INTEGER NOT NULL, etag TEXT NOT NULL, PRIMARY KEY(publication,item,generation,number))");
    }

    @Override public void onUpgrade(SQLiteDatabase db, int oldVersion, int newVersion) {
        throw new IllegalStateException("unsupported_upload_schema");
    }

    synchronized void addFile(String account, JSONObject metadata) throws Exception {
        ContentValues values = new ContentValues();
        values.put("handle", metadata.getString("native_file_id"));
        values.put("account", account);
        values.put("metadata", metadata.toString());
        values.put("created", System.currentTimeMillis());
        getWritableDatabase().insertOrThrow("files", null, values);
    }

    synchronized JSONObject file(String account, String handle) throws Exception {
        UploadPolicy.uuid(handle);
        try (Cursor cursor = getReadableDatabase().query("files", new String[] {"metadata"}, "handle=? AND account=?", new String[] {handle, account}, null, null, null)) {
            if (!cursor.moveToFirst()) throw new IllegalArgumentException("file_unavailable");
            return new JSONObject(cursor.getString(0));
        }
    }

    File path(String handle) { return new File(directory, UploadPolicy.uuid(handle)); }

    synchronized JSONObject enqueue(String account, JSONObject input) throws Exception {
        String uid = UploadPolicy.uuid(input.getString("client_uid"));
        JSONObject existing = get(account, uid);
        if (existing != null) return view(existing);
        JSONObject descriptor = new JSONObject();
        String[] fields = {"client_uid", "kind", "content", "privacy", "selected_user_ids", "chat_id", "parent_id", "quoted_post_id", "marks", "clip_start_seconds", "clip_duration_seconds"};
        for (String field : fields) if (input.has(field)) descriptor.put(field, input.get(field));
        String kind = descriptor.getString("kind");
        if (!kind.equals("post") && !kind.equals("story") && !kind.equals("chat")) throw new IllegalArgumentException("invalid_kind");
        JSONArray items = input.getJSONArray("items"), saved = new JSONArray();
        if (items.length() < 1 || items.length() > 30) throw new IllegalArgumentException("invalid_items");
        SQLiteDatabase db = getWritableDatabase();
        db.beginTransaction();
        try {
            for (int i = 0; i < items.length(); i++) {
                JSONObject source = items.getJSONObject(i);
                String handle = source.getString("native_file_id");
                JSONObject item = file(account, handle);
                if (!path(handle).isFile() || path(handle).length() != item.getLong("size")) throw new IllegalArgumentException("file_unavailable");
                item.put("client_uid", UploadPolicy.uuid(source.getString("client_uid")));
                for (int j = 0; j < saved.length(); j++) {
                    if (saved.getJSONObject(j).getString("client_uid").equals(item.getString("client_uid"))) throw new IllegalArgumentException("duplicate_item");
                }
                saved.put(item);
            }
            descriptor.put("items", saved);
            JSONObject snapshot = new JSONObject(descriptor.toString()).put("id", uid).put("status", "queued");
            ContentValues values = new ContentValues();
            values.put("uid", uid); values.put("account", account); values.put("descriptor", descriptor.toString());
            values.put("snapshot", snapshot.toString()); values.put("status", "queued"); values.put("created", System.currentTimeMillis());
            db.insertOrThrow("publications", null, values);
            for (int i = 0; i < saved.length(); i++) {
                values = new ContentValues(); values.put("publication", uid);
                if (db.update("files", values, "handle=? AND account=? AND publication IS NULL", new String[] {saved.getJSONObject(i).getString("native_file_id"), account}) != 1) throw new IllegalArgumentException("file_already_enqueued");
            }
            db.setTransactionSuccessful();
        } finally { db.endTransaction(); }
        return view(get(account, uid));
    }

    synchronized JSONObject get(String account, String id) throws Exception {
        try (Cursor cursor = getReadableDatabase().query("publications", null, "account=? AND uid=?", new String[] {account, id}, null, null, null)) {
            return cursor.moveToFirst() ? row(cursor) : null;
        }
    }

    synchronized List<JSONObject> rows(String account) throws Exception {
        List<JSONObject> rows = new ArrayList<>();
        try (Cursor cursor = getReadableDatabase().query("publications", null, "account=?", new String[] {account}, null, null, "created ASC")) {
            while (cursor.moveToNext()) rows.add(row(cursor));
        }
        return rows;
    }

    private JSONObject row(Cursor cursor) throws Exception {
        JSONObject row = new JSONObject();
        for (String column : new String[] {"uid", "account", "status", "server_id"}) row.put(column, cursor.getString(cursor.getColumnIndexOrThrow(column)));
        for (String column : new String[] {"descriptor", "snapshot"}) row.put(column, new JSONObject(cursor.getString(cursor.getColumnIndexOrThrow(column))));
        for (String column : new String[] {"next_attempt", "cancel_requested", "retry_requested", "sync_requested", "create_started", "created"}) row.put(column, cursor.getLong(cursor.getColumnIndexOrThrow(column)));
        return row;
    }

    synchronized JSONArray list(String account) throws Exception {
        JSONArray result = new JSONArray();
        for (JSONObject row : rows(account)) result.put(view(row));
        return result;
    }

    JSONObject view(JSONObject row) throws Exception {
        JSONObject result = new JSONObject(row.getJSONObject("snapshot").toString());
        result.put("native_id", row.getString("uid")).put("client_uid", row.getString("uid"));
        result.put("status", row.getString("status")).put("user_id", row.getString("account"));
        result.put("next_attempt_at", row.getLong("next_attempt"));
        result.put("created_at", row.getLong("created"));
        result.put("restart_required", row.getString("status").equals("failed") && row.getJSONObject("snapshot").optString("status").equals("cancelled") && row.getInt("cancel_requested") == 0);
        long total = 0, bytes = 0;
        JSONArray items = result.optJSONArray("items");
        if (items != null) for (int i = 0; i < items.length(); i++) {
            JSONObject item = items.getJSONObject(i);
            long size = item.optLong("size"); total += size;
            bytes += size * (UploadPolicy.uploaded(item.optString("status")) ? 100 : Math.min(100, item.optInt("progress"))) / 100;
        }
        result.put("progress", total == 0 ? 0 : Math.min(100, bytes * 100 / total));
        return result;
    }

    static boolean needsTransfer(JSONObject row) throws Exception {
        JSONArray items = row.getJSONObject("snapshot").optJSONArray("items");
        if (items == null) return true;
        for (int i = 0; i < items.length(); i++) if (!UploadPolicy.uploaded(items.getJSONObject(i).optString("status"))) return true;
        return false;
    }

    synchronized void state(String uid, String status, String error, long next) throws Exception {
        ContentValues values = new ContentValues();
        values.put("status", status); values.put("next_attempt", next);
        try (Cursor cursor = getReadableDatabase().query("publications", new String[] {"snapshot"}, "uid=?", new String[] {uid}, null, null, null)) {
            if (cursor.moveToFirst()) values.put("snapshot", new JSONObject(cursor.getString(0)).put("error", error == null ? JSONObject.NULL : error).toString());
        }
        getWritableDatabase().update("publications", values, "uid=?", new String[] {uid});
    }

    synchronized void flag(String uid, String flag, boolean value) {
        if (!flag.equals("cancel_requested") && !flag.equals("retry_requested") && !flag.equals("create_started") && !flag.equals("sync_requested")) throw new IllegalArgumentException();
        ContentValues values = new ContentValues(); values.put(flag, value ? 1 : 0);
        getWritableDatabase().update("publications", values, "uid=?", new String[] {uid});
    }

    synchronized void server(String account, String uid, JSONObject publication) throws Exception {
        JSONObject row = get(account, uid);
        if (row == null) return;
        if (row.getString("status").equals("published") && !publication.optString("status").equals("published")) return;
        if (row.getString("status").equals("cancelled") && !publication.optString("status").equals("published")) return;
        JSONObject snapshot = new JSONObject(publication.toString());
        JSONArray localItems = row.getJSONObject("descriptor").getJSONArray("items");
        JSONArray remoteItems = snapshot.optJSONArray("items");
        if (remoteItems != null) for (int i = 0; i < remoteItems.length(); i++) {
            JSONObject remote = remoteItems.getJSONObject(i);
            for (int j = 0; j < localItems.length(); j++) {
                JSONObject local = localItems.getJSONObject(j);
                if (local.getString("client_uid").equals(remote.optString("client_uid"))) {
                    for (String key : new String[] {"native_file_id", "name", "mime", "preview_url"}) if (local.has(key)) remote.put(key, local.get(key));
                }
            }
        }
        String status = snapshot.getString("status");
        ContentValues values = new ContentValues(); values.put("snapshot", snapshot.toString());
        values.put("server_id", snapshot.getString("id"));
        boolean serverCancelled = status.equals("cancelled") && row.getInt("cancel_requested") == 0;
        values.put("status", serverCancelled ? "failed" : row.getInt("cancel_requested") == 1 && !UploadPolicy.terminal(status) ? "cancelling" : status);
        values.put("next_attempt", 0);
        getWritableDatabase().update("publications", values, "uid=?", new String[] {uid});
        if (status.equals("published") || (status.equals("cancelled") && row.getInt("cancel_requested") == 1)) releaseFiles(uid);
    }

    synchronized JSONObject restart(String account, String uid) throws Exception {
        JSONObject row = get(account, uid);
        if (row == null || !row.getJSONObject("snapshot").optString("status").equals("cancelled") || row.getInt("cancel_requested") == 1) throw new IllegalArgumentException("restart_unavailable");
        JSONObject descriptor = new JSONObject(row.getJSONObject("descriptor").toString());
        descriptor.put("client_uid", java.util.UUID.randomUUID().toString());
        JSONArray items = descriptor.getJSONArray("items");
        for (int i = 0; i < items.length(); i++) items.getJSONObject(i).put("client_uid", java.util.UUID.randomUUID().toString());
        SQLiteDatabase db = getWritableDatabase();
        db.beginTransaction();
        try {
            ContentValues values = new ContentValues(); values.putNull("publication");
            db.update("files", values, "publication=? AND account=?", new String[] {uid, account});
            JSONObject replacement = enqueue(account, descriptor);
            state(uid, "cancelled", null, 0);
            db.setTransactionSuccessful();
            return replacement;
        } finally { db.endTransaction(); }
    }

    synchronized void progress(String account, String uid, String item, int percent) throws Exception {
        JSONObject row = get(account, uid);
        if (row == null || row.getInt("cancel_requested") == 1) return;
        JSONObject snapshot = row.getJSONObject("snapshot");
        JSONArray items = snapshot.getJSONArray("items");
        for (int i = 0; i < items.length(); i++) if (item.equals(items.getJSONObject(i).optString("client_uid"))) items.getJSONObject(i).put("progress", percent);
        ContentValues values = new ContentValues(); values.put("snapshot", snapshot.toString());
        getWritableDatabase().update("publications", values, "uid=?", new String[] {uid});
    }

    synchronized void part(String uid, String item, int generation, int number, String etag) {
        ContentValues values = new ContentValues(); values.put("publication", uid); values.put("item", item);
        values.put("generation", generation); values.put("number", number); values.put("etag", etag);
        getWritableDatabase().insertWithOnConflict("parts", null, values, SQLiteDatabase.CONFLICT_REPLACE);
    }

    synchronized JSONArray parts(String uid, String item, int generation) throws Exception {
        JSONArray result = new JSONArray();
        try (Cursor cursor = getReadableDatabase().query("parts", new String[] {"number", "etag"}, "publication=? AND item=? AND generation=?", new String[] {uid, item, String.valueOf(generation)}, null, null, "number ASC")) {
            while (cursor.moveToNext()) result.put(new JSONObject().put("part_number", cursor.getInt(0)).put("etag", cursor.getString(1)));
        }
        return result;
    }

    synchronized void resetParts(String uid, String item) {
        getWritableDatabase().delete("parts", "publication=? AND item=?", new String[] {uid, item});
    }

    synchronized void releaseFiles(String uid) {
        try (Cursor cursor = getReadableDatabase().query("files", new String[] {"handle"}, "publication=?", new String[] {uid}, null, null, null)) {
            while (cursor.moveToNext()) path(cursor.getString(0)).delete();
        }
        getWritableDatabase().delete("files", "publication=?", new String[] {uid});
        getWritableDatabase().delete("parts", "publication=?", new String[] {uid});
    }

    synchronized void cleanup() {
        long cutoff = System.currentTimeMillis() - 86400000L;
        try (Cursor cursor = getReadableDatabase().query("files", new String[] {"handle"}, "publication IS NULL AND created<?", new String[] {String.valueOf(cutoff)}, null, null, null)) {
            while (cursor.moveToNext()) path(cursor.getString(0)).delete();
        }
        getWritableDatabase().delete("files", "publication IS NULL AND created<?", new String[] {String.valueOf(cutoff)});
        File[] files = directory.listFiles();
        if (files != null) for (File file : files) if (file.lastModified() < cutoff) {
            try (Cursor cursor = getReadableDatabase().query("files", new String[] {"handle"}, "handle=?", new String[] {file.getName()}, null, null, null)) {
                if (!cursor.moveToFirst()) file.delete();
            }
        }
    }
}
