package com.zulors.app;

import org.junit.Test;

import static org.junit.Assert.assertEquals;
import static org.junit.Assert.assertFalse;
import static org.junit.Assert.assertTrue;

public class StartupNavigationPolicyTest {
    private static final String HOME = "https://zulors.com/";

    @Test
    public void normalLaunchStartsAtHomeWithoutFallback() {
        StartupNavigationPolicy.LaunchPlan plan =
            StartupNavigationPolicy.forColdStart(HOME, null, false);

        assertEquals(HOME, plan.initialUrl);
        assertFalse(plan.homeFallbackAvailable);
    }

    @Test
    public void notificationDeepLinkLoadsDirectlyAndKeepsHomeAsBackFallback() {
        String target = "https://zulors.com/explore/reels/Abc123";
        StartupNavigationPolicy.LaunchPlan plan =
            StartupNavigationPolicy.forColdStart(HOME, target, false);

        assertEquals(target, plan.initialUrl);
        assertTrue(plan.homeFallbackAvailable);
        assertTrue(StartupNavigationPolicy.shouldLoadHomeFallback(
            HOME,
            target,
            plan.homeFallbackAvailable
        ));
    }

    @Test
    public void homeNotificationAndGoogleCallbackDoNotAddHomeFallback() {
        StartupNavigationPolicy.LaunchPlan homePlan =
            StartupNavigationPolicy.forColdStart(HOME, "https://zulors.com/?source=push", false);
        StartupNavigationPolicy.LaunchPlan callbackPlan =
            StartupNavigationPolicy.forColdStart(HOME, "https://zulors.com/auth/google/callback", true);

        assertFalse(homePlan.homeFallbackAvailable);
        assertFalse(callbackPlan.homeFallbackAvailable);
    }

    @Test
    public void fallbackIsNotUsedAfterNavigationHasAlreadyReachedHome() {
        assertFalse(StartupNavigationPolicy.shouldLoadHomeFallback(
            HOME,
            HOME,
            true
        ));
        assertFalse(StartupNavigationPolicy.shouldLoadHomeFallback(
            HOME,
            "https://zulors.com/explore/reels/Abc123",
            false
        ));
    }
}
