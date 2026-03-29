import { defineStore } from 'pinia'
import api, { clearOfflineQueue, ensureCsrfCookie } from '@/services/api'

const USER_KEY = 'roadlink_user'

export const useAuthStore = defineStore('auth', {
  state: () => ({
    user: null,
    isAuthenticated: false,
    isLoading: false,
    error: '',
    initialized: false,
  }),

  actions: {
    persistSession(user) {
      const previousUserId = this.user?.id

      this.user = user
      this.isAuthenticated = true
      this.initialized = true
      localStorage.setItem(USER_KEY, JSON.stringify(user))

      if (previousUserId && previousUserId !== user.id) {
        clearOfflineQueue()
      }
    },

    clearSession() {
      this.user = null
      this.isAuthenticated = false
      this.error = ''
      localStorage.removeItem(USER_KEY)
      localStorage.removeItem('roadlink_chat_unread_total')
      sessionStorage.removeItem('roadlink_toast_message')
      sessionStorage.removeItem('roadlink_toast_type')
      clearOfflineQueue()
    },

    forceLogout() {
      this.clearSession()
      this.initialized = true
    },

    async login(username, password) {
      this.isLoading = true
      this.error = ''

      try {
        try {
          await ensureCsrfCookie()
        } catch {
        }
        const response = await api.post('/auth/login', { username, password })
        this.persistSession(response.data.user)
        return { success: true }
      } catch (error) {
        const payload = error.response?.data || {}
        this.error = payload.message || 'Login failed'

        return {
          success: false,
          error: payload.error,
          lockedUntil: payload.locked_until,
        }
      } finally {
        this.isLoading = false
      }
    },

    async register(data) {
      this.isLoading = true
      this.error = ''

      try {
        try {
          await ensureCsrfCookie()
        } catch {
        }
        const response = await api.post('/auth/register', data)
        this.persistSession(response.data.user)
        return { success: true }
      } catch (error) {
        const payload = error.response?.data || {}
        this.error = payload.message || 'Registration failed'
        return { success: false }
      } finally {
        this.isLoading = false
      }
    },

    async logout() {
      this.isLoading = true

      try {
        await api.post('/auth/logout')
      } catch {
      } finally {
        this.clearSession()
        this.isLoading = false
      }
    },

    async fetchMe() {
      try {
        const response = await api.get('/auth/me')
        this.user = response.data.user
        this.isAuthenticated = true
        localStorage.setItem(USER_KEY, JSON.stringify(response.data.user))
        return true
      } catch {
        this.clearSession()
        return false
      }
    },

    async initialize() {
      if (this.initialized) {
        return
      }

      const cachedUser = localStorage.getItem(USER_KEY)
      if (cachedUser) {
        this.user = JSON.parse(cachedUser)
      }

      const loaded = await this.fetchMe()
      if (!loaded) {
        this.clearSession()
      }

      this.initialized = true
    },
  },
})
