import { beforeEach, describe, expect, it, vi } from 'vitest'
import { flushPromises, mount } from '@vue/test-utils'

const { getMock, patchMock, pushMock } = vi.hoisted(() => ({
  getMock: vi.fn(),
  patchMock: vi.fn(),
  pushMock: vi.fn(),
}))

vi.mock('vue-router', () => ({
  useRouter: () => ({ push: pushMock }),
}))

vi.mock('@/stores/authStore', () => ({
  useAuthStore: () => ({
    user: { id: 3, username: 'fleet01', role: 'fleet_manager' },
    logout: vi.fn(),
  }),
}))

vi.mock('@/services/api', () => ({
  default: {
    get: getMock,
    patch: patchMock,
  },
}))

import FleetRideManagementPage from '@/pages/fleet/FleetRideManagementPage.vue'

describe('FleetRideManagementPage', () => {
  beforeEach(() => {
    vi.clearAllMocks()
    getMock.mockImplementation((url) => {
      if (url === '/fleet/rides/queue') {
        return Promise.resolve({ data: { data: [{ id: 10, status: 'matching', origin_address: 'A', destination_address: 'B', rider: { username: 'rider01' }, time_window_start: '2026-03-25T10:00:00Z' }] } })
      }
      if (url === '/fleet/rides/active') {
        return Promise.resolve({ data: { data: [{ id: 11, status: 'accepted', origin_address: 'C', destination_address: 'D', rider: { username: 'rider02' }, driver: { username: 'driver01' } }] } })
      }
      if (url === '/fleet/drivers') {
        return Promise.resolve({ data: { data: [{ id: 7, username: 'driver01' }] } })
      }

      return Promise.resolve({ data: {} })
    })
    patchMock.mockResolvedValue({ data: { order: { id: 10 } } })
  })

  it('renders queue and active rides for fleet operators', async () => {
    const wrapper = mount(FleetRideManagementPage, {
      global: {
        stubs: {
          AppShell: { template: '<div><slot /></div>' },
        },
      },
    })

    await flushPromises()

    expect(wrapper.text()).toContain('Fleet Ride Management')
    expect(wrapper.text()).toContain('Dispatch Queue')
    expect(wrapper.text()).toContain('Active Operations')
    expect(wrapper.text()).toContain('matching')
    expect(wrapper.text()).toContain('accepted')
  })

  it('assigns a driver from the queue', async () => {
    const wrapper = mount(FleetRideManagementPage, {
      global: {
        stubs: {
          AppShell: { template: '<div><slot /></div>' },
        },
      },
    })

    await flushPromises()

    await wrapper.get('[data-testid="queue-driver-select-10"]').setValue('7')
    await wrapper.get('[data-testid="queue-assign-10"]').trigger('click')

    expect(patchMock).toHaveBeenCalledWith('/fleet/rides/10/assign', expect.objectContaining({
      driver_id: 7,
    }))
  })
})
