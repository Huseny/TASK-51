import { beforeEach, describe, expect, it, vi } from 'vitest'
import { flushPromises, mount } from '@vue/test-utils'

const { pushMock, getMock, authState } = vi.hoisted(() => ({
  pushMock: vi.fn(),
  getMock: vi.fn(),
  authState: {
    user: { id: 41, username: 'rider01', role: 'rider' },
    logout: vi.fn(),
  },
}))

vi.mock('vue-router', () => ({
  useRouter: () => ({
    push: pushMock,
  }),
}))

vi.mock('@/services/api', () => ({
  default: {
    get: getMock,
  },
}))

vi.mock('@/stores/authStore', () => ({
  useAuthStore: () => authState,
}))

import DashboardPage from '@/pages/DashboardPage.vue'

describe('DashboardPage', () => {
  beforeEach(() => {
    vi.clearAllMocks()
    authState.user = { id: 41, username: 'rider01', role: 'rider' }
  })

  it('renders API-driven dashboard summary cards', async () => {
    getMock.mockImplementation((url) => {
      if (url === '/ride-orders') {
        return Promise.resolve({ data: { total: 7 } })
      }

      if (url === '/products') {
        return Promise.resolve({ data: { data: [{ id: 1 }, { id: 2 }, { id: 3 }] } })
      }

      if (url === '/notifications/unread-count') {
        return Promise.resolve({ data: { unread_count: 5 } })
      }

      if (url === '/recommendations') {
        return Promise.resolve({ data: { data: [] } })
      }

      return Promise.reject(new Error(`Unexpected URL ${url}`))
    })

    const wrapper = mount(DashboardPage, {
      global: {
        stubs: {
          AppShell: { template: '<div><slot /></div>' },
          Card: { template: '<section><slot /></section>' },
          Badge: { template: '<span><slot /></span>' },
        },
      },
    })

    await flushPromises()

    expect(wrapper.text()).toContain('Trips')
    expect(wrapper.text()).toContain('7')
    expect(wrapper.text()).toContain('Inventory')
    expect(wrapper.text()).toContain('3')
    expect(wrapper.text()).toContain('Notifications')
    expect(wrapper.text()).toContain('5')
  })

  it('shows dashboard summary error when summary API fails', async () => {
    getMock.mockImplementation((url) => {
      if (url === '/ride-orders') {
        return Promise.reject({ response: { data: { message: 'Summary offline' } } })
      }

      if (url === '/recommendations') {
        return Promise.resolve({ data: { data: [] } })
      }

      return Promise.resolve({ data: {} })
    })

    const wrapper = mount(DashboardPage, {
      global: {
        stubs: {
          AppShell: { template: '<div><slot /></div>' },
          Card: { template: '<section><slot /></section>' },
          Badge: { template: '<span><slot /></span>' },
        },
      },
    })

    await flushPromises()

    expect(wrapper.text()).toContain('Summary offline')
  })

  it('uses fleet ride endpoints for fleet-manager trip summary', async () => {
    authState.user = { id: 15, username: 'fleet01', role: 'fleet_manager' }

    getMock.mockImplementation((url) => {
      if (url === '/fleet/rides/queue') {
        return Promise.resolve({ data: { total: 4 } })
      }

      if (url === '/fleet/rides/active') {
        return Promise.resolve({ data: { total: 3 } })
      }

      if (url === '/products') {
        return Promise.resolve({ data: { data: [] } })
      }

      if (url === '/notifications/unread-count') {
        return Promise.resolve({ data: { unread_count: 2 } })
      }

      if (url === '/recommendations') {
        return Promise.resolve({ data: { data: [] } })
      }

      return Promise.reject(new Error(`Unexpected URL ${url}`))
    })

    const wrapper = mount(DashboardPage, {
      global: {
        stubs: {
          AppShell: { template: '<div><slot /></div>' },
          Card: { template: '<section><slot /></section>' },
          Badge: { template: '<span><slot /></span>' },
        },
      },
    })

    await flushPromises()

    expect(wrapper.text()).toContain('7')
  })
})
