/**
 * E2E tests for auth failure scenarios, role-based access control, and
 * registration validation.  These complement the happy-path ride-lifecycle
 * tests by covering the error paths and permission boundaries.
 */
import { expect, test } from '@playwright/test'

const API_BASE = process.env.E2E_API_URL || 'http://127.0.0.1:8000/api/v1'
const WEB_BASE = process.env.E2E_WEB_URL || 'http://127.0.0.1:3000'
const API_ROOT = API_BASE.replace('/api/v1', '')

const shouldSkipUnsupported = process.env.E2E_ALLOW_SKIP_UNSUPPORTED === '1'

const randomSuffix = () => `${Date.now()}${Math.floor(Math.random() * 9999)}`

const ensureServicesOrSkip = async (request) => {
  let backendReady = false
  let frontendReady = false

  try {
    const probe = await request.get(`${API_ROOT}/up`, { failOnStatusCode: false })
    backendReady = probe.status() > 0 && probe.status() < 500
  } catch {
    backendReady = false
  }

  try {
    const probe = await request.get(`${WEB_BASE}/login`, { failOnStatusCode: false })
    frontendReady = probe.status() > 0 && probe.status() < 500
  } catch {
    frontendReady = false
  }

  if (backendReady && frontendReady) {
    return
  }

  if (shouldSkipUnsupported) {
    test.skip(true, 'Backend/frontend is not reachable in this environment.')
  }

  throw new Error(
    'E2E services unavailable. Start backend/frontend or set E2E_ALLOW_SKIP_UNSUPPORTED=1.',
  )
}

const registerUser = async (request, { role, prefix }) => {
  const suffix = randomSuffix()
  const username = `${prefix}_${suffix}`
  const password = 'Password12345'

  const response = await request.post(`${API_BASE}/auth/register`, {
    data: { username, password, password_confirmation: password, role },
  })
  expect(response.status()).toBe(201)
  return { username, password }
}

const goToLogin = async (page) => {
  await page.context().clearCookies()
  await page.evaluate(() => {
    localStorage.clear()
    sessionStorage.clear()
  })
  await page.goto(`${WEB_BASE}/login`)
  await expect(page).toHaveURL(/\/login$/)
}

const loginThroughUi = async (page, { username, password }) => {
  await goToLogin(page)
  await page.getByLabel('Username').fill(username)
  await page.getByLabel('Password').fill(password)
  await page.getByRole('button', { name: 'Sign In' }).click()
  await expect(page).toHaveURL(/\/dashboard$/)
}

// ── auth failure ───────────────────────────────────────────────────────────

test('invalid credentials show an error message on the login page', async ({ request, page }) => {
  await ensureServicesOrSkip(request)

  await goToLogin(page)
  await page.getByLabel('Username').fill('no_such_user_xyz')
  await page.getByLabel('Password').fill('wrongpassword99')
  await page.getByRole('button', { name: 'Sign In' }).click()

  // Should stay on login page, not redirect
  await expect(page).toHaveURL(/\/login$/)

  // Error message should appear somewhere on the page
  await expect(page.locator('.error-text')).toBeVisible()
})

test('login page stays put and shows error on empty submission', async ({ request, page }) => {
  await ensureServicesOrSkip(request)

  await goToLogin(page)
  await page.getByRole('button', { name: 'Sign In' }).click()

  await expect(page).toHaveURL(/\/login$/)
  await expect(page.locator('.error-text')).toBeVisible()
})

// ── registration validation ────────────────────────────────────────────────

test('registration form shows all three password requirement checks unmet initially', async ({ request, page }) => {
  await ensureServicesOrSkip(request)

  await page.goto(`${WEB_BASE}/register`)
  await expect(page).toHaveURL(/\/register$/)

  // All three strength indicators should start unmet (no ✓ icons)
  const checks = page.locator('.password-checks li')
  await expect(checks).toHaveCount(3)
  for (let i = 0; i < 3; i++) {
    await expect(checks.nth(i)).not.toHaveClass(/met/)
  }
})

test('registration password checks turn green as requirements are met', async ({ request, page }) => {
  await ensureServicesOrSkip(request)

  await page.goto(`${WEB_BASE}/register`)
  await page.getByLabel('Password').fill('Password1234')

  // All three checks should be met
  const checks = page.locator('.password-checks li')
  for (let i = 0; i < 3; i++) {
    await expect(checks.nth(i)).toHaveClass(/met/)
  }
})

