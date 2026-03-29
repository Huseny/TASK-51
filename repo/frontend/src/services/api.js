import axios from 'axios'
import { enqueuePendingAction, getPendingActions, removePendingAction } from '@/services/offlineQueue'

const API_URL = import.meta.env.VITE_API_URL || 'http://localhost:8000/api/v1'

const transport = axios.create({
  baseURL: API_URL,
  timeout: 15000,
})

let forcedOnlineState = null
let unauthorizedHandler = () => {
  localStorage.removeItem('roadlink_token')
  localStorage.removeItem('roadlink_user')
}

const isMutatingMethod = (method) => ['post', 'put', 'patch', 'delete'].includes(String(method).toLowerCase())

const makeIdempotencyKey = () => {
  if (typeof crypto !== 'undefined' && typeof crypto.randomUUID === 'function') {
    return crypto.randomUUID()
  }

  return `idem-${Date.now()}-${Math.random().toString(16).slice(2)}`
}

const isOnline = () => {
  if (typeof forcedOnlineState === 'boolean') {
    return forcedOnlineState
  }

  if (typeof navigator === 'undefined') {
    return true
  }

  return navigator.onLine
}

const buildHeaders = (method, existingHeaders = {}) => {
  const headers = { ...existingHeaders }
  const token = localStorage.getItem('roadlink_token')

  if (token) {
    headers.Authorization = `Bearer ${token}`
  }

  if (isMutatingMethod(method) && !headers['X-Idempotency-Key']) {
    headers['X-Idempotency-Key'] = makeIdempotencyKey()
  }

  return headers
}

const handleUnauthorizedIfNeeded = async (error) => {
  if (error?.response?.status === 401) {
    await unauthorizedHandler(error)
  }
}

const sendOnline = async (config) => {
  try {
    return await transport.request(config)
  } catch (error) {
    await handleUnauthorizedIfNeeded(error)
    throw error
  }
}

const queuedResponse = (config) => ({
  status: 202,
  statusText: 'Queued Offline',
  headers: {},
  config,
  data: {
    queued: true,
    payload: config.data ?? null,
  },
})

const queueMutation = async (config) => {
  await enqueuePendingAction({
    id: `${Date.now()}-${Math.random().toString(36).slice(2)}`,
    url: config.url,
    method: String(config.method).toUpperCase(),
    data: config.data ?? null,
    headers: config.headers ?? {},
    timestamp: Date.now(),
  })

  return queuedResponse(config)
}

const markSyncFailure = (action, reason) => {
  sessionStorage.setItem('roadlink_toast_message', `Error syncing offline action (${action.method} ${action.url}): ${reason}`)
  sessionStorage.setItem('roadlink_toast_type', 'error')
}

export const setUnauthorizedHandler = (handler) => {
  unauthorizedHandler = handler
}

export const __setOnlineStateForTests = (value) => {
  forcedOnlineState = value
}

export const syncPendingActions = async () => {
  if (!isOnline()) {
    return
  }

  const pendingActions = await getPendingActions()
  pendingActions.sort((a, b) => a.timestamp - b.timestamp)

  for (const action of pendingActions) {
    try {
      await sendOnline({
        url: action.url,
        method: action.method,
        data: action.data,
        headers: action.headers,
      })

      await removePendingAction(action.id)
    } catch (error) {
      if (!error.response) {
        break
      }

      markSyncFailure(action, error.response?.data?.message || `HTTP ${error.response.status}`)
      await removePendingAction(action.id)
    }
  }
}

const request = async (method, url, data, config = {}) => {
  const headers = buildHeaders(method, config.headers || {})
  const requestConfig = {
    ...config,
    url,
    method,
    headers,
  }

  if (data !== undefined) {
    requestConfig.data = data
  }

  if (isMutatingMethod(method) && !isOnline()) {
    return queueMutation(requestConfig)
  }

  return sendOnline(requestConfig)
}

const api = {
  get(url, config) {
    return request('get', url, undefined, config)
  },
  post(url, data, config) {
    return request('post', url, data, config)
  },
  put(url, data, config) {
    return request('put', url, data, config)
  },
  patch(url, data, config) {
    return request('patch', url, data, config)
  },
  delete(url, config) {
    return request('delete', url, undefined, config)
  },
}

if (typeof window !== 'undefined') {
  window.addEventListener('online', () => {
    syncPendingActions()
  })
}

export default api
