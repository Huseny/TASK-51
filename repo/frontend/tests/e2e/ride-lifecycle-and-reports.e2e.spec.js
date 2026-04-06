import { expect, test } from '@playwright/test'

const API_BASE = process.env.E2E_API_URL || 'http://127.0.0.1:8000/api/v1'
const WEB_BASE = process.env.E2E_WEB_URL || 'http://127.0.0.1:3000'
const API_ROOT = API_BASE.replace('/api/v1', '')

const randomSuffix = () => `${Date.now()}${Math.floor(Math.random() * 10000)}`
const shouldSkipUnsupported = process.env.E2E_ALLOW_SKIP_UNSUPPORTED === '1'

const ensureServicesOrSkip = async (request) => {
  let backendReady = false
  let frontendReady = false

  try {
    const backendProbe = await request.get(`${API_ROOT}/up`, { failOnStatusCode: false })
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

  if (backendReady && frontendReady) {
    return
  }

  if (shouldSkipUnsupported) {
    test.skip(true, 'Backend/frontend is not reachable in this environment.')
  }

  throw new Error(
    'E2E services are unavailable. Start backend/frontend first or set E2E_ALLOW_SKIP_UNSUPPORTED=1 for unsupported environments.',
  )
}

const postAsBearer = async (apiContext, path, data) => apiContext.post(`${API_BASE}${path}`, { data })

const patchAsBearer = async (apiContext, path, data) => apiContext.patch(`${API_BASE}${path}`, { data })

const createBearerClient = async (playwright, credentials) => {
  const loginContext = await playwright.request.newContext()
  const loginResponse = await loginContext.post(`${API_BASE}/auth/login`, {
    data: {
      username: credentials.username,
      password: credentials.password,
    },
  })
  expect(loginResponse.status()).toBe(200)
  const loginPayload = await loginResponse.json()
  expect(loginPayload.token).toBeTruthy()
  await loginContext.dispose()

  return playwright.request.newContext({
    extraHTTPHeaders: {
      Authorization: `Bearer ${loginPayload.token}`,
    },
  })
}

const closeContexts = async (contexts) => {
  await Promise.all(contexts.map((context) => context.dispose()))
}

const timeWindowPayload = () => ({
  time_window_start: new Date(Date.now() + 60 * 60 * 1000).toISOString().slice(0, 16).replace('T', ' '),
  time_window_end: new Date(Date.now() + 2 * 60 * 60 * 1000).toISOString().slice(0, 16).replace('T', ' '),
})

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

  return {
    username,
    password,
  }
}

const ensureLoginPage = async (page) => {
  await page.goto(`${WEB_BASE}/login`)

  if (await page.getByLabel('Username').count()) {
    return
  }

  await page.context().clearCookies()
  await page.evaluate(() => {
    localStorage.clear()
    sessionStorage.clear()
  })
  await page.goto(`${WEB_BASE}/login`)
}

const loginThroughUi = async (page, { username, password }) => {
  await ensureLoginPage(page)
  await page.getByLabel('Username').fill(username)
  await page.getByLabel('Password').fill(password)
  await page.getByRole('button', { name: 'Sign In' }).click()
  await expect(page).toHaveURL(/\/dashboard$/)
}

const ensureRiderTripsPage = async (page, credentials) => {
  await page.goto(`${WEB_BASE}/rider/trips`)

  if (/\/login$/.test(new URL(page.url()).pathname)) {
    await loginThroughUi(page, credentials)
    await page.goto(`${WEB_BASE}/rider/trips`)
  }

  await expect(page).toHaveURL(/\/rider\/trips$/)
}

const countPendingActions = async (page) => page.evaluate(async () => {
  const openDb = () => new Promise((resolve, reject) => {
    const request = indexedDB.open('roadlink-offline', 1)
    request.onerror = () => reject(request.error)
    request.onsuccess = () => resolve(request.result)
    request.onupgradeneeded = () => {
      const db = request.result
      if (!db.objectStoreNames.contains('pending-actions')) {
        db.createObjectStore('pending-actions', { keyPath: 'id' })
      }
    }
  })

  const db = await openDb()

  return new Promise((resolve, reject) => {
    const transaction = db.transaction('pending-actions', 'readonly')
    const store = transaction.objectStore('pending-actions')
    const countRequest = store.count()

    countRequest.onerror = () => reject(countRequest.error)
    countRequest.onsuccess = () => resolve(countRequest.result)
  })
})

