const { spawnSync } = require('node:child_process')
const path = require('node:path')

const skipUnavailable = process.argv.includes('--skip-unavailable')
let playwrightCli = null

try {
  const playwrightPackagePath = require.resolve('playwright/package.json')
  const playwrightPackage = require(playwrightPackagePath)
  const cliRelativePath =
    typeof playwrightPackage.bin === 'string' ? playwrightPackage.bin : playwrightPackage.bin?.playwright

  if (!cliRelativePath) {
    throw new Error('Playwright package does not expose a CLI entry.')
  }

  playwrightCli = path.join(path.dirname(playwrightPackagePath), cliRelativePath)
} catch (error) {
  const message = error instanceof Error ? error.message : String(error)
  console.error('[e2e] Cannot locate Playwright CLI.')
  console.error('[e2e] Install dependencies with `npm install` in repo/frontend.')
  console.error(`[e2e] ${message}`)
  process.exit(1)
}

const env = {
  ...process.env,
  E2E_WEB_URL: process.env.E2E_WEB_URL || 'http://127.0.0.1:3100',
  E2E_API_URL: process.env.E2E_API_URL || 'http://127.0.0.1:8000/api/v1',
}

if (skipUnavailable) {
  env.E2E_ALLOW_SKIP_UNSUPPORTED = '1'
}

const result = spawnSync(process.execPath, [playwrightCli, 'test'], {
  stdio: 'inherit',
  env,
})

if (result.error) {
  const message = result.error.message || String(result.error)
  console.error('[e2e] Failed to launch Playwright process.')
  console.error(`[e2e] Command: ${process.execPath} ${playwrightCli} test`)
  console.error(`[e2e] ${message}`)
  process.exit(1)
}

if (result.signal) {
  console.error(`[e2e] Playwright process terminated by signal: ${result.signal}`)
  process.exit(1)
}

if (typeof result.status === 'number') {
  process.exit(result.status)
}

console.error('[e2e] Playwright process exited without a status code.')
process.exit(1)
