import { defineConfig, devices } from '@playwright/test';

export default defineConfig({
    testDir: './tests/browser',
    fullyParallel: false,
    forbidOnly: Boolean(process.env.CI),
    retries: process.env.CI ? 1 : 0,
    reporter: process.env.CI ? 'github' : 'list',
    timeout: 90_000,
    expect: { timeout: 15_000 },
    outputDir: process.env.PLAYWRIGHT_OUTPUT_DIR || '/tmp/zulors-playwright-results',
    use: {
        baseURL: process.env.MESSENGER_E2E_BASE_URL,
        trace: 'retain-on-failure',
        screenshot: 'only-on-failure',
        video: 'retain-on-failure',
    },
    projects: [
        {
            name: 'chromium',
            use: { ...devices['Desktop Chrome'] },
        },
    ],
});