test('ride lifecycle + report export with auth boundaries', async ({ request, page, playwright }) => {
  await ensureServicesOrSkip(request)

  const rider = await registerUser(request, { role: 'rider', prefix: 'e2e_rider' })
  const driver = await registerUser(request, { role: 'driver', prefix: 'e2e_driver' })
  const manager = await registerUser(request, { role: 'fleet_manager', prefix: 'e2e_manager' })

  const riderApi = await createBearerClient(playwright, rider)
  const driverApi = await createBearerClient(playwright, driver)
  const managerApi = await createBearerClient(playwright, manager)

  try {
    const createRide = await postAsBearer(riderApi, '/ride-orders', {
      origin_address: '123 Main St',
      destination_address: 'Airport',
      rider_count: 2,
      ...timeWindowPayload(),
      notes: 'e2e flow',
    })
    expect(createRide.status()).toBe(201)
    const ridePayload = await createRide.json()
    const rideId = ridePayload.order.id

    const available = await driverApi.get(`${API_BASE}/driver/available-rides`)
    expect(available.status()).toBe(200)

    const accept = await patchAsBearer(driverApi, `/ride-orders/${rideId}/transition`, { action: 'accept' })
    expect(accept.status()).toBe(200)

    const start = await patchAsBearer(driverApi, `/ride-orders/${rideId}/transition`, { action: 'start' })
    expect(start.status()).toBe(200)

    const complete = await patchAsBearer(driverApi, `/ride-orders/${rideId}/transition`, { action: 'complete' })
    expect(complete.status()).toBe(200)

    const show = await riderApi.get(`${API_BASE}/ride-orders/${rideId}`)
    expect(show.status()).toBe(200)
    const showPayload = await show.json()
    expect(showPayload.order.status).toBe('completed')

    const unauthorizedExport = await postAsBearer(riderApi, '/reports/export', {
      type: 'trends',
      format: 'csv',
      destination: 'qa',
      directory_id: 'default',
    })
    expect(unauthorizedExport.status()).toBe(403)

    const authorizedExport = await postAsBearer(managerApi, '/reports/export', {
      type: 'trends',
      format: 'csv',
      destination: 'qa',
      directory_id: 'default',
    })
    expect(authorizedExport.status()).toBe(200)
  } finally {
    await closeContexts([riderApi, driverApi, managerApi])
  }

  await loginThroughUi(page, rider)
  await page.goto(`${WEB_BASE}/reports`)
  await expect(page).toHaveURL(/\/dashboard$/)
})

test('cross-user login does not leak cached rider state', async ({ request, page, playwright }) => {
  await ensureServicesOrSkip(request)

  const riderA = await registerUser(request, { role: 'rider', prefix: 'e2e_isolated_a' })
  const riderB = await registerUser(request, { role: 'rider', prefix: 'e2e_isolated_b' })

  const riderAApi = await createBearerClient(playwright, riderA)
  const uniqueOrigin = `A-Origin-${randomSuffix()}`
  const uniqueDestination = `A-Destination-${randomSuffix()}`

  try {
    const createRide = await postAsBearer(riderAApi, '/ride-orders', {
      origin_address: uniqueOrigin,
      destination_address: uniqueDestination,
      rider_count: 1,
      ...timeWindowPayload(),
      notes: 'cache isolation check',
    })
    expect(createRide.status()).toBe(201)
  } finally {
    await closeContexts([riderAApi])
  }

  await loginThroughUi(page, riderA)
  await ensureRiderTripsPage(page, riderA)
  await expect(page.getByText(uniqueOrigin)).toBeVisible()

  await page.evaluate(() => {
    localStorage.setItem('roadlink_chat_unread_total', '9')
  })

  await page.getByRole('button', { name: 'Logout' }).click()
  await ensureLoginPage(page)

  await loginThroughUi(page, riderB)
  await ensureRiderTripsPage(page, riderB)

  await expect(page.getByText(uniqueOrigin)).toHaveCount(0)
  await expect(page.getByText(riderB.username)).toBeVisible()

  const unreadCache = await page.evaluate(() => localStorage.getItem('roadlink_chat_unread_total'))
  expect(unreadCache).toBeNull()
})

test('offline queued ride mutation replays after reconnect', async ({ request, page }) => {
  await ensureServicesOrSkip(request)

  const rider = await registerUser(request, { role: 'rider', prefix: 'e2e_offline_rider' })

  await loginThroughUi(page, rider)
  await ensureRiderTripsPage(page, rider)

  const uniqueOrigin = `Offline-Origin-${randomSuffix()}`
  const uniqueDestination = `Offline-Destination-${randomSuffix()}`
  await page.evaluate(async ({ origin, destination }) => {
    const { enqueuePendingAction } = await import('/src/services/offlineQueue.js')

    const user = JSON.parse(localStorage.getItem('roadlink_user') || '{}')
    const ownerKey = user?.id ? `user:${user.id}` : 'anonymous'
    const start = new Date(Date.now() + 90 * 60 * 1000)
    const end = new Date(Date.now() + 150 * 60 * 1000)
    const toBackend = (value) => value.toISOString().slice(0, 16).replace('T', ' ')

    await enqueuePendingAction({
      id: `e2e-${Date.now()}`,
      url: '/ride-orders',
      method: 'POST',
      data: {
        origin_address: origin,
        destination_address: destination,
        rider_count: 1,
        time_window_start: toBackend(start),
        time_window_end: toBackend(end),
        notes: 'queued offline in e2e',
      },
      headers: {
        'X-Idempotency-Key': `e2e-${Date.now()}-${Math.random().toString(16).slice(2)}`,
      },
      owner_key: ownerKey,
      timestamp: Date.now(),
    })
  }, { origin: uniqueOrigin, destination: uniqueDestination })

  await expect.poll(() => countPendingActions(page)).toBeGreaterThan(0)

  await page.evaluate(async () => {
    const { syncPendingActions } = await import('/src/services/api.js')
    await syncPendingActions()
  })

  await expect.poll(() => countPendingActions(page), { timeout: 15000 }).toBe(0)

  await page.reload()
  await expect(page.getByText(uniqueOrigin)).toBeVisible()
})

