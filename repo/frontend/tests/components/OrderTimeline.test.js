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

  it('labels reassignment events with a clear driver reassigned message', () => {
    const wrapper = mount(OrderTimeline, {
      props: {
        currentStatus: 'matching',
        logs: [
          {
            id: 3,
            from_status: 'accepted',
            to_status: 'matching',
            trigger_reason: 'no_show_auto_revert',
            triggered_by: 'system',
            metadata: {
              previous_driver_id: 4,
              new_driver_id: null,
              driver_reassigned: true,
              reassignment_reason: 'no_show_auto_revert',
            },
            created_at: '2026-03-25T10:20:00Z',
          },
        ],
      },
    })

    expect(wrapper.text()).toContain('Driver reassigned')
    expect(wrapper.text()).toContain('Driver no-show')
  })
})
