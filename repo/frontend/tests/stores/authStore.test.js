import { beforeEach, describe, expect, it, vi } from 'vitest'
import { createPinia, setActivePinia } from 'pinia'
import { useAuthStore } from '@/stores/authStore'
import api, { clearOfflineQueue } from '@/services/api'

vi.mock('@/services/api', () => ({
  default: {
    post: vi.fn(),
    get: vi.fn(),
  },
  clearOfflineQueue: vi.fn(),
}))

describe('authStore', () => {
  beforeEach(() => {
    setActivePinia(createPinia())
    localStorage.clear()
    vi.clearAllMocks()
  })

  it('login action sets user and token on success', async () => {
    api.post.mockResolvedValueOnce({
      data: {
        user: { id: 1, username: 'rider01', role: 'rider' },
        token: 'token-abc',
      },
    })

    const store = useAuthStore()
    const result = await store.login('rider01', 'Rider12345!')

    expect(result.success).toBe(true)
    expect(store.user.username).toBe('rider01')
    expect(store.token).toBe('token-abc')
    expect(localStorage.getItem('roadlink_token')).toBe('token-abc')
  })

  it('login action sets error on failure', async () => {
    api.post.mockRejectedValueOnce({
      response: {
        data: { message: 'Invalid username or password', error: 'invalid_credentials' },
      },
    })

    const store = useAuthStore()
    const result = await store.login('ghost', 'bad-password')

    expect(result.success).toBe(false)
    expect(store.error).toBe('Invalid username or password')
  })

  it('logout action clears state and localStorage', async () => {
    api.post.mockResolvedValueOnce({ data: { message: 'Logged out successfully' } })
    const store = useAuthStore()
    store.persistSession({ id: 1, username: 'driver01', role: 'driver' }, 'token-1')

    await store.logout()

    expect(store.user).toBeNull()
    expect(store.token).toBeNull()
    expect(localStorage.getItem('roadlink_token')).toBeNull()
    expect(clearOfflineQueue).toHaveBeenCalled()
  })

  it('initialize restores from localStorage', async () => {
    localStorage.setItem('roadlink_token', 'persisted-token')
    localStorage.setItem('roadlink_user', JSON.stringify({ id: 5, username: 'fleet01', role: 'fleet_manager' }))
    api.get.mockResolvedValueOnce({ data: { user: { id: 5, username: 'fleet01', role: 'fleet_manager' } } })

    const store = useAuthStore()
    await store.initialize()

    expect(store.token).toBe('persisted-token')
    expect(store.user.username).toBe('fleet01')
    expect(store.isAuthenticated).toBe(true)
  })

  it('switching authenticated user clears offline queue', () => {
    const store = useAuthStore()

    store.persistSession({ id: 1, username: 'rider01', role: 'rider' }, 'token-a')
    store.persistSession({ id: 2, username: 'driver01', role: 'driver' }, 'token-b')

    expect(clearOfflineQueue).toHaveBeenCalled()
  })
})
