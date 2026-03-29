import { beforeEach, describe, expect, it, vi } from 'vitest'
import { createPinia, setActivePinia } from 'pinia'
import { useAuthStore } from '@/stores/authStore'
import api, { clearOfflineQueue, ensureCsrfCookie } from '@/services/api'

vi.mock('@/services/api', () => ({
  default: {
    post: vi.fn(),
    get: vi.fn(),
  },
  clearOfflineQueue: vi.fn(),
  ensureCsrfCookie: vi.fn(),
}))

describe('authStore', () => {
  beforeEach(() => {
    setActivePinia(createPinia())
    localStorage.clear()
    vi.clearAllMocks()
  })

  it('login action sets authenticated session on success', async () => {
    ensureCsrfCookie.mockResolvedValueOnce({})
    api.post.mockResolvedValueOnce({
      data: {
        user: { id: 1, username: 'rider01', role: 'rider' },
      },
    })

    const store = useAuthStore()
    const result = await store.login('rider01', 'Rider12345!')

    expect(result.success).toBe(true)
    expect(store.user.username).toBe('rider01')
    expect(store.isAuthenticated).toBe(true)
    expect(localStorage.getItem('roadlink_user')).toContain('rider01')
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
    store.persistSession({ id: 1, username: 'driver01', role: 'driver' })

    await store.logout()

    expect(store.user).toBeNull()
    expect(store.isAuthenticated).toBe(false)
    expect(localStorage.getItem('roadlink_user')).toBeNull()
    expect(clearOfflineQueue).toHaveBeenCalled()
  })

  it('initialize restores session-backed user', async () => {
    localStorage.setItem('roadlink_user', JSON.stringify({ id: 5, username: 'fleet01', role: 'fleet_manager' }))
    api.get.mockResolvedValueOnce({ data: { user: { id: 5, username: 'fleet01', role: 'fleet_manager' } } })

    const store = useAuthStore()
    await store.initialize()

    expect(store.user.username).toBe('fleet01')
    expect(store.isAuthenticated).toBe(true)
  })

  it('switching authenticated user clears offline queue', () => {
    const store = useAuthStore()

    store.persistSession({ id: 1, username: 'rider01', role: 'rider' })
    store.persistSession({ id: 2, username: 'driver01', role: 'driver' })

    expect(clearOfflineQueue).toHaveBeenCalled()
  })

  it('forceLogout clears sensitive local/session artifacts', () => {
    const store = useAuthStore()
    localStorage.setItem('roadlink_chat_unread_total', '5')
    sessionStorage.setItem('roadlink_toast_message', 'toast')
    sessionStorage.setItem('roadlink_toast_type', 'error')

    store.persistSession({ id: 3, username: 'admin01', role: 'admin' })
    store.forceLogout()

    expect(store.user).toBeNull()
    expect(store.isAuthenticated).toBe(false)
    expect(localStorage.getItem('roadlink_chat_unread_total')).toBeNull()
    expect(sessionStorage.getItem('roadlink_toast_message')).toBeNull()
    expect(sessionStorage.getItem('roadlink_toast_type')).toBeNull()
    expect(clearOfflineQueue).toHaveBeenCalled()
  })
})
