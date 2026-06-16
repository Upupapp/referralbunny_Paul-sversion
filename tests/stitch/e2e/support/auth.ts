import type { Page } from '@playwright/test';
import { QA_PASSWORD, type QaSession } from './qa-matrix';

/**
 * Logs in as the given QA session via its real login form, then waits for
 * the post-login redirect to land on the session's dashboard.
 */
export async function loginAs(page: Page, session: QaSession): Promise<void> {
  await page.goto(session.loginPath);
  await page.fill('input[name="email"]', session.email);
  await page.fill('input[name="password"]', QA_PASSWORD);
  await page.locator('input[name="password"]').press('Enter');
  await page.waitForURL(session.dashboardPath, { timeout: 15000 });
}
