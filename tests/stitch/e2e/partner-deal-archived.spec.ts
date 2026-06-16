/**
 * STITCH — Partner Portal: Archived Deal Filtering
 *
 * Verifies that archived deals are excluded from all active-deal surfaces in the
 * partner portal, and that write operations on archived deals are blocked with 422.
 *
 * Pre-condition: ReferralBunnyQaSeeder must have been run. seedArchivedDeal()
 * inserts leads row (QA_ARCHIVED_DEAL_ID) + deal_partners row linking
 * qa-partner-active@referralbunny.ai to that archived lead.
 */
import { test, expect } from '@playwright/test';
import { loginAs } from './support/auth';
import { QA_MATRIX } from './support/qa-matrix';

const PARTNER = QA_MATRIX.find(s => s.role === 'partner' && s.tenantId === 'qa-tenant-a')!;

// Must stay in sync with ReferralBunnyQaSeeder::QA_ARCHIVED_DEAL_ID constant.
const ARCHIVED_DEAL_ID   = 'aaaaaaaa-aaaa-4aaa-aaaa-aaaaaaaaaaaa';
const ARCHIVED_DEAL_NAME = '[QA] Archived Deal — Tenant A';

test.describe('partner portal — archived deal filtering', () => {
  test.beforeEach(async ({ page }) => {
    await loginAs(page, PARTNER);
  });

  test('dashboard Active associations count excludes archived deal', async ({ page }) => {
    await page.goto('/partner/dashboard');
    // The "My Deals" stat card shows the $dealCount value. With only the archived
    // deal in deal_partners, the active count must be 0.
    await expect(
      page.locator('xpath=//p[normalize-space(.)="Active associations"]/preceding-sibling::p[1]')
    ).toHaveText('0');
  });

  test('archived deal name does not appear in dashboard recent deals', async ({ page }) => {
    await page.goto('/partner/dashboard');
    await expect(page.locator('body')).not.toContainText(ARCHIVED_DEAL_NAME);
  });

  test('archived deal does not appear in deals list', async ({ page }) => {
    await page.goto('/partner/deals');
    await expect(page.locator('body')).not.toContainText(ARCHIVED_DEAL_NAME);
  });

  test('archived deal show page displays top-of-page archived banner', async ({ page }) => {
    await page.goto(`/partner/deals/${ARCHIVED_DEAL_ID}`);
    await expect(page.locator('body')).toContainText('This deal has been archived');
  });

  test('archived deal show page hides Add Note form and shows Read-only badge', async ({ page }) => {
    await page.goto(`/partner/deals/${ARCHIVED_DEAL_ID}`);
    await expect(page.getByRole('button', { name: 'Add Note' })).not.toBeVisible();
    await expect(page.locator('[data-testid="hero-readonly-badge"]')).toBeVisible();
    await expect(page.locator('[data-testid="notes-readonly-badge"]')).toBeVisible();
  });

  test('addNote API returns 422 on archived deal', async ({ page }) => {
    await page.goto(`/partner/deals/${ARCHIVED_DEAL_ID}`);

    const cookies    = await page.context().cookies();
    const xsrfCookie = cookies.find(c => c.name === 'XSRF-TOKEN');
    const csrfToken  = xsrfCookie ? decodeURIComponent(xsrfCookie.value) : '';

    const response = await page.request.post(
      `/partner/deals/${ARCHIVED_DEAL_ID}/notes`,
      {
        headers: {
          'Accept':       'application/json',
          'X-XSRF-TOKEN': csrfToken,
        },
        form: { body: '[STITCH] this note should be rejected on archived deal' },
      },
    );
    expect(response.status()).toBe(422);
  });
});
