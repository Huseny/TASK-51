import { defineConfig } from '@playwright/test'

const webUrl = process.env.E2E_WEB_URL || 'http://127.0.0.1:3100'
const apiUrl = process.env.E2E_API_URL || 'http://127.0.0.1:8000/api/v1'

const webServers = [
  {
    command: 'npm run dev -- --host 127.0.0.1 --port 3100',
    url: webUrl,
    reuseExistingServer: !process.env.CI,
    timeout: 120000,
  },
]

if (process.env.PW_START_BACKEND === '1') {
  webServers.push({
    command: 'php artisan serve --host=127.0.0.1 --port=8000',
    url: apiUrl.replace('/api/v1', '/up'),
    cwd: '../backend',
    reuseExistingServer: !process.env.CI,
    timeout: 120000,
  })
}

export default defineConfig({
  testDir: './tests/e2e',
  timeout: 120000,
  fullyParallel: false,
  workers: 1,
  outputDir: 'test-results/playwright',
  webServer: webServers,
  use: {
    baseURL: webUrl,
    trace: 'retain-on-failure',
    screenshot: 'only-on-failure',
    video: 'retain-on-failure',
  },
})
