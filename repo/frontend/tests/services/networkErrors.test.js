import { beforeEach, describe, expect, it, vi } from 'vitest'

// Mock axios at the top so the transport instance gets our mock
const requestMock = vi.fn()

vi.mock('axios', () => ({
  default: {
    create: vi.fn(() => ({
      request: requestMock,
    })),
  },
}))

vi.mock('@/services/offlineQueue', () => ({
  enqueuePendingAction: vi.fn(),
  getPendingActionsByOwner: vi.fn().mockResolvedValue([]),
  removePendingAction: vi.fn(),
  clearPendingActions: vi.fn(),
}))

describe('api error handling', () => {
  beforeEach(async () => {
    vi.resetModules()
    vi.clearAllMocks()
    localStorage.clear()
    sessionStorage.clear()

    // Re-import mocks after resetModules
    const offlineQueue = await import('@/services/offlineQueue')
    offlineQueue.getPendingActionsByOwner.mockResolvedValue([])
  })

  it('propagates 500 server error to caller', async () => {
    const serverError = {
      response: {
        status: 500,
        data: { message: 'Internal Server Error' },
      },
    }
    requestMock.mockRejectedValueOnce(serverError)

    const { default: api, __setOnlineStateForTests } = await import('@/services/api')
    __setOnlineStateForTests(true)

    await expect(api.get('/some-endpoint')).rejects.toMatchObject({
      response: { status: 500 },
    })
  })

  it('propagates 404 not found error to caller', async () => {
    const notFoundError = {
      response: {
        status: 404,
        data: { message: 'Resource not found' },
      },
    }
    requestMock.mockRejectedValueOnce(notFoundError)

    const { default: api, __setOnlineStateForTests } = await import('@/services/api')
    __setOnlineStateForTests(true)

    await expect(api.get('/missing-resource')).rejects.toMatchObject({
      response: { status: 404 },
    })
  })

  it('invokes unauthorized handler and clears token on 401 response', async () => {
    const unauthorizedError = {
      response: {
        status: 401,
        data: { message: 'Unauthenticated', error: 'unauthenticated' },
      },
    }
    requestMock.mockRejectedValueOnce(unauthorizedError)
    localStorage.setItem('roadlink_auth_token', 'stale-token')

    const unauthorizedCallback = vi.fn()
    const { default: api, setUnauthorizedHandler, __setOnlineStateForTests } = await import('@/services/api')
    __setOnlineStateForTests(true)
    setUnauthorizedHandler(unauthorizedCallback)

    await expect(api.get('/protected')).rejects.toMatchObject({
      response: { status: 401 },
    })

    expect(unauthorizedCallback).toHaveBeenCalled()
  })

  it('propagates network-level error (no response) to caller', async () => {
    const networkError = new Error('Network Error')
    requestMock.mockRejectedValueOnce(networkError)

    const { default: api, __setOnlineStateForTests } = await import('@/services/api')
    __setOnlineStateForTests(true)

    await expect(api.get('/endpoint')).rejects.toThrow('Network Error')
  })

  it('queues mutation when offline instead of throwing', async () => {
    const { default: api, __setOnlineStateForTests } = await import('@/services/api')
    __setOnlineStateForTests(false)
    localStorage.setItem('roadlink_user', JSON.stringify({ id: 7 }))

    const offlineQueue = await import('@/services/offlineQueue')

    const response = await api.post('/ride-orders', { origin_address: '1 Main St' })

    expect(response.status).toBe(202)
    expect(response.data.queued).toBe(true)
    expect(offlineQueue.enqueuePendingAction).toHaveBeenCalledTimes(1)
    expect(requestMock).not.toHaveBeenCalled()
  })

  it('marks sync failure in sessionStorage when queued request fails with HTTP error', async () => {
    const { syncPendingActions, __setOnlineStateForTests } = await import('@/services/api')
    __setOnlineStateForTests(true)

    localStorage.setItem('roadlink_user', JSON.stringify({ id: 42 }))
    localStorage.setItem('roadlink_auth_token', 'tok-42')

    const offlineQueue = await import('@/services/offlineQueue')
    offlineQueue.getPendingActionsByOwner.mockResolvedValueOnce([
      {
        id: 'fail-1',
        url: '/ride-orders/99/transition',
        method: 'PATCH',
        data: { action: 'start' },
        headers: { 'X-Idempotency-Key': 'idem-fail' },
        owner_key: 'user:42',
        timestamp: Date.now(),
      },
    ])

    requestMock.mockRejectedValueOnce({
      response: {
        status: 422,
        data: { message: 'Invalid transition' },
      },
    })

    await syncPendingActions()

    const toastMsg = sessionStorage.getItem('roadlink_toast_message')
    expect(toastMsg).toBeTruthy()
    expect(toastMsg).toContain('PATCH')
    expect(toastMsg).toContain('/ride-orders/99/transition')

    expect(offlineQueue.removePendingAction).toHaveBeenCalledWith('fail-1')
  })

  it('GET requests are never queued when offline', async () => {
    const { default: api, __setOnlineStateForTests } = await import('@/services/api')
    __setOnlineStateForTests(false)

    requestMock.mockRejectedValueOnce(new Error('Network Error'))

    const offlineQueue = await import('@/services/offlineQueue')

    await expect(api.get('/ride-orders')).rejects.toThrow()
    expect(offlineQueue.enqueuePendingAction).not.toHaveBeenCalled()
  })

  it('bearer token is included in the Authorization header', async () => {
    requestMock.mockResolvedValueOnce({ data: { ok: true } })
    localStorage.setItem('roadlink_auth_token', 'my-bearer-token')

    const { default: api, __setOnlineStateForTests } = await import('@/services/api')
    __setOnlineStateForTests(true)

    await api.get('/notifications')

    const calledConfig = requestMock.mock.calls[0][0]
    expect(calledConfig.headers.Authorization).toBe('Bearer my-bearer-token')
  })

  it('requests without a token have no Authorization header', async () => {
    requestMock.mockResolvedValueOnce({ data: {} })
    localStorage.removeItem('roadlink_auth_token')

    const { default: api, __setOnlineStateForTests } = await import('@/services/api')
    __setOnlineStateForTests(true)

    await api.get('/public-endpoint')

    const calledConfig = requestMock.mock.calls[0][0]
    expect(calledConfig.headers.Authorization).toBeUndefined()
  })
})
