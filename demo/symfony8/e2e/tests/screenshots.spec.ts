import { test, expect } from '@playwright/test';
import { mkdirSync } from 'node:fs';
import { resolve } from 'node:path';

/** REQ-DEMO-013 — the manual in situ for the README: a chapter with its sidebar, table of contents and a figure. */
const outDir = process.env.SCREENSHOT_DIR
  ? resolve(process.env.SCREENSHOT_DIR)
  : resolve(__dirname, '../../../../docs/images/demo');

test.beforeAll(() => {
  mkdirSync(outDir, { recursive: true });
});

test.use({ viewport: { width: 1280, height: 800 } });

test('overview — a chapter of the manual', async ({ page }) => {
  await page.goto('/en/login');
  await page.getByLabel('Username').fill('admin');
  await page.getByLabel('Password').fill('admin');
  await page.getByRole('button', { name: 'Sign in' }).click();
  await page.goto('/en/manual/01-getting-started');
  await expect(page.getByTestId('manual-chapter')).toBeVisible();
  await page.screenshot({ path: resolve(outDir, 'overview.png') });
});
