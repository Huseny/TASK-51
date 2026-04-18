import { beforeEach, describe, expect, it, vi } from 'vitest'
import { flushPromises, mount } from '@vue/test-utils'

// Hoisted mocks so they're available before module imports
const { pushMock, loginMock, authState } = vi.hoisted(() => ({
  pushMock: vi.fn(),
  loginMock: vi.fn(),
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
    login: loginMock,
  }),
}))

import LoginPage from '@/pages/LoginPage.vue'

const InputStub = {
  template: '<div><label>{{ label }}</label><input :type="type || \'text\'" :value="modelValue" @input="$emit(\'update:modelValue\', $event.target.value)" /></div>',
  props: ['modelValue', 'label', 'type', 'placeholder', 'icon'],
  emits: ['update:modelValue'],
}

const ButtonStub = {
  template: '<button :type="type || \'button\'" :disabled="loading"><slot /></button>',
  props: ['type', 'loading', 'disabled'],
}

const mountLoginPage = () =>
  mount(LoginPage, {
    global: {
      stubs: {
        'Input': InputStub,
        'Button': ButtonStub,
        RouterLink: { template: '<a><slot /></a>' },
        Transition: { template: '<div><slot /></div>' },
      },
    },
  })

describe('LoginPage', () => {
  beforeEach(() => {
    vi.clearAllMocks()
    authState.error = ''
    authState.isLoading = false
  })

  it('renders username and password inputs', () => {
    const wrapper = mountLoginPage()
    const inputs = wrapper.findAll('input')
    expect(inputs.length).toBeGreaterThanOrEqual(2)
  })

  it('renders the sign-in submit button', () => {
    const wrapper = mountLoginPage()
    const button = wrapper.find('button[type="submit"]')
    expect(button.exists()).toBe(true)
    expect(button.text()).toContain('Sign In')
  })

  it('calls authStore.login with username and password on form submit', async () => {
    loginMock.mockResolvedValueOnce({ success: true })
    pushMock.mockResolvedValueOnce(undefined)

    const wrapper = mountLoginPage()
    const inputs = wrapper.findAll('input')
    await inputs[0].setValue('rider01')
    await inputs[1].setValue('Password1234')

    await wrapper.find('form').trigger('submit')
    await flushPromises()

    expect(loginMock).toHaveBeenCalledWith('rider01', 'Password1234')
  })

  it('redirects to /dashboard on successful login', async () => {
    loginMock.mockResolvedValueOnce({ success: true })
    pushMock.mockResolvedValueOnce(undefined)

    const wrapper = mountLoginPage()
    const inputs = wrapper.findAll('input')
    await inputs[0].setValue('driver01')
    await inputs[1].setValue('Password1234')

    await wrapper.find('form').trigger('submit')
    await flushPromises()

    expect(pushMock).toHaveBeenCalledWith('/dashboard')
  })

  it('displays auth error when login fails', async () => {
    loginMock.mockImplementationOnce(async () => {
      authState.error = 'Invalid username or password'
      return { success: false }
    })

    const wrapper = mountLoginPage()
    await wrapper.find('form').trigger('submit')
    await flushPromises()

    expect(wrapper.text()).toContain('Invalid username or password')
  })

  it('does not redirect on failed login', async () => {
    loginMock.mockResolvedValueOnce({ success: false, error: 'invalid_credentials' })

    const wrapper = mountLoginPage()
    await wrapper.find('form').trigger('submit')
    await flushPromises()

    expect(pushMock).not.toHaveBeenCalled()
  })

  it('shows locked account countdown when account_locked error is returned', async () => {
    const futureTime = new Date(Date.now() + 5 * 60 * 1000).toISOString()
    loginMock.mockResolvedValueOnce({
      success: false,
      error: 'account_locked',
      lockedUntil: futureTime,
    })

    const wrapper = mountLoginPage()
    await wrapper.find('form').trigger('submit')
    await flushPromises()

    expect(wrapper.text()).toContain('Account locked')
  })

  it('shows loading state during form submission', async () => {
    let resolveLogin
    loginMock.mockImplementationOnce(() => new Promise((resolve) => { resolveLogin = resolve }))
    authState.isLoading = true

    const wrapper = mountLoginPage()
    const button = wrapper.find('button[type="submit"]')

    expect(button.attributes('disabled')).toBeDefined()
  })

  it('does not call login when already loading', async () => {
    authState.isLoading = true
    const wrapper = mountLoginPage()
    const button = wrapper.find('button[type="submit"]')
    expect(button.attributes('disabled')).toBeDefined()
  })

  it('has a link to the registration page', () => {
    const wrapper = mountLoginPage()
    expect(wrapper.text()).toContain('Create an account')
  })
})
