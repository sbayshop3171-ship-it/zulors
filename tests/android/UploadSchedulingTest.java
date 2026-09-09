package com.zulors.app;

import android.app.job.JobInfo;
import org.junit.Test;
import org.junit.runner.RunWith;
import org.robolectric.RobolectricTestRunner;
import org.robolectric.RuntimeEnvironment;
import org.robolectric.annotation.Config;
import static org.junit.Assert.*;

@RunWith(RobolectricTestRunner.class)
@Config(manifest = Config.NONE, sdk = 34)
public class UploadSchedulingTest {
    @Test public void android14UidtAcceptsPersistedJobWithExponentialBackoff() {
        JobInfo job = UploadScheduling.uidt(RuntimeEnvironment.getApplication(), 2048);
        assertTrue(job.isUserInitiated());
        assertTrue(job.isPersisted());
        assertEquals(JobInfo.BACKOFF_POLICY_EXPONENTIAL, job.getBackoffPolicy());
        assertEquals(60000, job.getInitialBackoffMillis());
        assertEquals(2048, job.getEstimatedNetworkUploadBytes());
        assertNotNull(job.getRequiredNetwork());
    }
}
