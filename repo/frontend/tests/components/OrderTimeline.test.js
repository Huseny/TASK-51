import { describe, expect, it } from 'vitest'
import { mount } from '@vue/test-utils'
import OrderTimeline from '@/components/rides/OrderTimeline.vue'

describe('OrderTimeline', () => {
  it('renders audit entries in chronological order', () => {
    const wrapper = mount(OrderTimeline, {
      props: {
        currentStatus: 'accepted',
        logs: [
          {
            id: 2,
            from_status: 'matching',
            to_status: 'accepted',
            trigger_reason: 'driver_accepted',
            triggered_by: '5',
            created_at: '2026-03-25T10:10:00Z',
          },
          {
            id: 1,
            from_status: 'created',
            to_status: 'matching',
            trigger_reason: 'order_submitted',
            triggered_by: 'system',
            created_at: '2026-03-25T10:00:00Z',
          },
        ],
      },
    })

    const rows = wrapper.findAll('.timeline__item')
    expect(rows).toHaveLength(2)
    expect(rows[0].text()).toContain('created')
    expect(rows[1].text()).toContain('accepted')
  })
})
