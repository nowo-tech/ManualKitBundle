import { test, expect, type Page } from '@playwright/test';

/** REQ-DEMO-013 — the manual in the demo: sign-in, chapters, the figure lightbox and the Spanish translation. */
async function signIn(page: Page, user: string): Promise<void> {
  await page.goto('/en/login');
  await page.getByLabel('Username').fill(user);
  await page.getByLabel('Password').fill(user);
  await page.getByRole('button', { name: 'Sign in' }).click();
  await expect(page.getByTestId('manual')).toBeVisible();
}

test('a member reads the manual and zooms a figure', async ({ page }) => {
  await signIn(page, 'demo');
  await page.getByTestId('manual-nav-01-getting-started').click();
  await expect(page.locator('h1')).toHaveText('Getting started');
  await expect(page.getByTestId('manual-nav-02-admin')).toHaveCount(0);

  await page.locator('a.manual-zoom').first().click();
  await expect(page.getByTestId('manual-lightbox')).toBeVisible();
  await page.keyboard.press('Escape');
  await expect(page.getByTestId('manual-lightbox')).toBeHidden();
});

test('an administrator reads the Spanish manual with a notice for the untranslated chapter', async ({ page }) => {
  await signIn(page, 'admin');
  await page.goto('/es/manual/02-admin');
  await expect(page.getByTestId('manual-translation-notice')).toBeVisible();
  await page.goto('/es/manual/01-getting-started#first-steps');
  await expect(page.locator('h2#first-steps')).toBeVisible();
});
