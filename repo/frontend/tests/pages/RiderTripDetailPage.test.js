import { beforeEach, describe, expect, it, vi } from 'vitest'
import { flushPromises, mount } from '@vue/test-utils'

const { getMock, patchMock, pushMock, logoutMock } = vi.hoisted(() => ({
  getMock: vi.fn(),
  patchMock: vi.fn(),
  pushMock: vi.fn(),
  logoutMock: vi.fn(),
}))

vi.mock('vue-router', () => ({
  useRoute: () => ({
    params: { id: '44' },
  }),
  useRouter: () => ({
    push: pushMock,
  }),
}))

vi.mock('@/stores/authStore', () => ({
  useAuthStore: () => ({
    user: { id: 5, username: 'rider01', role: 'rider' },
    logout: logoutMock,
  }),
}))

vi.mock('@/services/api', () => ({
  default: {
    get: getMock,
    patch: patchMock,
  },
}))

import RiderTripDetailPage from '@/pages/rider/RiderTripDetailPage.vue'

describe('RiderTripDetailPage', () => {
  beforeEach(() => {
    vi.clearAllMocks()

    getMock.mockImplementation((url) => {
      if (url === '/ride-orders/44/chat') {
        return Promise.resolve({ data: { unread_count: 0 } })
      }

      return Promise.resolve({
        data: {
          time_until_auto_cancel: 120,
          order: {
            id: 44,
            origin_address: 'A',
            destination_address: 'B',
            rider_count: 1,
            status: 'matching',
            driver: null,
            audit_logs: [],
          },
        },
      })
    })

    patchMock.mockResolvedValue({ data: {} })
  })

  it('shows a reassignment notice for accepted to matching no-show reassignments', async () => {
    getMock.mockImplementation((url) => {
      if (url === '/ride-orders/44/chat') {
        return Promise.resolve({ data: { unread_count: 0 } })
      }

      return Promise.resolve({
        data: {
          time_until_auto_cancel: 120,
          order: {
            id: 44,
            origin_address: 'A',
            destination_address: 'B',
            rider_count: 1,
            status: 'matching',
            driver: null,
            audit_logs: [
              {
                id: 9,
                from_status: 'accepted',
                to_status: 'matching',
                trigger_reason: 'no_show_auto_revert',
                triggered_by: 'system',
                metadata: {
                  previous_driver_id: 12,
                  new_driver_id: null,
                  driver_reassigned: true,
                  reassignment_reason: 'no_show_auto_revert',
                },
                created_at: '2026-03-25T10:20:00Z',
              },
            ],
          },
        },
      })
    })

    const wrapper = mount(RiderTripDetailPage, {
      global: {
        stubs: {
          AppShell: { template: '<div><slot /></div>' },
          AutoCancelCountdown: { template: '<div class="countdown" />' },
          OrderTimeline: { template: '<div class="timeline" />' },
          Button: { template: '<button><slot /></button>' },
        },
      },
    })

    await flushPromises()

    expect(wrapper.text()).toContain('Driver reassigned')
    expect(wrapper.text()).toContain('Driver no-show')
    expect(wrapper.text()).toContain('previous driver is no longer assigned')
  })

  it('shows a reassignment notice when audit metadata reports a driver change', async () => {
    getMock.mockImplementation((url) => {
      if (url === '/ride-orders/44/chat') {
        return Promise.resolve({ data: { unread_count: 0 } })
      }

      return Promise.resolve({
        data: {
          time_until_auto_cancel: null,
          order: {
            id: 44,
            origin_address: 'A',
            destination_address: 'B',
            rider_count: 1,
            status: 'accepted',
            driver: { id: 15, username: 'driver-new' },
            audit_logs: [
              {
                id: 10,
                from_status: 'accepted',
                to_status: 'accepted',
                trigger_reason: 'manual_reassignment',
                triggered_by: 'admin',
                metadata: {
                  previous_driver_id: 12,
                  new_driver_id: 15,
                },
                created_at: '2026-03-25T10:30:00Z',
              },
            ],
          },
        },
      })
    })

    const wrapper = mount(RiderTripDetailPage, {
      global: {
        stubs: {
          AppShell: { template: '<div><slot /></div>' },
          AutoCancelCountdown: { template: '<div class="countdown" />' },
          OrderTimeline: { template: '<div class="timeline" />' },
          Button: { template: '<button><slot /></button>' },
        },
      },
    })

    await flushPromises()

    expect(wrapper.text()).toContain('Driver reassigned')
    expect(wrapper.text()).toContain('Manual reassignment')
    expect(wrapper.text()).toContain('Driver assignment has changed for this trip')
  })
})
