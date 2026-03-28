<script setup>
import { computed, onBeforeUnmount, onMounted, ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import AppShell from '@/components/layout/AppShell.vue'
import AutoCancelCountdown from '@/components/rides/AutoCancelCountdown.vue'
import OrderTimeline from '@/components/rides/OrderTimeline.vue'
import Button from '@/components/ui/Button.vue'
import api from '@/services/api'
import { useAuthStore } from '@/stores/authStore'

const route = useRoute()
const router = useRouter()
const authStore = useAuthStore()

const order = ref(null)
const isLoading = ref(false)
const fetchError = ref('')
const cancelReason = ref('')
const isCancelling = ref(false)
const countdownSeconds = ref(null)
let countdownTimer = null

const user = computed(() => authStore.user || { username: 'Guest', role: 'rider' })
const reassigned = computed(() => (order.value?.audit_logs || []).some((entry) => entry.from_status === 'exception' && entry.to_status === 'matching'))

const startCountdownRefresh = () => {
  if (countdownTimer) {
    clearInterval(countdownTimer)
    countdownTimer = null
  }

  countdownTimer = setInterval(() => {
    if (countdownSeconds.value === null) {
      return
    }

    countdownSeconds.value = Math.max(0, countdownSeconds.value - 1)
  }, 1000)
}

const fetchOrder = async () => {
  isLoading.value = true
  fetchError.value = ''

  try {
    const response = await api.get(`/ride-orders/${route.params.id}`)
    order.value = response.data.order
    countdownSeconds.value = response.data.time_until_auto_cancel

    if (countdownSeconds.value !== null) {
      startCountdownRefresh()
    }
  } catch (error) {
    fetchError.value = error.response?.data?.message || 'Unable to load trip details.'
  } finally {
    isLoading.value = false
  }
}

const cancelTrip = async () => {
  if (!order.value || !window.confirm('Cancel this trip request?')) {
    return
  }

  isCancelling.value = true

  try {
    const response = await api.patch(`/ride-orders/${order.value.id}/transition`, {
      action: 'cancel',
      reason: cancelReason.value || undefined,
    })

    order.value = response.data.order
    countdownSeconds.value = response.data.time_until_auto_cancel
  } catch (error) {
    fetchError.value = error.response?.data?.message || 'Unable to cancel trip.'
  } finally {
    isCancelling.value = false
  }
}

const handleLogout = async () => {
  await authStore.logout()
  await router.push('/login')
}

onMounted(fetchOrder)

onBeforeUnmount(() => {
  if (countdownTimer) {
    clearInterval(countdownTimer)
  }
})
</script>

<template>
  <AppShell :user="user" @logout="handleLogout">
    <section v-if="order" class="detail-page">
      <header class="detail-header">
        <div>
          <h1>{{ order.origin_address }} → {{ order.destination_address }}</h1>
          <p class="helper-text">👤 x {{ order.rider_count }}</p>
        </div>
        <span class="status-pill">{{ order.status.replace('_', ' ') }}</span>
      </header>

      <AutoCancelCountdown v-if="order.status === 'matching'" :seconds="countdownSeconds" />

      <div v-if="reassigned" class="reassign-notice">
        ⚠️ Your trip is being reassigned to a new driver. You will be notified when a new driver accepts.
      </div>

      <section class="timeline-wrapper glass-card">
        <h2>Order Timeline</h2>
        <OrderTimeline :logs="order.audit_logs || []" :current-status="order.status" />
      </section>

      <section v-if="['matching', 'accepted'].includes(order.status)" class="cancel-box glass-card">
        <h3>Cancel Trip</h3>
        <input v-model="cancelReason" type="text" placeholder="Optional reason">
        <Button :loading="isCancelling" @click="cancelTrip">Cancel Trip</Button>
      </section>
    </section>

    <p v-else-if="isLoading" class="helper-text">Loading trip details...</p>
    <p v-else class="error-text">{{ fetchError }}</p>
  </AppShell>
</template>

<style scoped>
.detail-page {
  display: grid;
  gap: var(--space-4);
}

.detail-header {
  display: flex;
  justify-content: space-between;
  gap: var(--space-4);
}

h1,
h2,
h3 {
  margin: 0;
}

.status-pill {
  border-radius: 999px;
  padding: 6px 12px;
  height: fit-content;
  text-transform: capitalize;
  border: 1px solid var(--color-border);
  background: rgba(67, 97, 238, 0.18);
}

.timeline-wrapper,
.cancel-box {
  padding: var(--space-5);
}

.reassign-notice {
  border: 1px solid rgba(255, 167, 38, 0.52);
  background: rgba(255, 167, 38, 0.14);
  border-radius: var(--radius-md);
  color: #ffdfa5;
  padding: 10px 12px;
}

input {
  width: 100%;
  margin: var(--space-3) 0;
  border-radius: var(--radius-md);
  border: 1px solid var(--color-border);
  background: rgba(20, 26, 47, 0.45);
  color: var(--color-text);
  padding: 10px 12px;
}

.error-text {
  color: var(--color-error);
}

@media (max-width: 760px) {
  .detail-header {
    flex-direction: column;
  }
}
</style>
