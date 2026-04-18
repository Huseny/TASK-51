/**
 * Real HTTP integration tests — hit the live backend service.
 *
 * These tests run inside the Docker frontend container where the backend is
 * reachable via the Docker service name.  They are skipped automatically when
 * the backend cannot be reached so they never block a pure unit-test run.
 *
 * Override the base URL:  VITEST_API_URL=http://localhost:8000/api/v1 npx vitest
 */
import { beforeAll, describe, expect, it } from 'vitest'

const BASE = (process.env.VITEST_API_URL ?? 'http://backend:8000/api/v1').replace(/\/$/, '')
const HEALTH_URL = BASE.replace(/\/api\/v1$/, '')

// ── helpers ──────────────────────────────────────────────────────────────────

const uid = () => `http_test_${Date.now()}_${Math.random().toString(36).slice(2, 6)}`

const post = (path, body, token) =>
  fetch(`${BASE}${path}`, {
    method: 'POST',
    headers: {
      'Content-Type': 'application/json',
      ...(token ? { Authorization: `Bearer ${token}` } : {}),
    },
    body: JSON.stringify(body),
  })

const get = (path, token) =>
  fetch(`${BASE}${path}`, {
    headers: token ? { Authorization: `Bearer ${token}` } : {},
  })

const patch = (path, body, token) =>
  fetch(`${BASE}${path}`, {
    method: 'PATCH',
    headers: {
      'Content-Type': 'application/json',
      ...(token ? { Authorization: `Bearer ${token}` } : {}),
    },
    body: JSON.stringify(body),
  })

const registerAndLogin = async (role = 'rider') => {
  const username = uid()
  const password = 'Password1234'
  await post('/auth/register', { username, password, password_confirmation: password, role })
  const loginRes = await post('/auth/login', { username, password })
  const loginData = await loginRes.json()
  return { username, password, token: loginData.token }
}

// ── availability guard ────────────────────────────────────────────────────────

let backendAvailable = false

beforeAll(async () => {
  try {
    const res = await fetch(`${HEALTH_URL}/up`, { signal: AbortSignal.timeout(4000) })
    backendAvailable = res.ok
  } catch {
    backendAvailable = false
  }
}, 10000)

const maybeIt = (name, fn, timeout) =>
  it(name, async (...args) => {
    if (!backendAvailable) {
      console.warn(`[integration] backend unreachable at ${HEALTH_URL} — skipping: ${name}`)
      return
    }
    return fn(...args)
  }, timeout)

// ── tests ─────────────────────────────────────────────────────────────────────

