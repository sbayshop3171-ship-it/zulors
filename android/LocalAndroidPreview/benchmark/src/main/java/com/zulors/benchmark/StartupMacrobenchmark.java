package com.zulors.benchmark;

import static org.junit.Assert.assertTrue;

import androidx.benchmark.macro.CompilationMode;
import androidx.benchmark.macro.StartupMode;
import androidx.benchmark.macro.StartupTimingMetric;
import androidx.benchmark.macro.TraceSectionMetric;
import androidx.benchmark.macro.junit4.MacrobenchmarkRule;
import androidx.test.ext.junit.runners.AndroidJUnit4;
import androidx.test.uiautomator.By;
import androidx.test.uiautomator.Until;

import org.junit.Rule;
import org.junit.Test;
import org.junit.runner.RunWith;

import java.util.Arrays;
import java.util.concurrent.TimeUnit;

import kotlin.Unit;

@RunWith(AndroidJUnit4.class)
public final class StartupMacrobenchmark {
    private static final String TARGET_PACKAGE = "com.zulors.app";
    private static final long USABLE_SCREEN_TIMEOUT_MS = TimeUnit.SECONDS.toMillis(30);

    @Rule
    public final MacrobenchmarkRule benchmarkRule = new MacrobenchmarkRule();

    @Test
    public void coldLaunchMeasuresInitialDisplayAndFirstUsableScreen() {
        benchmarkRule.measureRepeated(
            TARGET_PACKAGE,
            Arrays.asList(
                new StartupTimingMetric(),
                new TraceSectionMetric("ZulorsInitialScreen", TraceSectionMetric.Mode.First.INSTANCE)
            ),
            new CompilationMode.None(),
            StartupMode.COLD,
            5,
            scope -> {
                scope.pressHome();
                return Unit.INSTANCE;
            },
            scope -> {
                scope.startActivityAndWait();
                boolean usableScreenVisible = scope.getDevice().wait(
                    Until.gone(By.desc("Loading Zulors")),
                    USABLE_SCREEN_TIMEOUT_MS
                );
                assertTrue("Zulors did not reveal its first usable screen within 30 seconds", usableScreenVisible);
                return Unit.INSTANCE;
            }
        );
    }
}
