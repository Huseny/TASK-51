import { describe, expect, it } from 'vitest'
import { mount } from '@vue/test-utils'
import TripCard from '@/components/rides/TripCard.vue'

describe('TripCard', () => {
  it('renders matching status badge tone class', () => {
    const wrapper = mount(TripCard, {
      props: {
        order: {
          id: 1,
          origin_address: 'A',
          destination_address: 'B',
          rider_count: 2,
          time_window_start: '2026-03-25T10:00:00Z',
          time_window_end: '2026-03-25T11:00:00Z',
          notes: 'note',
          status: 'matching',
        },
      },
    })

    expect(wrapper.find('.trip-card__status').classes()).toContain('trip-card__status--matching')
  })
})
