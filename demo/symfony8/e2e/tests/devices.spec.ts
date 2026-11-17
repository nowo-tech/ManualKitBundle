import { test, expect, type Page } from '@playwright/test';

/** 1.12 — desktop / phone views: the switch, the viewport default, the stored choice and a search hit's view. */
async function signIn(page: Page): Promise<void> {
  await page.goto('/en/login');
  await page.getByLabel('Username').fill('demo');
  await page.getByLabel('Password').fill('demo');
  await page.getByRole('button', { name: 'Sign in' }).click();
  await expect(page.getByTestId('manual')).toBeVisible();
}

const desktopBlock = (page: Page) => page.locator('.manual-prose .manual-device--desktop');
const phoneBlock = (page: Page) => page.locator('.manual-prose .manual-device--phone');

test('the switch shows one view at a time, keeps the choice and needs no reload', async ({ page }) => {
  const errors: string[] = [];
  page.on('console', (message) => {
    if (message.type() === 'error') {
      errors.push(message.text());
    }
  });
  await signIn(page);
  await page.goto('/en/manual/03-guide/projects');

  const group = page.getByRole('group', { name: 'View for' });
  await expect(group).toBeVisible();
  const desktop = group.getByRole('button', { name: 'Desktop' });
  const phone = group.getByRole('button', { name: 'Phone' });
  await expect(desktop).toHaveAttribute('aria-pressed', 'true');
  await expect(desktopBlock(page)).toBeVisible();
  await expect(phoneBlock(page)).toBeHidden();
  await expect(page.locator('figure[data-nowo-manual-device="phone"] img')).toHaveAttribute('src', '/en/manual/images/dashboard.phone.svg');
  await expect(page.locator('figure[data-nowo-manual-device="phone"]')).toBeHidden();
  await expect(page.locator('.manual-device__label').first()).toBeHidden();

  // Keyboard: the options are buttons in the tab order; Enter switches without a reload.
  const marker = await page.evaluate(() => {
    (window as unknown as { marker: number }).marker = 42;
    return 42;
  });
  await phone.focus();
  await page.keyboard.press('Enter');
  await expect(phone).toHaveAttribute('aria-pressed', 'true');
  await expect(phoneBlock(page)).toBeVisible();
  await expect(desktopBlock(page)).toBeHidden();
  await expect(page.locator('figure[data-nowo-manual-device="phone"]')).toBeVisible();
  expect(await page.evaluate(() => (window as unknown as { marker?: number }).marker)).toBe(marker);

  // The choice is kept for this reader.
  await page.reload();
  await expect(page.getByRole('button', { name: 'Phone' })).toHaveAttribute('aria-pressed', 'true');
  await expect(phoneBlock(page)).toBeVisible();
  await page.keyboard.press('Tab');
  await page.getByRole('button', { name: 'Desktop' }).press('Space');
  await expect(desktopBlock(page)).toBeVisible();
  expect(errors).toEqual([]);
});

test('chapters without device content have no switch', async ({ page }) => {
  await signIn(page);
  await page.goto('/en/manual/03-guide');
  await expect(page.locator('h1')).toHaveText('User guide');
  await expect(page.getByTestId('manual-device-switch')).toHaveCount(0);
});

test('a search hit opens its view, and the reader\'s choice then wins', async ({ page }) => {
  await signIn(page);
  await page.goto('/en/manual/03-guide/projects?device=phone#filter');
  await expect(page.getByRole('button', { name: 'Phone' })).toHaveAttribute('aria-pressed', 'true');
  await expect(phoneBlock(page)).toBeVisible();

  await page.getByRole('button', { name: 'Desktop' }).click();
  await expect(page).toHaveURL(/\/en\/manual\/03-guide\/projects#filter$/);
  await expect(desktopBlock(page)).toBeVisible();

  await page.goto('/en/manual/03-guide/projects?device=tablet');
  await expect(page.getByRole('button', { name: 'Desktop' })).toHaveAttribute('aria-pressed', 'true');
});

test.describe('on a phone-sized screen', () => {
  test.use({ viewport: { width: 390, height: 844 } });

  test('the phone view is the default, in Spanish too', async ({ page }) => {
    await signIn(page);
    await page.goto('/es/manual/03-guide/projects');
    await expect(page.getByRole('group', { name: 'Vista para' })).toBeVisible();
    await expect(page.getByRole('button', { name: 'Móvil' })).toHaveAttribute('aria-pressed', 'true');
    await expect(phoneBlock(page)).toBeVisible();
    await expect(desktopBlock(page)).toBeHidden();
  });
});
