/**
 * STITCH global setup — logs in each QA session once and saves Playwright
 * storage state so individual tests can load state directly without re-logging.
 *
 * Storage state files land in tests/stitch/e2e/.auth/{email}.json (gitignored).
 * Run `php artisan stitch:seed` before running Playwright for the first time.
 */
import { chromium, FullConfig, Page } from '@playwright/test';
import * as fs from 'fs';
import * as path from 'path';
import { QaSession, QA_MATRIX } from './qa-matrix';

export const authStatePath = (email: string): string =>
  path.join(__dirname, '../.auth', `${email.replace('@', '_at_')}.json`);

/**
 * Logs in as the given QA session using a live browser login flow.
 * Receives a Page from the test fixture (which inherits baseURL from
 * playwright.config.ts), so relative loginUrl values resolve correctly.
 * Cannot apply saved storage state because storageState must be set at
 * context-creation time, not injected into an existing Page.
 */
export async function loginAs(page: Page, session: QaSession): Promise<void> {
  await page.goto(session.loginUrl);
  await page.fill('input[name="email"]',    session.email);
  await page.fill('input[name="password"]', session.password);
  await page.click('button[type="submit"]');
  await page.waitForFunction(
    () => !document.location.pathname.endsWith('/login'),
    { timeout: 15_000 }
  );
  const finalUrl = page.url();
  if (!finalUrl.includes(session.dashboardUrl)) {
    throw new Error(
      `Login failed for ${session.email}: landed on ${finalUrl}, expected path containing ${session.dashboardUrl}`
    );
  }
}

export default async function globalSetup(config: FullConfig): Promise<void> {
  const baseURL = config.projects[0].use.baseURL ?? 'http://127.0.0.1:8000';
  const authDir  = path.join(__dirname, '../.auth');

  if (!fs.existsSync(authDir)) {
    fs.mkdirSync(authDir, { recursive: true });
  }

  const browser = await chromium.launch();

  for (const session of QA_MATRIX) {
    const context = await browser.newContext();
    const page    = await context.newPage();

    await page.goto(`${baseURL}${session.loginUrl}`);
    await page.fill('input[name="email"]',    session.email);
    await page.fill('input[name="password"]', session.password);
    await page.click('button[type="submit"]');

    await page.waitForFunction(
      () => !document.location.pathname.endsWith('/login'),
      { timeout: 15_000 }
    );

    const finalUrl = page.url();
    if (!finalUrl.includes(session.dashboardUrl)) {
      await browser.close();
      throw new Error(
        `globalSetup: login failed for ${session.email}: landed on ${finalUrl}, expected path containing ${session.dashboardUrl}`
      );
    }

    await context.storageState({ path: authStatePath(session.email) });
    await context.close();
  }

  await browser.close();
}
