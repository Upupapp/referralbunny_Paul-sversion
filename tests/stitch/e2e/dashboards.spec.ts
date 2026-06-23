/**
 * STITCH — Dashboard landing checks.
 *
 * For each of the 13 QA sessions, verifies:
 *   1. The dashboard URL loads (HTTP 200, not redirected to a login page).
 *   2. body[data-stitch-page] matches the expected portal value.
 *   3. No STITCH failure modal is open on load.
 *
 * Runs across Desktop Chrome + Mobile Chrome (Pixel 5) = 26 total assertions.
 *
 * Prerequisites:
 *   php artisan stitch:seed          — seed the 13 QA accounts
 *   npx playwright install chromium  — install browser binaries
 *   php artisan serve                — run the app on localhost:8000
 *   npx playwright test              — execute this spec
 */
import { test, expect, BrowserContext } from '@playwright/test';
import * as fs from 'fs';
import { QA_MATRIX, QaSession } from './support/qa-matrix';
import { authStatePath } from './support/auth';

// ── helpers ───────────────────────────────────────────────────────────────────

function stateExists(session: QaSession): boolean {
  return fs.existsSync(authStatePath(session.email));
}

async function contextForSession(
  session: QaSession,
  browser: import('@playwright/test').Browser
): Promise<BrowserContext> {
  if (stateExists(session)) {
    return browser.newContext({ storageState: authStatePath(session.email) });
  }
  // Fallback: log in inline if global setup didn't run (e.g., developer running
  // a single test directly).
  const ctx  = await browser.newContext();
  const page = await ctx.newPage();
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
      `Fallback login failed for ${session.email}: landed on ${finalUrl}, expected path containing ${session.dashboardUrl}`
    );
  }
  return ctx;
}

// ── tests ─────────────────────────────────────────────────────────────────────

for (const session of QA_MATRIX) {
  test(`[${session.role}] ${session.email} — dashboard reached`, async ({ browser }) => {
    const ctx  = await contextForSession(session, browser);
    const page = await ctx.newPage();

    const response = await page.goto(session.dashboardUrl);

    // 1. HTTP 200 — not bounced back to login
    expect(response?.status(), `Expected 200 for ${session.dashboardUrl}`).toBe(200);
    expect(page.url(), 'Should not redirect to a login page').not.toContain('/login');

    // 2. Correct portal reached (data-stitch-page attribute)
    const stitchPage = await page.getAttribute('body', 'data-stitch-page');
    expect(
      stitchPage,
      `Expected data-stitch-page="${session.expectedStitchPage}" for ${session.role}`
    ).toBe(session.expectedStitchPage);

    // 3. No failure modal visible
    const failureModal = page.locator('[data-stitch-failure-modal]');
    await expect(failureModal, 'STITCH failure modal should not be visible').not.toBeVisible();

    await ctx.close();
  });
}
