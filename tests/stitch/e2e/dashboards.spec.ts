import { test, expect } from '@playwright/test';
import { QA_MATRIX } from './support/qa-matrix';
import { loginAs } from './support/auth';

for (const session of QA_MATRIX) {
  const label = `${session.role} (${session.tenantId ?? 'platform'})`;

  test(`${label} logs in and reaches ${session.stitchPage}`, async ({ page }) => {
    await loginAs(page, session);

    await expect(page).toHaveURL(session.dashboardPath);
    await expect(page.locator('body')).toHaveAttribute('data-stitch-page', session.stitchPage);
  });
}
