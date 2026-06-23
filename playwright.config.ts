import { defineConfig, devices } from '@playwright/test';

export default defineConfig({
  testDir: './tests/stitch/e2e',
  globalSetup: './tests/stitch/e2e/support/auth.ts',
  fullyParallel: false,
  workers: 1,
  reporter: [['list'], ['html', { open: 'never' }]],
  use: {
    baseURL: process.env.STITCH_BASE_URL ?? 'http://127.0.0.1:8000',
    trace: 'on-first-retry',
  },
  projects: [
    { name: 'Desktop Chrome', use: { ...devices['Desktop Chrome'] } },
    { name: 'Mobile Chrome', use: { ...devices['Pixel 5'] } },
  ],
});
