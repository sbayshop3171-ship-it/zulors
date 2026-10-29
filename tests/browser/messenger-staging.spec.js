import { test, expect } from '@playwright/test';

const env = process.env;
const stagingConfig = {
    baseUrl: env.MESSENGER_E2E_BASE_URL,
    username: env.MESSENGER_E2E_USER,
    password: env.MESSENGER_E2E_PASSWORD,
    sourceChatId: env.MESSENGER_E2E_SOURCE_CHAT_ID,
    destinationChatId: env.MESSENGER_E2E_DESTINATION_CHAT_ID,
    destinationChatName: env.MESSENGER_E2E_DESTINATION_CHAT_NAME,
};
const isConfigured = Object.values(stagingConfig).every(Boolean)
    && new URL(stagingConfig.baseUrl).origin === 'https://staging.zulors.com'
    && stagingConfig.sourceChatId !== stagingConfig.destinationChatId;

async function signIn(page) {
    await page.goto('/auth/login');
    const loginField = page.locator('input[name="authCreds.login"]');
    await loginField.fill(stagingConfig.username);
    await page.locator('form button[type="submit"]').click();

    const passwordField = page.locator('input[name="authCreds.password"]');
    await passwordField.waitFor({ state: 'visible' });
    await passwordField.fill(stagingConfig.password);
    await page.locator('form button[type="submit"]').click();
    await expect(page).not.toHaveURL(/\/auth\/login/);
}

test.describe('isolated Messenger staging checks', () => {
test.skip(!isConfigured, 'Configure the staging URL and a synthetic account with separate source/destination chats.');

test('opens quickly, sends once, forwards, and fits mobile viewport', async ({ page }) => {
    await signIn(page);
    await page.goto('/switch-language/en');
    await page.goto(`/messenger/c/${stagingConfig.sourceChatId}`);

    const composer = page.locator('textarea').first();
    await expect(composer).toBeVisible();

    const messageText = `staging messenger check ${Date.now()}`;
    let sendRequestCount = 0;
    await page.route('**/api/messenger/send', async (route) => {
        sendRequestCount += 1;
        await new Promise(resolve => setTimeout(resolve, 600));
        await route.continue();
    });

    const sendResponsePromise = page.waitForResponse(response =>
        response.url().includes('/api/messenger/send') && response.request().method() === 'POST'
    );
    await composer.fill(messageText);
    await composer.press('Enter');
    await composer.press('Enter');
    await sendResponsePromise;

    const sourceMessage = page.getByTestId('chat-message').filter({ hasText: messageText });
    await expect(sourceMessage).toHaveCount(1);
    expect(sendRequestCount).toBe(1);

    await sourceMessage.getByTestId('message-actions-dropdown').locator('button').click();
    await page.getByText('Forward message', { exact: true }).click();

    const forwardDialog = page.getByRole('dialog', { name: 'Forward message' });
    await expect(forwardDialog).toBeVisible();
    await forwardDialog.getByRole('option', { name: stagingConfig.destinationChatName }).click();

    const forwardResponsePromise = page.waitForResponse(response =>
        response.url().includes('/api/messenger/chat/message/') && response.request().method() === 'POST'
    );
    await forwardDialog.getByRole('button', { name: 'Forward', exact: true }).click();
    const forwardResponse = await forwardResponsePromise;
    expect(forwardResponse.ok()).toBeTruthy();

    await page.goto(`/messenger/c/${stagingConfig.destinationChatId}`);
    const forwardedMessage = page.getByTestId('chat-message').filter({ hasText: messageText });
    await expect(forwardedMessage).toHaveCount(1);
    await expect(forwardedMessage.getByText(/Forwarded from/)).toBeVisible();

    await page.setViewportSize({ width: 390, height: 844 });
    await page.goto('/switcher/device/mobile');
    await page.goto(`/messenger/c/${stagingConfig.destinationChatId}`);
    await expect(page.locator('textarea')).toBeVisible();
    const hasHorizontalOverflow = await page.evaluate(() => document.documentElement.scrollWidth > window.innerWidth + 1);
    expect(hasHorizontalOverflow).toBe(false);
});
});
