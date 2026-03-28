import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import { mount } from '@vue/test-utils'
import AutoCancelCountdown from '@/components/rides/AutoCancelCountdown.vue'

describe('AutoCancelCountdown', () => {
  beforeEach(() => {
    vi.useFakeTimers()
  })

  afterEach(() => {
    vi.useRealTimers()
  })

  it('displays and updates countdown every second', async () => {
    const wrapper = mount(AutoCancelCountdown, {
      props: {
        seconds: 125,
      },
    })

    expect(wrapper.text()).toContain('2:05')

    vi.advanceTimersByTime(1000)
    await wrapper.vm.$nextTick()

    expect(wrapper.text()).toContain('2:04')
  })
})
