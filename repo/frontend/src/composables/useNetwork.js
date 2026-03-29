import { readonly, ref } from 'vue'
import { syncPendingActions } from '@/services/api'

const isOnline = ref(typeof navigator === 'undefined' ? true : navigator.onLine)
let initialized = false

const updateOnlineState = async () => {
  isOnline.value = typeof navigator === 'undefined' ? true : navigator.onLine

  if (isOnline.value) {
    await syncPendingActions()
  }
}

export const useNetwork = () => {
  if (!initialized && typeof window !== 'undefined') {
    window.addEventListener('online', updateOnlineState)
    window.addEventListener('offline', updateOnlineState)
    initialized = true
  }

  return {
    isOnline: readonly(isOnline),
  }
}
