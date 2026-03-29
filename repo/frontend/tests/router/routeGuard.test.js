import { beforeEach, describe, expect, it, vi } from 'vitest'
import { createPinia, setActivePinia } from 'pinia'

vi.mock('@/services/api', () => ({
  default: {
    get: vi.fn(),
    post: vi.fn(),
    patch: vi.fn(),
    put: vi.fn(),
    delete: vi.fn(),
  },
  clearOfflineQueue: vi.fn(),
  setUnauthorizedHandler: vi.fn(),
  syncPendingActions: vi.fn(),
}))

import router from '@/router'
import api from '@/services/api'
import { useAuthStore } from '@/stores/authStore'

describe('route guards', () => {
  beforeEach(async () => {
    setActivePinia(createPinia())
    localStorage.clear()
    sessionStorage.clear()
    vi.clearAllMocks()
    await router.push('/login')
  })

  it('redirects unauthenticated users to login for protected routes', async () => {
    const store = useAuthStore()
    store.initialized = true

    await router.push('/dashboard')

    expect(router.currentRoute.value.path).toBe('/login')
  })

  it('redirects user without allowed role from protected route', async () => {
    const store = useAuthStore()
    store.persistSession({ id: 1, username: 'driver01', role: 'driver' })
    store.initialized = true

    await router.push('/reports')

    expect(router.currentRoute.value.path).toBe('/dashboard')
    expect(sessionStorage.getItem('roadlink_toast_type')).toBe('error')
  })

  it('allows authorized role into protected route', async () => {
    const store = useAuthStore()
    store.persistSession({ id: 6, username: 'fleet01', role: 'fleet_manager' })
    store.initialized = true

    await router.push('/reports')

    expect(router.currentRoute.value.path).toBe('/reports')
  })

  it('redirects to login when persisted session is stale', async () => {
    localStorage.setItem('roadlink_user', JSON.stringify({ id: 77, username: 'stale', role: 'admin' }))
    api.get.mockRejectedValueOnce({ response: { status: 401 } })

    const store = useAuthStore()
    store.initialized = false

    await router.push('/reports')

    expect(router.currentRoute.value.path).toBe('/login')
  })
})
