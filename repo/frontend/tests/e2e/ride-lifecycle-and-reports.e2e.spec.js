import { expect, test } from '@playwright/test'

const API_BASE = process.env.E2E_API_URL || 'http://127.0.0.1:8000/api/v1'
const WEB_BASE = process.env.E2E_WEB_URL || 'http://127.0.0.1:3000'

const randomSuffix = () => `${Date.now()}${Math.floor(Math.random() * 10000)}`

const registerUser = async (request, { role, prefix }) => {
  const suffix = randomSuffix()
  const username = `${prefix}_${suffix}`
  const password = 'Password12345'

  const response = await request.post(`${API_BASE}/auth/register`, {
    data: {
      username,
      password,
      password_confirmation: password,
      role,
    },
  })

  expect(response.status()).toBe(201)
  const body = await response.json()

  return {
    username,
    password,
    token: body.token,
    user: body.user,
  }
}

const loginThroughUi = async (page, { username, password }) => {
  await page.goto(`${WEB_BASE}/login`)
  await page.getByLabel('Username').fill(username)
  await page.getByLabel('Password').fill(password)
  await page.getByRole('button', { name: 'Sign In' }).click()
  await expect(page).toHaveURL(/\/dashboard$/)
}

test('ride lifecycle + report export with auth boundaries', async ({ request, page }) => {
  let backendReady = false
  let frontendReady = false

  try {
    const backendProbe = await request.get(`${API_BASE.replace('/api/v1', '')}/up`, { failOnStatusCode: false })
    backendReady = backendProbe.status() > 0 && backendProbe.status() < 500
  } catch {
    backendReady = false
  }

  try {
    const frontendProbe = await request.get(`${WEB_BASE}/login`, { failOnStatusCode: false })
    frontendReady = frontendProbe.status() > 0 && frontendProbe.status() < 500
  } catch {
    frontendReady = false
  }

  const shouldSkipUnsupported = process.env.E2E_ALLOW_SKIP_UNSUPPORTED === '1'

  if (!(backendReady && frontendReady)) {
    if (shouldSkipUnsupported) {
      test.skip(true, 'Backend/frontend is not reachable in this environment.')
    }

    throw new Error(
      'E2E services are unavailable. Start backend/frontend first or set E2E_ALLOW_SKIP_UNSUPPORTED=1 for unsupported environments.',
    )
  }

  const rider = await registerUser(request, { role: 'rider', prefix: 'e2e_rider' })
  const driver = await registerUser(request, { role: 'driver', prefix: 'e2e_driver' })
  const manager = await registerUser(request, { role: 'fleet_manager', prefix: 'e2e_manager' })

  const createRide = await request.post(`${API_BASE}/ride-orders`, {
    headers: {
      Authorization: `Bearer ${rider.token}`,
    },
    data: {
      origin_address: '123 Main St',
      destination_address: 'Airport',
      rider_count: 2,
      time_window_start: new Date(Date.now() + 60 * 60 * 1000).toISOString().slice(0, 16).replace('T', ' '),
      time_window_end: new Date(Date.now() + 2 * 60 * 60 * 1000).toISOString().slice(0, 16).replace('T', ' '),
      notes: 'e2e flow',
    },
  })
  expect(createRide.status()).toBe(201)
  const ridePayload = await createRide.json()
  const rideId = ridePayload.order.id

  const available = await request.get(`${API_BASE}/driver/available-rides`, {
    headers: { Authorization: `Bearer ${driver.token}` },
  })
  expect(available.status()).toBe(200)

  const accept = await request.patch(`${API_BASE}/ride-orders/${rideId}/transition`, {
    headers: { Authorization: `Bearer ${driver.token}` },
    data: { action: 'accept' },
  })
  expect(accept.status()).toBe(200)

  const start = await request.patch(`${API_BASE}/ride-orders/${rideId}/transition`, {
    headers: { Authorization: `Bearer ${driver.token}` },
    data: { action: 'start' },
  })
  expect(start.status()).toBe(200)

  const complete = await request.patch(`${API_BASE}/ride-orders/${rideId}/transition`, {
    headers: { Authorization: `Bearer ${driver.token}` },
    data: { action: 'complete' },
  })
  expect(complete.status()).toBe(200)

  const show = await request.get(`${API_BASE}/ride-orders/${rideId}`, {
    headers: { Authorization: `Bearer ${rider.token}` },
  })
  expect(show.status()).toBe(200)
  const showPayload = await show.json()
  expect(showPayload.order.status).toBe('completed')

  const unauthorizedExport = await request.post(`${API_BASE}/reports/export`, {
    headers: { Authorization: `Bearer ${rider.token}` },
    data: { type: 'trends', format: 'csv', destination: 'qa' },
  })
  expect(unauthorizedExport.status()).toBe(403)

  const authorizedExport = await request.post(`${API_BASE}/reports/export`, {
    headers: { Authorization: `Bearer ${manager.token}` },
    data: { type: 'trends', format: 'csv', destination: 'qa' },
  })
  expect(authorizedExport.status()).toBe(200)

  await loginThroughUi(page, rider)
  await page.goto(`${WEB_BASE}/reports`)
  await expect(page).toHaveURL(/\/dashboard$/)
})
