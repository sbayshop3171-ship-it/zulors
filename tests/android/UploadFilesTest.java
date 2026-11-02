package com.zulors.app;

import org.junit.Test;

import static org.junit.Assert.assertEquals;

public class UploadFilesTest {
    @Test public void videoMimeUsesTheRecognizedContainerWhenProviderReturnsCodecMime() {
        assertEquals("video/mp4", UploadFiles.normalizeContainerMime("video/hevc", "camera-clip.mp4"));
        assertEquals("video/quicktime", UploadFiles.normalizeContainerMime("application/octet-stream", "camera-clip.mov"));
        assertEquals("video/webm", UploadFiles.normalizeContainerMime(null, "edit.webm"));
    }

    @Test public void unknownVideoContainersKeepTheirProviderMime() {
        assertEquals("video/hevc", UploadFiles.normalizeContainerMime("video/hevc", "clip.unknown"));
        assertEquals("video/mp4", UploadFiles.normalizeContainerMime("video/mp4; codecs=avc1", "clip.bin"));
    }
}
