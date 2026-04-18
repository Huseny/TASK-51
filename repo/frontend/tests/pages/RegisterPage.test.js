import { beforeEach, describe, expect, it, vi } from 'vitest'
import { flushPromises, mount } from '@vue/test-utils'

const { pushMock, registerMock, authState } = vi.hoisted(() => ({
  pushMock: vi.fn(),
  registerMock: vi.fn(),
  authState: {
    user: null,
    isAuthenticated: false,
    isLoading: false,
    error: '',
  },
}))

vi.mock('vue-router', () => ({
  useRouter: () => ({ push: pushMock }),
  RouterLink: { template: '<a><slot /></a>' },
}))

vi.mock('@/stores/authStore', () => ({
  useAuthStore: () => ({
    ...authState,
    register: registerMock,
  }),
}))

import RegisterPage from '@/pages/RegisterPage.vue'

const InputStub = {
  template: '<div><label>{{ label }}</label><input :type="type || \'text\'" :value="modelValue" @input="$emit(\'update:modelValue\', $event.target.value)" /></div>',
  props: ['modelValue', 'label', 'type', 'placeholder', 'icon'],
  emits: ['update:modelValue'],
}

const ButtonStub = {
  template: '<button :type="type || \'button\'" :disabled="loading"><slot /></button>',
  props: ['type', 'loading', 'disabled'],
}

const mountRegisterPage = () =>
  mount(RegisterPage, {
    global: {
      stubs: {
        Input: InputStub,
        Button: ButtonStub,
        RouterLink: { template: '<a><slot /></a>' },
        Transition: { template: '<div><slot /></div>' },
      },
    },
  })

describe('RegisterPage', () => {
  beforeEach(() => {
    vi.clearAllMocks()
    authState.error = ''
    authState.isLoading = false
  })

  it('renders username, password, and confirm password inputs', () => {
    const wrapper = mountRegisterPage()
    const inputs = wrapper.findAll('input')
    expect(inputs.length).toBeGreaterThanOrEqual(3)
  })

  it('renders the role selector with rider, driver, fleet_manager options', () => {
    const wrapper = mountRegisterPage()
    const select = wrapper.find('select')
    expect(select.exists()).toBe(true)

    const options = select.findAll('option')
    const values = options.map((o) => o.attributes('value'))
    expect(values).toContain('rider')
    expect(values).toContain('driver')
    expect(values).toContain('fleet_manager')
  })

  it('shows password strength indicators that start unmet', () => {
    const wrapper = mountRegisterPage()
    const checks = wrapper.findAll('li')

    const meChecks = checks.filter((c) => c.classes('met'))
    expect(meChecks.length).toBe(0)
  })

  it('password checks update when password is entered', async () => {
    const wrapper = mountRegisterPage()
    const inputs = wrapper.findAll('input')
    const passwordInput = inputs[1]

    await passwordInput.setValue('Password1234')

    const checks = wrapper.findAll('li')
    const metChecks = checks.filter((c) => c.classes('met'))
    expect(metChecks.length).toBe(3)
  })

  it('only length check passes for a short password with letter and number', async () => {
    const wrapper = mountRegisterPage()
    const inputs = wrapper.findAll('input')
    await inputs[1].setValue('Ab1')

    const checks = wrapper.findAll('li')
    const metChecks = checks.filter((c) => c.classes('met'))
    expect(metChecks.length).toBe(2)

    const metTexts = metChecks.map((c) => c.text())
    expect(metTexts.some((t) => t.includes('letter'))).toBe(true)
    expect(metTexts.some((t) => t.includes('number'))).toBe(true)
  })

  it('calls authStore.register with form data on submit', async () => {
    registerMock.mockResolvedValueOnce({ success: true })
    pushMock.mockResolvedValueOnce(undefined)

    const wrapper = mountRegisterPage()
    const inputs = wrapper.findAll('input')

    await inputs[0].setValue('newrider')
    await inputs[1].setValue('Password1234')
    await inputs[2].setValue('Password1234')

    const select = wrapper.find('select')
    await select.setValue('driver')

    await wrapper.find('form').trigger('submit')
    await flushPromises()

    expect(registerMock).toHaveBeenCalledWith(
      expect.objectContaining({
        username: 'newrider',
        password: 'Password1234',
        password_confirmation: 'Password1234',
        role: 'driver',
      }),
    )
  })

  it('redirects to /dashboard on successful registration', async () => {
    registerMock.mockResolvedValueOnce({ success: true })
    pushMock.mockResolvedValueOnce(undefined)

    const wrapper = mountRegisterPage()
    const inputs = wrapper.findAll('input')
    await inputs[0].setValue('newrider')
    await inputs[1].setValue('Password1234')
    await inputs[2].setValue('Password1234')

    await wrapper.find('form').trigger('submit')
    await flushPromises()

    expect(pushMock).toHaveBeenCalledWith('/dashboard')
  })

  it('displays error message when registration fails', async () => {
    registerMock.mockImplementationOnce(async () => {
      authState.error = 'Username already taken'
      return { success: false }
    })

    const wrapper = mountRegisterPage()
    const inputs = wrapper.findAll('input')
    await inputs[0].setValue('existinguser')
    await inputs[1].setValue('Password1234')
    await inputs[2].setValue('Password1234')

    await wrapper.find('form').trigger('submit')
    await flushPromises()

    expect(wrapper.text()).toContain('Username already taken')
  })

  it('does not redirect on failed registration', async () => {
    registerMock.mockResolvedValueOnce({ success: false })

    const wrapper = mountRegisterPage()
    await wrapper.find('form').trigger('submit')
    await flushPromises()

    expect(pushMock).not.toHaveBeenCalled()
  })

  it('has a link to the login page', () => {
    const wrapper = mountRegisterPage()
    expect(wrapper.text()).toContain('Sign in')
  })

  it('role defaults to rider', () => {
    const wrapper = mountRegisterPage()
    const select = wrapper.find('select')
    expect(select.element.value).toBe('rider')
  })
})
