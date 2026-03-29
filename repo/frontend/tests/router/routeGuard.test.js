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
import { useAuthStore } from '@/stores/authStore'

describe('route guards', () => {
  beforeEach(async () => {
    setActivePinia(createPinia())
    localStorage.clear()
    sessionStorage.clear()
    vi.clearAllMocks()
    await router.push('/login')
  })

  it('redirects user without allowed role from protected route', async () => {
    const store = useAuthStore()
    store.persistSession({ id: 1, username: 'driver01', role: 'driver' }, 'token-driver')
    store.initialized = true

    await router.push('/reports')

    expect(router.currentRoute.value.path).toBe('/dashboard')
    expect(sessionStorage.getItem('roadlink_toast_type')).toBe('error')
  })

  it('allows authorized role into protected route', async () => {
    const store = useAuthStore()
    store.persistSession({ id: 6, username: 'fleet01', role: 'fleet_manager' }, 'token-fleet')
    store.initialized = true

    await router.push('/reports')

    expect(router.currentRoute.value.path).toBe('/reports')
  })
})
