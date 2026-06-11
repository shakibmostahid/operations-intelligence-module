import { expect, test } from '@playwright/test';

test.beforeEach(async ({ page }) => {
    await page.route('http://localhost:5173/**', async (route) => {
        const url = new URL(route.request().url());

        url.hostname = 'app';

        await route.continue({ url: url.toString() });
    });
});

test('user can sign in and generate an incident summary', async ({ page }) => {
    await page.goto('/login');

    await expect(page.getByRole('heading', { name: 'Welcome back' })).toBeVisible();
    await page.getByLabel('Email address').fill('super.admin@iot.com');
    await page.getByLabel('Password').fill('incident@admin');
    await page.getByRole('button', { name: 'Sign in' }).click();

    await expect(page).toHaveURL(/\/dashboard$/);
    await expect(page.getByRole('heading', { name: 'Dashboard' })).toBeVisible();

    await page.getByRole('link', { name: 'Incidents', exact: true }).click();
    await page.locator('tbody a').first().click();

    await expect(page.getByText('Generating operational analysis...')).toBeVisible();
    await expect(page.getByText('Suggested next action')).toBeVisible();
});

test('guest is redirected to login from a protected page', async ({ page }) => {
    await page.goto('/dashboard');

    await expect(page).toHaveURL(/\/login$/);
    await expect(page.getByRole('heading', { name: 'Welcome back' })).toBeVisible();
});