test('notification center covers comment/reply/mention/follower/moderation/announcement flows', async ({ request, page, playwright }) => {
  await ensureServicesOrSkip(request)

  const recipient = await registerUser(request, { role: 'rider', prefix: 'e2e_notify_recipient' })
  const commenter = await registerUser(request, { role: 'driver', prefix: 'e2e_notify_commenter' })
  const moderator = await registerUser(request, { role: 'fleet_manager', prefix: 'e2e_notify_moderator' })

  const recipientApi = await createBearerClient(playwright, recipient)
  const commenterApi = await createBearerClient(playwright, commenter)
  const moderatorApi = await createBearerClient(playwright, moderator)
  let recipientCookies = []

  try {
    const meRes = await recipientApi.get(`${API_BASE}/auth/me`)
    expect(meRes.status()).toBe(200)
    const mePayload = await meRes.json()
    const recipientId = mePayload.user.id

    const createRide = await postAsBearer(recipientApi, '/ride-orders', {
      origin_address: `Notify Origin ${randomSuffix()}`,
      destination_address: `Notify Destination ${randomSuffix()}`,
      rider_count: 1,
      ...timeWindowPayload(),
      notes: 'notification scenario context',
    })
    expect(createRide.status()).toBe(201)
    const rideId = (await createRide.json()).order.id

    const acceptRide = await patchAsBearer(commenterApi, `/ride-orders/${rideId}/transition`, { action: 'accept' })
    expect(acceptRide.status()).toBe(200)

    const createEventFromCommenter = async (scenario) => {
      const response = await postAsBearer(commenterApi, '/notifications/events', {
        scenario,
        recipient_id: recipientId,
        ride_id: rideId,
      })

      expect(response.status()).toBe(201)
    }

    const createModeratorEvent = async (scenario, message = '') => {
      const response = await postAsBearer(moderatorApi, '/notifications/events', {
        scenario,
        recipient_id: recipientId,
        message,
      })

      expect(response.status()).toBe(201)
    }

    await createEventFromCommenter('comment')
    await createEventFromCommenter('reply')
    await createEventFromCommenter('reply')
    await createEventFromCommenter('mention')

    const followResponse = await postAsBearer(commenterApi, '/notification-subscriptions', {
      entity_type: 'follow_user',
      entity_id: recipientId,
    })
    expect(followResponse.status()).toBe(201)

    const followerEvent = await postAsBearer(commenterApi, '/notifications/events', {
      scenario: 'follower',
      recipient_id: recipientId,
    })
    expect(followerEvent.status()).toBe(201)

    await createModeratorEvent('moderation', 'Content review completed')
    await createModeratorEvent('announcement', 'Platform update available')

    recipientCookies = (await recipientApi.storageState()).cookies
  } finally {
    await closeContexts([recipientApi, commenterApi, moderatorApi])
  }

  await page.context().addCookies(recipientCookies)
  await page.goto(`${WEB_BASE}/dashboard`)
  await expect(page).toHaveURL(/\/dashboard$/)
  await page.getByRole('button', { name: 'Bell' }).click()

  const expectedScenarios = ['comment', 'reply', 'mention', 'follower', 'moderation', 'announcement']

  await expect
    .poll(async () => {
      const labels = await page.locator('.panel .pill').allTextContents()
      const normalized = labels.map((label) => label.trim().toLowerCase())

      return expectedScenarios.every((scenario) => normalized.includes(scenario))
    }, { timeout: 15000 })
    .toBe(true)

  await expect(page.locator('.pill', { hasText: 'comment' })).toBeVisible()
  await expect(page.locator('.pill', { hasText: 'reply' })).toBeVisible()
  await expect(page.locator('.pill', { hasText: 'mention' })).toBeVisible()
  await expect(page.locator('.pill', { hasText: 'follower' })).toBeVisible()
  await expect(page.locator('.pill', { hasText: 'moderation' })).toBeVisible()
  await expect(page.locator('.pill', { hasText: 'announcement' })).toBeVisible()
  await expect(page.getByText('2 new replies')).toBeVisible()

  await expect(page.locator('.panel .item').filter({ hasText: 'Moderation update' }).first()).toBeVisible({ timeout: 15000 })
  await expect(page.getByRole('button', { name: 'Notification settings' })).toBeVisible()
})