describe('backend integration', () => {
  describe('health', () => {
    maybeIt('GET /up returns 200', async () => {
      const res = await fetch(`${HEALTH_URL}/up`)
      expect(res.status).toBe(200)
    })

    maybeIt('GET /api/v1/readiness returns ready status with expected shape', async () => {
      const res = await get('/readiness')
      expect(res.status).toBe(200)
      const data = await res.json()
      expect(data).toHaveProperty('status', 'ready')
      expect(data).toHaveProperty('checks')
      expect(data).toHaveProperty('required_migrations')
      expect(data).toHaveProperty('pending_required_migrations')
    })
  })

  describe('auth flow', () => {
    maybeIt('registers a new user and returns 201', async () => {
      const username = uid()
      const res = await post('/auth/register', {
        username,
        password: 'Password1234',
        password_confirmation: 'Password1234',
        role: 'rider',
      })
      expect(res.status).toBe(201)
      const data = await res.json()
      expect(data).toHaveProperty('token')
      expect(typeof data.token).toBe('string')
    })

    maybeIt('logs in with valid credentials and returns bearer token', async () => {
      const { token } = await registerAndLogin('rider')
      expect(typeof token).toBe('string')
      expect(token.length).toBeGreaterThan(10)
    })

    maybeIt('GET /auth/me returns the authenticated user', async () => {
      const { username, token } = await registerAndLogin('rider')
      const res = await get('/auth/me', token)
      expect(res.status).toBe(200)
      const data = await res.json()
      expect(data.user.username).toBe(username)
      expect(data.user).toHaveProperty('id')
      expect(data.user).toHaveProperty('role', 'rider')
    })

    maybeIt('GET /auth/me without token returns 401', async () => {
      const res = await get('/auth/me')
      expect(res.status).toBe(401)
      const data = await res.json()
      expect(data.error).toBe('unauthenticated')
    })

    maybeIt('POST /auth/login with wrong password returns 401', async () => {
      const username = uid()
      await post('/auth/register', {
        username,
        password: 'Password1234',
        password_confirmation: 'Password1234',
        role: 'rider',
      })
      const res = await post('/auth/login', { username, password: 'WrongPassword99' })
      expect(res.status).toBe(401)
    })

    maybeIt('POST /auth/register with too-short password returns 422', async () => {
      const res = await post('/auth/register', {
        username: uid(),
        password: 'abc',
        password_confirmation: 'abc',
        role: 'rider',
      })
      expect(res.status).toBe(422)
    })

    maybeIt('POST /auth/logout invalidates the bearer token', async () => {
      const { token } = await registerAndLogin('rider')

      const logoutRes = await post('/auth/logout', {}, token)
      expect(logoutRes.status).toBe(200)

      const meRes = await get('/auth/me', token)
      expect(meRes.status).toBe(401)
    })
  })

  describe('rider ride flow', () => {
    maybeIt('rider creates a ride order and it appears in their list', async () => {
      const { token } = await registerAndLogin('rider')

      const createRes = await post(
        '/ride-orders',
        {
          origin_address: '1 Integration St',
          destination_address: '2 Test Ave',
          origin_lat: 40.712776,
          origin_lng: -74.005974,
          destination_lat: 40.73061,
          destination_lng: -73.935242,
        },
        token,
      )
      expect(createRes.status).toBe(201)
      const created = await createRes.json()
      expect(created.order).toHaveProperty('id')
      expect(created.order.status).toBe('matching')

      const rideId = created.order.id

      const showRes = await get(`/ride-orders/${rideId}`, token)
      expect(showRes.status).toBe(200)
      const shown = await showRes.json()
      expect(shown.order.id).toBe(rideId)
      expect(shown.order).toHaveProperty('origin_address', '1 Integration St')
    })

    maybeIt('rider can list their own ride orders', async () => {
      const { token } = await registerAndLogin('rider')

      await post(
        '/ride-orders',
        {
          origin_address: 'A St',
          destination_address: 'B St',
          origin_lat: 1,
          origin_lng: 1,
          destination_lat: 2,
          destination_lng: 2,
        },
        token,
      )

      const listRes = await get('/ride-orders', token)
      expect(listRes.status).toBe(200)
      const listData = await listRes.json()
      expect(listData).toHaveProperty('data')
      expect(Array.isArray(listData.data)).toBe(true)
      expect(listData.data.length).toBeGreaterThanOrEqual(1)
    })

    maybeIt('driver cannot create ride orders (403)', async () => {
      const { token } = await registerAndLogin('driver')
      const res = await post(
        '/ride-orders',
        {
          origin_address: 'X',
          destination_address: 'Y',
          origin_lat: 1,
          origin_lng: 1,
          destination_lat: 2,
          destination_lng: 2,
        },
        token,
      )
      expect(res.status).toBe(403)
    })
  })

  describe('driver endpoints', () => {
    maybeIt('driver sees available rides list', async () => {
      const { token } = await registerAndLogin('driver')
      const res = await get('/driver/available-rides', token)
      expect(res.status).toBe(200)
      const data = await res.json()
      expect(data).toHaveProperty('data')
      expect(Array.isArray(data.data)).toBe(true)
    })

    maybeIt('rider cannot access driver available-rides (403)', async () => {
      const { token } = await registerAndLogin('rider')
      const res = await get('/driver/available-rides', token)
      expect(res.status).toBe(403)
    })

    maybeIt('driver can list their own rides', async () => {
      const { token } = await registerAndLogin('driver')
      const res = await get('/driver/my-rides', token)
      expect(res.status).toBe(200)
      const data = await res.json()
      expect(data).toHaveProperty('data')
    })
  })

  describe('notifications', () => {
    maybeIt('authenticated user can fetch notifications list', async () => {
      const { token } = await registerAndLogin('rider')
      const res = await get('/notifications', token)
      expect(res.status).toBe(200)
      const data = await res.json()
      expect(data).toHaveProperty('data')
      expect(Array.isArray(data.data)).toBe(true)
    })

    maybeIt('unauthenticated request to /notifications returns 401', async () => {
      const res = await get('/notifications')
      expect(res.status).toBe(401)
    })

    maybeIt('authenticated user can fetch unread notification count', async () => {
      const { token } = await registerAndLogin('rider')
      const res = await get('/notifications/unread-count', token)
      expect(res.status).toBe(200)
      const data = await res.json()
      expect(data).toHaveProperty('count')
      expect(typeof data.count).toBe('number')
    })

    maybeIt('PATCH /notifications/read-all marks all notifications read', async () => {
      const { token } = await registerAndLogin('rider')

      const patchRes = await patch('/notifications/read-all', {}, token)
      expect(patchRes.status).toBe(200)

      const countRes = await get('/notifications/unread-count', token)
      const countData = await countRes.json()
      expect(countData.count).toBe(0)
    })
  })

  describe('products', () => {
    maybeIt('authenticated user can list products', async () => {
      const { token } = await registerAndLogin('rider')
      const res = await get('/products', token)
      expect(res.status).toBe(200)
      const data = await res.json()
      expect(data).toHaveProperty('data')
      expect(Array.isArray(data.data)).toBe(true)
    })

    maybeIt('unauthenticated request to /products returns 401', async () => {
      const res = await get('/products')
      expect(res.status).toBe(401)
    })
  })

  describe('api contract shapes', () => {
    maybeIt('ride order response includes expected fields', async () => {
      const { token } = await registerAndLogin('rider')
      const createRes = await post(
        '/ride-orders',
        {
          origin_address: 'Contract Test Origin',
          destination_address: 'Contract Test Dest',
          origin_lat: 51.5,
          origin_lng: -0.1,
          destination_lat: 51.51,
          destination_lng: -0.09,
        },
        token,
      )
      const { order } = await createRes.json()
      expect(order).toHaveProperty('id')
      expect(order).toHaveProperty('status')
      expect(order).toHaveProperty('origin_address')
      expect(order).toHaveProperty('destination_address')
      expect(order).toHaveProperty('created_at')
    })

    maybeIt('/auth/me response includes user object with expected fields', async () => {
      const { token } = await registerAndLogin('driver')
      const res = await get('/auth/me', token)
      const { user } = await res.json()
      expect(user).toHaveProperty('id')
      expect(user).toHaveProperty('username')
      expect(user).toHaveProperty('role')
      expect(user).not.toHaveProperty('password')
    })
  })
})
