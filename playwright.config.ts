import { defineConfig, devices } from '@playwright/test';

export default defineConfig({
  testDir: './tests',
  timeout: 30_000,
  retries: 0,
  use: {
    baseURL: 'https://dev.widev.pro',
    ignoreHTTPSErrors: true,
    screenshot: 'only-on-failure',
    trace: 'on-first-retry',
  },
  projects: [
    {
      name: 'setup',
      testMatch: /auth\.setup\.ts/,
    },
    {
      name: 'seed',
      testMatch: /seed\.setup\.ts/,
      use: {
        storageState: 'tests/.auth/state.json',
      },
      dependencies: ['setup'],
      timeout: 120_000,
    },
    {
      name: 'chromium',
      use: {
        ...devices['Desktop Chrome'],
        storageState: 'tests/.auth/state.json',
      },
      dependencies: ['setup', 'seed'],
    },
  ],
});
