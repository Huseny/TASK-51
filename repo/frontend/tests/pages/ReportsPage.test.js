import { beforeEach, describe, expect, it, vi } from 'vitest'
import { flushPromises, mount } from '@vue/test-utils'

const { getMock, postMock, pushMock } = vi.hoisted(() => ({
  getMock: vi.fn(),
  postMock: vi.fn(),
  pushMock: vi.fn(),
}))

vi.mock('vue-router', () => ({
  useRouter: () => ({ push: pushMock }),
}))

vi.mock('@/stores/authStore', () => ({
  useAuthStore: () => ({
    user: { id: 1, username: 'manager01', role: 'fleet_manager' },
    logout: vi.fn(),
  }),
}))

vi.mock('@/services/api', () => ({
  default: {
    get: getMock,
    post: postMock,
  },
}))

vi.mock('vue-chartjs', () => ({
  Line: { template: '<div />' },
  Doughnut: { template: '<div />' },
}))

import ReportsPage from '@/pages/reports/ReportsPage.vue'

describe('ReportsPage', () => {
  beforeEach(() => {
    vi.clearAllMocks()
    getMock.mockImplementation((url) => {
      if (url === '/reports/trends') return Promise.resolve({ data: { labels: [], datasets: [{ data: [] }] } })
      if (url === '/reports/distribution') return Promise.resolve({ data: { labels: [], datasets: [{ data: [] }] } })
      if (url === '/reports/regions') return Promise.resolve({ data: { data: [] } })
      if (url === '/reports/templates') return Promise.resolve({ data: { data: [] } })
      if (url === '/reports/export-directories') {
        return Promise.resolve({ data: { data: [{ id: 'default', label: 'Default exports' }, { id: 'ops', label: 'Operations exports' }] } })
      }

      return Promise.resolve({ data: {} })
    })

    postMock.mockResolvedValue({ data: { url: 'https://example.test/export' } })
    vi.stubGlobal('open', vi.fn())
  })

  it('uses approved export directory id in export payload', async () => {
    const wrapper = mount(ReportsPage, {
      global: {
        stubs: {
          AppShell: { template: '<div><slot /></div>' },
        },
      },
    })

    await flushPromises()

    const selects = wrapper.findAll('select')
    const directorySelect = selects[2]
    await directorySelect.setValue('ops')

    await wrapper.findAll('button.link')[0].trigger('click')
    await flushPromises()

    expect(postMock).toHaveBeenCalledWith('/reports/export', expect.objectContaining({
      directory_id: 'ops',
      type: 'trends',
    }))
  })
})
