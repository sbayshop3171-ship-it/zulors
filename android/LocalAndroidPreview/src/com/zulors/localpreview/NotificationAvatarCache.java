package com.zulors.app;

import android.content.Context;
import android.graphics.Bitmap;
import android.graphics.BitmapFactory;
import android.graphics.BitmapShader;
import android.graphics.Canvas;
import android.graphics.Paint;
import android.graphics.Shader;

import java.io.ByteArrayOutputStream;
import java.io.File;
import java.io.FileOutputStream;
import java.io.InputStream;
import java.net.HttpURLConnection;
import java.net.URL;
import java.security.MessageDigest;

final class NotificationAvatarCache {
    private static final int MAX_DOWNLOAD_BYTES = 2 * 1024 * 1024;
    private static final int AVATAR_SIZE_PX = 96;
    private static final long CACHE_TTL_MS = 7L * 24L * 60L * 60L * 1000L;

    private NotificationAvatarCache() {}

    static Bitmap readCachedAvatar(Context context, String avatarUrl) {
        File file = cacheFile(context, avatarUrl);

        if (file == null || !file.isFile()) {
            return null;
        }

        if (System.currentTimeMillis() - file.lastModified() > CACHE_TTL_MS) {
            file.delete();
            return null;
        }

        try {
            return BitmapFactory.decodeFile(file.getAbsolutePath());
        }
        catch (Throwable ignored) {
            return null;
        }
    }

    static Bitmap fetchAndCache(Context context, String avatarUrl) {
        HttpURLConnection connection = null;

        try {
            URL url = new URL(avatarUrl);
            if (!"https".equalsIgnoreCase(url.getProtocol()) || url.getUserInfo() != null) {
                return null;
            }

            connection = (HttpURLConnection) url.openConnection();
            connection.setConnectTimeout(1200);
            connection.setReadTimeout(1800);
            connection.setInstanceFollowRedirects(true);

            if (connection.getResponseCode() < 200 || connection.getResponseCode() >= 300
                || !"https".equalsIgnoreCase(connection.getURL().getProtocol())) {
                return null;
            }

            int contentLength = connection.getContentLength();
            if (contentLength > MAX_DOWNLOAD_BYTES) {
                return null;
            }

            byte[] bytes;
            try (InputStream input = connection.getInputStream(); ByteArrayOutputStream output = new ByteArrayOutputStream()) {
                byte[] buffer = new byte[8192];
                int total = 0;
                int read;
                while ((read = input.read(buffer)) != -1) {
                    total += read;
                    if (total > MAX_DOWNLOAD_BYTES) {
                        return null;
                    }
                    output.write(buffer, 0, read);
                }
                bytes = output.toByteArray();
            }

            Bitmap decoded = decodeSmall(bytes);
            if (decoded == null) {
                return null;
            }

            Bitmap avatar = circularAvatar(decoded);
            decoded.recycle();

            File file = cacheFile(context, avatarUrl);
            if (file == null) {
                avatar.recycle();
                return null;
            }

            File temporary = new File(file.getParentFile(), file.getName() + ".tmp");
            try (FileOutputStream output = new FileOutputStream(temporary)) {
                if (!avatar.compress(Bitmap.CompressFormat.PNG, 100, output)) {
                    temporary.delete();
                    avatar.recycle();
                    return null;
                }
                output.flush();
            }

            if (file.exists()) {
                file.delete();
            }
            if (!temporary.renameTo(file)) {
                temporary.delete();
                avatar.recycle();
                return null;
            }

            avatar.recycle();
            return readCachedAvatar(context, avatarUrl);
        }
        catch (Throwable ignored) {
            return null;
        }
        finally {
            if (connection != null) {
                connection.disconnect();
            }
        }
    }

    private static Bitmap decodeSmall(byte[] bytes) {
        BitmapFactory.Options bounds = new BitmapFactory.Options();
        bounds.inJustDecodeBounds = true;
        BitmapFactory.decodeByteArray(bytes, 0, bytes.length, bounds);

        if (bounds.outWidth <= 0 || bounds.outHeight <= 0) {
            return null;
        }

        BitmapFactory.Options options = new BitmapFactory.Options();
        options.inSampleSize = 1;
        while (bounds.outWidth / options.inSampleSize > AVATAR_SIZE_PX * 2
            || bounds.outHeight / options.inSampleSize > AVATAR_SIZE_PX * 2) {
            options.inSampleSize *= 2;
        }
        options.inPreferredConfig = Bitmap.Config.RGB_565;
        return BitmapFactory.decodeByteArray(bytes, 0, bytes.length, options);
    }

    private static Bitmap circularAvatar(Bitmap source) {
        int cropSize = Math.min(source.getWidth(), source.getHeight());
        int left = (source.getWidth() - cropSize) / 2;
        int top = (source.getHeight() - cropSize) / 2;
        Bitmap output = Bitmap.createBitmap(AVATAR_SIZE_PX, AVATAR_SIZE_PX, Bitmap.Config.ARGB_8888);
        Canvas canvas = new Canvas(output);
        Paint paint = new Paint(Paint.ANTI_ALIAS_FLAG | Paint.FILTER_BITMAP_FLAG);
        paint.setShader(new BitmapShader(source, Shader.TileMode.CLAMP, Shader.TileMode.CLAMP));
        float scale = (float) AVATAR_SIZE_PX / cropSize;
        canvas.save();
        canvas.scale(scale, scale);
        canvas.translate(-left, -top);
        canvas.drawCircle(left + cropSize / 2f, top + cropSize / 2f, cropSize / 2f, paint);
        canvas.restore();
        return output;
    }

    private static File cacheFile(Context context, String avatarUrl) {
        if (context == null || avatarUrl == null || avatarUrl.trim().isEmpty()) {
            return null;
        }

        try {
            File directory = new File(context.getCacheDir(), "notification-avatars");
            if (!directory.isDirectory() && !directory.mkdirs()) {
                return null;
            }

            byte[] digest = MessageDigest.getInstance("SHA-256").digest(avatarUrl.getBytes("UTF-8"));
            StringBuilder name = new StringBuilder(digest.length * 2);
            for (byte value : digest) {
                name.append(String.format("%02x", value & 0xff));
            }
            return new File(directory, name + ".png");
        }
        catch (Throwable ignored) {
            return null;
        }
    }
}
