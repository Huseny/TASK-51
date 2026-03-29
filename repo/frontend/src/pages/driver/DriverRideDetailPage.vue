<script setup>
import { computed, onBeforeUnmount, onMounted, ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import AppShell from '@/components/layout/AppShell.vue'
import DriverRideActions from '@/components/driver/DriverRideActions.vue'
import OrderTimeline from '@/components/rides/OrderTimeline.vue'
import api from '@/services/api'
import { useAuthStore } from '@/stores/authStore'

const route = useRoute()
const router = useRouter()
const authStore = useAuthStore()

const user = computed(() => authStore.user || { username: 'Guest', role: 'driver' })
const ride = ref(null)
const isLoading = ref(false)
const actionLoading = ref(false)
const errorMessage = ref('')
const unreadCount = ref(0)
let pollTimer = null
let unreadPollTimer = null

const fetchRide = async () => {
  isLoading.value = true

  try {
    const response = await api.get(`/driver/my-rides/${route.params.id}`)
    ride.value = response.data.order
    errorMessage.value = ''
  } catch (error) {
    errorMessage.value = error.response?.data?.message || 'Unable to load ride.'
  } finally {
    isLoading.value = false
  }
}

const fetchUnreadCount = async () => {
  try {
    const response = await api.get(`/ride-orders/${route.params.id}/chat`)
    unreadCount.value = response.data.unread_count || 0
    localStorage.setItem('roadlink_chat_unread_total', String(unreadCount.value))
  } catch {
    unreadCount.value = 0
    localStorage.setItem('roadlink_chat_unread_total', '0')
  }
}

const applyAction = async (action, reason = undefined) => {
  if (!ride.value) {
    return
  }

  actionLoading.value = true

  try {
    const response = await api.patch(`/ride-orders/${ride.value.id}/transition`, {
      action,
      reason,
    })

    if (response.data?.queued) {
      const optimisticStatusByAction = {
        start: 'in_progress',
        complete: 'completed',
        flag_exception: 'exception',
      }

      const nextStatus = optimisticStatusByAction[action]
      if (nextStatus) {
        ride.value = {
          ...ride.value,
          status: nextStatus,
        }
      }
    } else {
      await fetchRide()
    }
  } finally {
    actionLoading.value = false
  }
}

const handleLogout = async () => {
  await authStore.logout()
  await router.push('/login')
}

onMounted(async () => {
  await fetchRide()
  await fetchUnreadCount()
  pollTimer = setInterval(fetchRide, 15000)
  unreadPollTimer = setInterval(fetchUnreadCount, 30000)
})

onBeforeUnmount(() => {
  if (pollTimer) {
    clearInterval(pollTimer)
  }

  if (unreadPollTimer) {
    clearInterval(unreadPollTimer)
  }
})
</script>

<template>
  <AppShell :user="user" @logout="handleLogout">
    <p v-if="isLoading" class="helper-text">Loading ride...</p>
    <p v-else-if="errorMessage" class="error-text">{{ errorMessage }}</p>

    <section v-else-if="ride" class="detail-page">
      <header class="detail-header glass-card">
        <h1>{{ ride.origin_address }} → {{ ride.destination_address }}</h1>
        <p class="helper-text">👤 x {{ ride.rider_count }} · {{ new Date(ride.time_window_start).toLocaleString() }} - {{ new Date(ride.time_window_end).toLocaleTimeString() }}</p>
        <p class="notes" v-if="ride.notes">Rider notes: {{ ride.notes }}</p>
        <span class="status-pill">{{ ride.status.replace('_', ' ') }}</span>
        <button class="chat-link" type="button" @click="router.push(`/driver/my-rides/${ride.id}/chat`)">
          Chat <span v-if="unreadCount > 0" class="badge">{{ unreadCount }}</span>
        </button>
      </header>

      <section class="glass-card action-panel">
        <h2>Actions</h2>
        <DriverRideActions
          :ride="ride"
          :loading="actionLoading"
          @start="applyAction('start')"
          @complete="applyAction('complete')"
          @flag-exception="(reason) => applyAction('flag_exception', reason)"
        />
      </section>

      <section class="glass-card timeline-panel">
        <h2>Audit Timeline</h2>
        <OrderTimeline :logs="ride.audit_logs || []" :current-status="ride.status" />
      </section>
    </section>
  </AppShell>
</template>

<style scoped>
.detail-page {
  display: grid;
  gap: var(--space-4);
}

.detail-header,
.action-panel,
.timeline-panel {
  padding: var(--space-5);
}

h1,
h2 {
  margin-top: 0;
}

.status-pill {
  display: inline-flex;
  border-radius: 999px;
  border: 1px solid var(--color-border);
  background: rgba(67, 97, 238, 0.22);
  padding: 6px 12px;
  text-transform: capitalize;
}

.chat-link {
  margin-top: var(--space-2);
  width: fit-content;
  border: 1px solid var(--color-border);
  border-radius: 999px;
  background: rgba(67, 97, 238, 0.16);
  color: var(--color-text);
  padding: 6px 12px;
  cursor: pointer;
}

.badge {
  display: inline-grid;
  place-items: center;
  margin-left: 6px;
  min-width: 20px;
  height: 20px;
  padding: 0 6px;
  border-radius: 999px;
  background: rgba(239, 71, 111, 0.82);
  color: #fff;
  font-size: 0.75rem;
}

.notes {
  margin: var(--space-2) 0 0;
}

.error-text {
  color: var(--color-error);
}
</style>
