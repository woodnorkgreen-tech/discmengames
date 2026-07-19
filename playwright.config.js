import { defineConfig, devices } from '@playwright/test'

export default defineConfig({
  testDir: './tests/Browser',
  fullyParallel: false,
  workers: 1,
  timeout: 120_000,
  expect: { timeout: 10_000 },
  reporter: [['list'], ['html', { open: 'never', outputFolder: 'storage/playwright-report' }]],
  use: {
    baseURL: process.env.E2E_BASE_URL ?? 'https://discmen-final-whistle.ddev.site',
    screenshot: 'only-on-failure',
    trace: 'retain-on-failure',
  },
  projects: [{ name: 'chromium', use: { ...devices['Desktop Chrome'] } }],
})
