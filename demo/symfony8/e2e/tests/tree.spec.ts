import AxeBuilder from '@axe-core/playwright';
import { test, expect, type Page } from '@playwright/test';

/** 1.6 — sub-pages: navigation through a three-level tree, inherited access and search ("Parent › Child"), with axe. */
async function signIn(page: Page, user: string): Promise<void> {
  await page.goto('/en/login');
  await page.getByLabel('Username').fill(user);
  await page.getByLabel('Password').fill(user);
  await page.getByRole('button', { name: 'Sign in' }).click();
  await expect(page.getByTestId('manual')).toBeVisible();
}

async function expectNoAxeViolations(page: Page): Promise<void> {
  const result = await new AxeBuilder({ page }).include('main').withTags(['wcag2a', 'wcag2aa', 'wcag21a', 'wcag21aa']).analyze();
  expect(result.violations.map((v) => `${v.id}: ${v.nodes.map((n) => n.target.join(' ')).join(', ')}`)).toEqual([]);
}

test('a member walks the tree: folded branches, breadcrumb, sub-pages and the depth-first pager', async ({ page }) => {
  await signIn(page, 'demo');
  await expect(page.getByTestId('manual-branch-03-guide')).not.toHaveAttribute('open', /.*/);
  await page.getByTestId('manual-nav-03-guide').click();
  await expect(page).toHaveURL(/\/en\/manual\/03-guide$/);
  await expect(page.getByTestId('manual-branch-03-guide')).toHaveAttribute('open', '');
  await expect(page.getByTestId('manual-children').getByRole('link')).toHaveText(['Projects']);
  await expect(page.getByTestId('manual-nav-billing')).toHaveCount(0);

  await page.getByTestId('manual-child-projects').click();
  await expect(page).toHaveURL(/\/en\/manual\/03-guide\/projects$/);
  await page.locator('.manual-prose').getByRole('link', { name: 'Sharing a project' }).click();
  await expect(page).toHaveURL(/\/en\/manual\/03-guide\/projects\/sharing#invite$/);
  await expect(page.locator('h1')).toHaveText('Sharing a project');
  await expect(page.getByTestId('manual-breadcrumbs').getByRole('listitem')).toHaveText([/Manual/, /User guide/, /Projects/, /Sharing a project/]);
  await expect(page.getByTestId('manual-branch-projects')).toHaveAttribute('open', '');
  await expect(page.getByTestId('manual-nav-sharing')).toHaveAttribute('aria-current', 'page');
  await expect(page.getByTestId('manual-previous')).toHaveAttribute('href', '/en/manual/03-guide/projects');
  await expectNoAxeViolations(page);

  // Folding a branch by hand keeps working (native <details>).
  await page.getByTestId('manual-branch-03-guide').locator('> summary').click();
  await expect(page.getByTestId('manual-nav-projects')).toBeHidden();

  await page.getByTestId('manual-breadcrumbs').getByRole('link', { name: 'User guide' }).click();
  await expect(page).toHaveURL(/\/en\/manual\/03-guide$/);
});

test('access is inherited: billing is a 404 for a member, its link plain text; an admin reads it', async ({ page }) => {
  await signIn(page, 'demo');
  const response = await page.goto('/en/manual/03-guide/billing');
  expect(response?.status()).toBe(404);

  await page.context().clearCookies();
  await signIn(page, 'admin');
  await page.goto('/en/manual/03-guide');
  await expect(page.getByTestId('manual-children').getByRole('link')).toHaveText(['Projects', 'Billing']);
  await page.locator('.manual-prose').getByRole('link', { name: 'Billing' }).click();
  await expect(page.locator('h1')).toHaveText('Billing');
  await expectNoAxeViolations(page);
});

test('search finds sub-pages as "Parent › Child" and respects access', async ({ page }) => {
  await signIn(page, 'demo');
  await page.goto('/en/manual-search?q=invite');
  await expect(page.getByTestId('manual-search-result').first()).toHaveText('User guide › Projects › Sharing a project');
  await page.goto('/en/manual-search?q=invoice');
  await expect(page.getByTestId('manual-search-empty')).toBeVisible();
  await expectNoAxeViolations(page);

  await page.context().clearCookies();
  await signIn(page, 'admin');
  await page.goto('/en/manual-search?q=invoice');
  await expect(page.getByTestId('manual-search-result')).toHaveText(['User guide › Billing']);
  await page.getByTestId('manual-search-result').click();
  await expect(page).toHaveURL(/\/en\/manual\/03-guide\/billing$/);
});

test('the Spanish sub-page comes from es/03-guide/projects.md', async ({ page }) => {
  await signIn(page, 'demo');
  await page.goto('/es/manual/03-guide/projects');
  await expect(page.locator('h1')).toHaveText('Proyectos');
  await expect(page.locator('.manual-prose').getByRole('link', { name: 'Compartir un proyecto' })).toHaveAttribute('href', '/es/manual/03-guide/projects/sharing#invite');
});
