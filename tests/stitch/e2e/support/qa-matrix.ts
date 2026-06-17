/**
 * STITCH QA Matrix — 13 seeded sessions for dashboard-landing E2E checks.
 *
 * Mirrors config/stitch.php qa_matrix. Dashboard URLs resolve {tenantId} from
 * each session's tenant_id. Shared password for all sessions: QaPassword123!
 *
 * Seed with: php artisan stitch:seed
 * lgu-ids is intentionally absent — it is the protected production-like tenant.
 */

export interface QaSession {
  role: string;
  tenantId: string | null;
  email: string;
  password: string;
  loginUrl: string;
  dashboardUrl: string;
  expectedStitchPage: string;
}

const PASSWORD = 'QaPassword123!';

export const QA_MATRIX: QaSession[] = [
  // ── Tenant Admin × 3 tenants ─────────────────────────────────────────────
  {
    role: 'tenant_admin',
    tenantId: 'qa-tenant-a',
    email: 'qa-admin-a@referralbunny.ai',
    password: PASSWORD,
    loginUrl: '/tenant/login',
    dashboardUrl: '/tenant/qa-tenant-a/dashboard',
    expectedStitchPage: 'tenant-dashboard',
  },
  {
    role: 'tenant_admin',
    tenantId: 'qa-tenant-b',
    email: 'qa-admin-b@referralbunny.ai',
    password: PASSWORD,
    loginUrl: '/tenant/login',
    dashboardUrl: '/tenant/qa-tenant-b/dashboard',
    expectedStitchPage: 'tenant-dashboard',
  },
  {
    role: 'tenant_admin',
    tenantId: 'qa-tenant-c',
    email: 'qa-admin-c@referralbunny.ai',
    password: PASSWORD,
    loginUrl: '/tenant/login',
    dashboardUrl: '/tenant/qa-tenant-c/dashboard',
    expectedStitchPage: 'tenant-dashboard',
  },

  // ── Tenant Manager × 3 tenants ────────────────────────────────────────────
  {
    role: 'tenant_manager',
    tenantId: 'qa-tenant-a',
    email: 'qa-manager-a@referralbunny.ai',
    password: PASSWORD,
    loginUrl: '/tenant/login',
    dashboardUrl: '/tenant/qa-tenant-a/dashboard',
    expectedStitchPage: 'tenant-dashboard',
  },
  {
    role: 'tenant_manager',
    tenantId: 'qa-tenant-b',
    email: 'qa-manager-b@referralbunny.ai',
    password: PASSWORD,
    loginUrl: '/tenant/login',
    dashboardUrl: '/tenant/qa-tenant-b/dashboard',
    expectedStitchPage: 'tenant-dashboard',
  },
  {
    role: 'tenant_manager',
    tenantId: 'qa-tenant-c',
    email: 'qa-manager-c@referralbunny.ai',
    password: PASSWORD,
    loginUrl: '/tenant/login',
    dashboardUrl: '/tenant/qa-tenant-c/dashboard',
    expectedStitchPage: 'tenant-dashboard',
  },

  // ── Referrer × 3 tenants ──────────────────────────────────────────────────
  {
    role: 'referrer',
    tenantId: 'qa-tenant-a',
    email: 'qa-referrer-active@referralbunny.ai',
    password: PASSWORD,
    loginUrl: '/reseller/login',
    dashboardUrl: '/reseller/qa-tenant-a/dashboard',
    expectedStitchPage: 'referrer-dashboard',
  },
  {
    role: 'referrer',
    tenantId: 'qa-tenant-b',
    email: 'qa-referrer-active-b@referralbunny.ai',
    password: PASSWORD,
    loginUrl: '/reseller/login',
    dashboardUrl: '/reseller/qa-tenant-b/dashboard',
    expectedStitchPage: 'referrer-dashboard',
  },
  {
    role: 'referrer',
    tenantId: 'qa-tenant-c',
    email: 'qa-referrer-active-c@referralbunny.ai',
    password: PASSWORD,
    loginUrl: '/reseller/login',
    dashboardUrl: '/reseller/qa-tenant-c/dashboard',
    expectedStitchPage: 'referrer-dashboard',
  },

  // ── Partner × 3 tenants ───────────────────────────────────────────────────
  {
    role: 'partner',
    tenantId: 'qa-tenant-a',
    email: 'qa-partner-active@referralbunny.ai',
    password: PASSWORD,
    loginUrl: '/partner/login',
    dashboardUrl: '/partner/dashboard',
    expectedStitchPage: 'partner-dashboard',
  },
  {
    role: 'partner',
    tenantId: 'qa-tenant-b',
    email: 'qa-partner-active-b@referralbunny.ai',
    password: PASSWORD,
    loginUrl: '/partner/login',
    dashboardUrl: '/partner/dashboard',
    expectedStitchPage: 'partner-dashboard',
  },
  {
    role: 'partner',
    tenantId: 'qa-tenant-c',
    email: 'qa-partner-active-c@referralbunny.ai',
    password: PASSWORD,
    loginUrl: '/partner/login',
    dashboardUrl: '/partner/dashboard',
    expectedStitchPage: 'partner-dashboard',
  },

  // ── Super Admin (platform-wide) ───────────────────────────────────────────
  {
    role: 'super_admin',
    tenantId: null,
    email: 'qa-superadmin@referralbunny.ai',
    password: PASSWORD,
    loginUrl: '/login',
    dashboardUrl: '/platform/dashboard',
    expectedStitchPage: 'platform-dashboard',
  },
];
