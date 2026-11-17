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

test('an old chapter link with a moved anchor lands on the new chapter and anchor (1.11)', async ({ page }) => {
  await signIn(page, 'demo');
  // 301 to /en/manual/03-guide?moved_from=00-introduction, then js/manual-anchors.js maps #navigation.
  await page.goto('/en/manual/00-introduction#navigation');
  await expect(page).toHaveURL(/\/en\/manual\/01-getting-started#moving-around$/);
  await expect(page.locator('h1')).toHaveText('Getting started');

  // A heading renamed inside an existing chapter: #navigation is no longer on the page.
  await page.goto('/en/manual/01-getting-started#navigation');
  await expect(page).toHaveURL(/\/en\/manual\/01-getting-started#moving-around$/);

  // A renamed chapter: the fragment travels with the 301, and the marker never stays in the address bar.
  await page.goto('/en/manual/01-start#first-steps');
  await expect(page).toHaveURL(/\/en\/manual\/01-getting-started#first-steps$/);
});