test('registration with an already-taken username shows an error', async ({ request, page }) => {
  await ensureServicesOrSkip(request)

  // Register once to claim the username
  const { username, password } = await registerUser(request, { role: 'rider', prefix: 'e2e_dup_check' })

  await page.goto(`${WEB_BASE}/register`)
  await page.getByLabel('Username').fill(username)
  await page.getByLabel('Password').fill(password)
  await page.getByLabel('Confirm Password').fill(password)
  await page.getByRole('button', { name: 'Create Account' }).click()

  await expect(page).toHaveURL(/\/register$/)
  await expect(page.locator('.error-text')).toBeVisible()
})

// ── unauthenticated redirect ───────────────────────────────────────────────

test('unauthenticated user visiting /dashboard is redirected to /login', async ({ request, page }) => {
  await ensureServicesOrSkip(request)

  // Fresh context — no auth token
  await page.context().clearCookies()
  await page.evaluate(() => localStorage.clear())
  await page.goto(`${WEB_BASE}/dashboard`)

  await expect(page).toHaveURL(/\/login$/)
})

test('unauthenticated user visiting a protected driver route is redirected to /login', async ({ request, page }) => {
  await ensureServicesOrSkip(request)

  await page.context().clearCookies()
  await page.evaluate(() => localStorage.clear())
  await page.goto(`${WEB_BASE}/driver/available-rides`)

  await expect(page).toHaveURL(/\/login$/)
})

// ── role-based access control ──────────────────────────────────────────────

test('rider visiting a driver-only route is redirected to /dashboard', async ({ request, page }) => {
  await ensureServicesOrSkip(request)

  const rider = await registerUser(request, { role: 'rider', prefix: 'e2e_rbac_rider' })
  await loginThroughUi(page, rider)

  await page.goto(`${WEB_BASE}/driver/available-rides`)

  // Role guard redirects back to dashboard
  await expect(page).toHaveURL(/\/dashboard$/)
})

test('driver visiting a rider-only route is redirected to /dashboard', async ({ request, page }) => {
  await ensureServicesOrSkip(request)

  const driver = await registerUser(request, { role: 'driver', prefix: 'e2e_rbac_driver' })
  await loginThroughUi(page, driver)

  await page.goto(`${WEB_BASE}/rider/trips`)

  await expect(page).toHaveURL(/\/dashboard$/)
})

test('rider visiting /reports (fleet_manager only) is redirected to /dashboard', async ({ request, page }) => {
  await ensureServicesOrSkip(request)

  const rider = await registerUser(request, { role: 'rider', prefix: 'e2e_rbac_reports' })
  await loginThroughUi(page, rider)

  await page.goto(`${WEB_BASE}/reports`)

  await expect(page).toHaveURL(/\/dashboard$/)
})

// ── role badge on dashboard ────────────────────────────────────────────────

test('rider dashboard displays the rider role badge', async ({ request, page }) => {
  await ensureServicesOrSkip(request)

  const rider = await registerUser(request, { role: 'rider', prefix: 'e2e_badge_rider' })
  await loginThroughUi(page, rider)

  await expect(page.locator('.badge', { hasText: 'rider' })).toBeVisible()
})

test('driver dashboard displays the driver role badge', async ({ request, page }) => {
  await ensureServicesOrSkip(request)

  const driver = await registerUser(request, { role: 'driver', prefix: 'e2e_badge_driver' })
  await loginThroughUi(page, driver)

  await expect(page.locator('.badge', { hasText: 'driver' })).toBeVisible()
})

test('fleet_manager dashboard displays the fleet_manager role badge', async ({ request, page }) => {
  await ensureServicesOrSkip(request)

  const manager = await registerUser(request, { role: 'fleet_manager', prefix: 'e2e_badge_mgr' })
  await loginThroughUi(page, manager)

  await expect(page.locator('.badge', { hasText: 'fleet_manager' })).toBeVisible()
})

// ── logout clears session ──────────────────────────────────────────────────

test('logout button clears the session and redirects to /login', async ({ request, page }) => {
  await ensureServicesOrSkip(request)

  const rider = await registerUser(request, { role: 'rider', prefix: 'e2e_logout' })
  await loginThroughUi(page, rider)

  await page.getByRole('button', { name: 'Logout' }).click()
  await expect(page).toHaveURL(/\/login$/)

  // Revisiting dashboard without re-login should redirect to login again
  await page.goto(`${WEB_BASE}/dashboard`)
  await expect(page).toHaveURL(/\/login$/)
})
