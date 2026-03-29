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
const getPendingActionsByOwner = vi.fn()
const removePendingAction = vi.fn()
const clearPendingActions = vi.fn()

vi.mock('@/services/offlineQueue', () => ({
  enqueuePendingAction,
  getPendingActionsByOwner,
  removePendingAction,
  clearPendingActions,
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
    localStorage.setItem('roadlink_token', 'token-offline')
    localStorage.setItem('roadlink_user', JSON.stringify({ id: 11 }))

    const response = await api.post('/ride-orders/1/transition', { action: 'start' })

    expect(response.status).toBe(202)
    expect(response.data.queued).toBe(true)
    expect(enqueuePendingAction).toHaveBeenCalledTimes(1)
    const queuedAction = enqueuePendingAction.mock.calls[0][0]
    expect(queuedAction.owner_key).toBe('user:11')
    expect(queuedAction.headers.Authorization).toBeUndefined()
    expect(queuedAction.headers['X-Idempotency-Key']).toBeTruthy()
    expect(requestMock).not.toHaveBeenCalled()
  })

  it('syncs queued requests using current token and clears queue item', async () => {
    const { syncPendingActions, __setOnlineStateForTests } = await import('@/services/api')
    __setOnlineStateForTests(true)
    localStorage.setItem('roadlink_token', 'token-current-user')
    localStorage.setItem('roadlink_user', JSON.stringify({ id: 99 }))

    getPendingActionsByOwner.mockResolvedValueOnce([
      {
        id: 'q1',
        url: '/group-chats/7/messages',
        method: 'POST',
        data: { content: 'hello' },
        headers: { 'X-Idempotency-Key': 'abc' },
        owner_key: 'user:99',
        timestamp: 1,
      },
    ])
    requestMock.mockResolvedValueOnce({ status: 201, data: { ok: true } })

    await syncPendingActions()

    expect(requestMock).toHaveBeenCalledWith({
      url: '/group-chats/7/messages',
      method: 'POST',
      data: { content: 'hello' },
      headers: { 'X-Idempotency-Key': 'abc', Authorization: 'Bearer token-current-user' },
    })
    expect(removePendingAction).toHaveBeenCalledWith('q1')
  })

  it('clearOfflineQueue clears all pending actions (logout/user switch)', async () => {
    const { clearOfflineQueue } = await import('@/services/api')
    await clearOfflineQueue()
    expect(clearPendingActions).toHaveBeenCalledTimes(1)
  })

  it('does not replay another users queued actions', async () => {
    const { syncPendingActions, __setOnlineStateForTests } = await import('@/services/api')
    __setOnlineStateForTests(true)
    localStorage.setItem('roadlink_token', 'token-user-b')
    localStorage.setItem('roadlink_user', JSON.stringify({ id: 202 }))

    getPendingActionsByOwner.mockResolvedValueOnce([])

    await syncPendingActions()

    expect(requestMock).not.toHaveBeenCalled()
    expect(removePendingAction).not.toHaveBeenCalled()
  })
})
