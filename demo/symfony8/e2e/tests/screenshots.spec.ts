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

test.beforeEach(async ({ page }) => {
  // The Symfony debug toolbar is not part of the demo platform: hide it on every page of the capture.
  await page.addInitScript(() => {
    const hide = () => {
      const style = document.createElement('style');
      style.textContent = '.sf-toolbar,.sf-minitoolbar,[id^="sfwdt"]{display:none!important}';
      document.head.appendChild(style);
    };
    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', hide);
    else hide();
  });
  await page.goto('/en/login');
  await page.getByLabel('Username').fill('admin');
  await page.getByLabel('Password').fill('admin');
  await page.getByRole('button', { name: 'Sign in' }).click();
});

test('overview — a chapter of the manual', async ({ page }) => {
  await page.goto('/en/manual/01-getting-started');
  await expect(page.getByTestId('manual-chapter')).toBeVisible();
  await page.screenshot({ path: resolve(outDir, 'overview.png') });
});

test('sub-pages — a grandchild with its open branch and breadcrumb', async ({ page }) => {
  await page.goto('/en/manual/03-guide/projects/sharing');
  await expect(page.getByTestId('manual-breadcrumbs')).toBeVisible();
  await page.screenshot({ path: resolve(outDir, 'sub-pages.png') });
});
