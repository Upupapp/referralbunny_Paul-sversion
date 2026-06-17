/**
 * STITCH global setup — logs in each QA session once and saves Playwright
 * storage state so individual tests can load state directly without re-logging.
 *
 * Storage state files land in tests/stitch/e2e/.auth/{email}.json (gitignored).
 * Run `php artisan stitch:seed` before running Playwright for the first time.
 */
import { chromium, FullConfig } from '@playwright/test';
import * as fs from 'fs';
import * as path from 'path';
import { QA_MATRIX } from './qa-matrix';

export const authStatePath = (email: string): string =>
  path.join(__dirname, '../.auth', `${email.replace('@', '_at_')}.json`);

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

    // Wait until we are no longer on a login page
    await page.waitForFunction(
      () => !document.location.pathname.endsWith('/login'),
      { timeout: 15_000 }
    );

    await context.storageState({ path: authStatePath(session.email) });
    await context.close();
  }

  await browser.close();
}
