/**
 * Hand-mirrors config/stitch.php's `qa_matrix` (13 seeded sessions).
 * Keep in sync with that file and with database/seeders/ReferralBunnyQaSeeder.php.
 * lgu-ids is intentionally absent — never seeded or touched by STITCH tooling.
 */

export const QA_PASSWORD = 'QaPassword123!';

export interface QaSession {
  role: 'tenant_admin' | 'tenant_manager' | 'referrer' | 'partner' | 'super_admin';
  tenantId: string | null;
  email: string;
  loginPath: string;
  dashboardPath: string;
  stitchPage: string;
}

export const QA_MATRIX: QaSession[] = [
  { role: 'tenant_admin',   tenantId: 'qa-tenant-a', email: 'qa-admin-a@referralbunny.ai',           loginPath: '/tenant/login',   dashboardPath: '/tenant/qa-tenant-a/dashboard',   stitchPage: 'tenant-dashboard' },
  { role: 'tenant_admin',   tenantId: 'qa-tenant-b', email: 'qa-admin-b@referralbunny.ai',           loginPath: '/tenant/login',   dashboardPath: '/tenant/qa-tenant-b/dashboard',   stitchPage: 'tenant-dashboard' },
  { role: 'tenant_admin',   tenantId: 'qa-tenant-c', email: 'qa-admin-c@referralbunny.ai',           loginPath: '/tenant/login',   dashboardPath: '/tenant/qa-tenant-c/dashboard',   stitchPage: 'tenant-dashboard' },
  { role: 'tenant_manager', tenantId: 'qa-tenant-a', email: 'qa-manager-a@referralbunny.ai',         loginPath: '/tenant/login',   dashboardPath: '/tenant/qa-tenant-a/dashboard',   stitchPage: 'tenant-dashboard' },
  { role: 'tenant_manager', tenantId: 'qa-tenant-b', email: 'qa-manager-b@referralbunny.ai',         loginPath: '/tenant/login',   dashboardPath: '/tenant/qa-tenant-b/dashboard',   stitchPage: 'tenant-dashboard' },
  { role: 'tenant_manager', tenantId: 'qa-tenant-c', email: 'qa-manager-c@referralbunny.ai',         loginPath: '/tenant/login',   dashboardPath: '/tenant/qa-tenant-c/dashboard',   stitchPage: 'tenant-dashboard' },
  { role: 'referrer',       tenantId: 'qa-tenant-a', email: 'qa-referrer-active@referralbunny.ai',   loginPath: '/reseller/login', dashboardPath: '/reseller/qa-tenant-a/dashboard', stitchPage: 'referrer-dashboard' },
  { role: 'referrer',       tenantId: 'qa-tenant-b', email: 'qa-referrer-active-b@referralbunny.ai', loginPath: '/reseller/login', dashboardPath: '/reseller/qa-tenant-b/dashboard', stitchPage: 'referrer-dashboard' },
  { role: 'referrer',       tenantId: 'qa-tenant-c', email: 'qa-referrer-active-c@referralbunny.ai', loginPath: '/reseller/login', dashboardPath: '/reseller/qa-tenant-c/dashboard', stitchPage: 'referrer-dashboard' },
  { role: 'partner',        tenantId: 'qa-tenant-a', email: 'qa-partner-active@referralbunny.ai',    loginPath: '/partner/login',  dashboardPath: '/partner/dashboard',              stitchPage: 'partner-dashboard' },
  { role: 'partner',        tenantId: 'qa-tenant-b', email: 'qa-partner-active-b@referralbunny.ai',  loginPath: '/partner/login',  dashboardPath: '/partner/dashboard',              stitchPage: 'partner-dashboard' },
  { role: 'partner',        tenantId: 'qa-tenant-c', email: 'qa-partner-active-c@referralbunny.ai',  loginPath: '/partner/login',  dashboardPath: '/partner/dashboard',              stitchPage: 'partner-dashboard' },
  { role: 'super_admin',    tenantId: null,          email: 'qa-superadmin@referralbunny.ai',        loginPath: '/login',          dashboardPath: '/platform/dashboard',             stitchPage: 'platform-dashboard' },
];
