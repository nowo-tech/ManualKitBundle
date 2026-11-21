import { test, expect, type Page } from '@playwright/test';

/** 1.13 — named audiences (developer) and the customer portal root (/portal/help, its own firewall and users). */
async function signIn(page: Page, user: string): Promise<void> {
  await page.goto('/en/login');
  await page.getByLabel('Username').fill(user);
  await page.getByLabel('Password').fill(user);
  await page.getByRole('button', { name: 'Sign in' }).click();
  await expect(page.getByTestId('manual')).toBeVisible();
}

test('a developer reads the API chapter and its block; a member never sees it', async ({ page }) => {
  await signIn(page, 'dev');
  await page.getByTestId('manual-nav-04-api').click();
  await expect(page).toHaveURL(/\/en\/manual\/04-api$/);
  await expect(page.locator('h2#rotating-tokens')).toBeVisible();
  await expect(page.getByTestId('manual-nav-portal-home')).toHaveCount(0);
});

test('a member gets a 404 for the developer and portal chapters', async ({ page }) => {
  await signIn(page, 'demo');
  await expect(page.getByTestId('manual-nav-04-api')).toHaveCount(0);
  expect((await page.goto('/en/manual/04-api'))?.status()).toBe(404);
  expect((await page.goto('/en/manual/portal-home'))?.status()).toBe(404);
});

test.describe('customer portal', () => {
  test.use({ httpCredentials: { username: 'contact', password: 'contact' } });

  test('a contact reads only customer chapters under /portal/help', async ({ page }) => {
    expect((await page.goto('/portal/help'))?.status()).toBe(200);
    await expect(page.getByRole('heading', { level: 1, name: 'Customer help' })).toBeVisible();
    await expect(page.locator('h2#staff-notes')).toHaveCount(0);
    await expect(page.locator('.manual-prose a[href*="01-getting-started"]')).toHaveCount(0);
    expect((await page.goto('/portal/help/01-getting-started'))?.status()).toBe(404);
    expect((await page.goto('/portal/help/04-api'))?.status()).toBe(404);
  });
});
