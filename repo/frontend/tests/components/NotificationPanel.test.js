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

vi.mock('@/services/api', () => ({
  default: {
    get: getMock,
    patch: patchMock,
  },
}))

import NotificationPanel from '@/components/notifications/NotificationPanel.vue'

describe('NotificationPanel', () => {
  beforeEach(() => {
    vi.clearAllMocks()
  })

  it('renders all scenario labels and deep-links on click', async () => {
    getMock.mockImplementation((url) => {
      if (url === '/notifications/unread-count') {
        return Promise.resolve({ data: { unread_count: 6 } })
      }

      return Promise.resolve({
        data: {
          data: [
            { id: 1, type: 'order_update', title: 'New comment', body: 'comment', is_read: false, count: 1, data: { scenario: 'comment', url: '/rider/trips' } },
            { id: 2, type: 'reply', title: 'New reply', body: 'reply', is_read: false, count: 3, data: { scenario: 'reply', url: '/rider/trips' } },
            { id: 3, type: 'mention', title: 'Mention', body: 'mention', is_read: false, count: 1, data: { scenario: 'mention', url: '/rider/trips' } },
            { id: 4, type: 'follower', title: 'Follower', body: 'follower', is_read: false, count: 1, data: { scenario: 'follower', url: '/dashboard' } },
            { id: 5, type: 'moderation', title: 'Moderation', body: 'moderation', is_read: false, count: 1, data: { scenario: 'moderation', url: '/settings/notifications' } },
            { id: 6, type: 'system', title: 'Announcement', body: 'announcement', is_read: false, count: 1, data: { scenario: 'announcement', url: '/dashboard' } },
          ],
        },
      })
    })

    patchMock.mockResolvedValue({ data: { message: 'ok' } })

    const wrapper = mount(NotificationPanel, {
      props: { open: false },
    })

    await wrapper.setProps({ open: true })

    await flushPromises()

    expect(wrapper.text()).toContain('comment')
    expect(wrapper.text()).toContain('reply')
    expect(wrapper.text()).toContain('mention')
    expect(wrapper.text()).toContain('follower')
    expect(wrapper.text()).toContain('moderation')
    expect(wrapper.text()).toContain('announcement')

    await wrapper.findAll('.item')[4].trigger('click')
    await flushPromises()

    expect(patchMock).toHaveBeenCalledWith('/notifications/5/read')
    expect(pushMock).toHaveBeenCalledWith('/settings/notifications')
  })
})
