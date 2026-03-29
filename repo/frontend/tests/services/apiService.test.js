import { beforeEach, describe, expect, it, vi } from 'vitest'

const requestMock = vi.fn()

vi.mock('axios', () => ({
  default: {
    create: vi.fn(() => ({
      request: requestMock,
    })),
  },
}))

const enqueuePendingAction = vi.fn()
const getPendingActions = vi.fn()
const removePendingAction = vi.fn()

vi.mock('@/services/offlineQueue', () => ({
  enqueuePendingAction,
  getPendingActions,
  removePendingAction,
}))

describe('api offline queue', () => {
  beforeEach(() => {
    vi.resetModules()
    vi.clearAllMocks()
    localStorage.clear()
    sessionStorage.clear()
  })

  it('queues mutating request when offline and resolves optimistically', async () => {
    const { default: api, __setOnlineStateForTests } = await import('@/services/api')
    __setOnlineStateForTests(false)

    const response = await api.post('/ride-orders/1/transition', { action: 'start' })

    expect(response.status).toBe(202)
    expect(response.data.queued).toBe(true)
    expect(enqueuePendingAction).toHaveBeenCalledTimes(1)
    expect(requestMock).not.toHaveBeenCalled()
  })

  it('syncs queued requests when online and clears queue', async () => {
    const { syncPendingActions, __setOnlineStateForTests } = await import('@/services/api')
    __setOnlineStateForTests(true)

    getPendingActions.mockResolvedValueOnce([
      {
        id: 'q1',
        url: '/group-chats/7/messages',
        method: 'POST',
        data: { content: 'hello' },
        headers: { 'X-Idempotency-Key': 'abc' },
        timestamp: 1,
      },
    ])
    requestMock.mockResolvedValueOnce({ status: 201, data: { ok: true } })

    await syncPendingActions()

    expect(requestMock).toHaveBeenCalledWith({
      url: '/group-chats/7/messages',
      method: 'POST',
      data: { content: 'hello' },
      headers: { 'X-Idempotency-Key': 'abc' },
    })
    expect(removePendingAction).toHaveBeenCalledWith('q1')
  })
})
