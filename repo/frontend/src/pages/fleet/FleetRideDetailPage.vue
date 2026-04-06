<script setup>
import { computed, onMounted, ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import AppShell from '@/components/layout/AppShell.vue'
import OrderTimeline from '@/components/rides/OrderTimeline.vue'
import api from '@/services/api'
import { useAuthStore } from '@/stores/authStore'

const route = useRoute()
const router = useRouter()
const authStore = useAuthStore()

const user = computed(() => authStore.user || { username: 'Guest', role: 'fleet_manager' })
const ride = ref(null)
const drivers = ref([])
const selectedDriverId = ref('')
const error = ref('')

const loadRide = async () => {
  try {
    const [rideResponse, driversResponse] = await Promise.all([
      api.get(`/fleet/rides/${route.params.id}`),
      api.get('/fleet/drivers'),
    ])
    ride.value = rideResponse.data.order
    drivers.value = driversResponse.data.data || []
  } catch (err) {
    error.value = err.response?.data?.message || 'Unable to load fleet ride detail.'
  }
}

const assignOrReassign = async () => {
  if (!selectedDriverId.value) {
    error.value = 'Select a driver first.'
    return
  }

  const endpoint = ride.value?.status === 'matching'
    ? `/fleet/rides/${ride.value.id}/assign`
    : `/fleet/rides/${ride.value.id}/reassign`

  await api.patch(endpoint, {
    driver_id: Number(selectedDriverId.value),
    reason: ride.value?.status === 'matching' ? 'fleet_dispatch_assignment' : 'manual_reassignment',
  })

  await loadRide()
}

const handleLogout = async () => {
  await authStore.logout()
  await router.push('/login')
}

onMounted(loadRide)
</script>

<template>
  <AppShell :user="user" @logout="handleLogout">
    <p v-if="error" class="error-text">{{ error }}</p>

    <section v-if="ride" class="detail-page">
      <section class="glass-card panel">
        <h1>{{ ride.origin_address }} → {{ ride.destination_address }}</h1>
        <p class="helper-text">Rider: {{ ride.rider?.username || 'Unknown' }}</p>
        <p class="helper-text">Driver: {{ ride.driver?.username || 'Unassigned' }}</p>
        <p class="helper-text">Window: {{ new Date(ride.time_window_start).toLocaleString() }}</p>
        <span class="status">{{ ride.status.replace('_', ' ') }}</span>

        <div class="assign-row">
          <select v-model="selectedDriverId">
            <option value="">Select driver</option>
            <option v-for="driver in drivers" :key="driver.id" :value="driver.id">{{ driver.username }}</option>
          </select>
          <button type="button" @click="assignOrReassign">
            {{ ride.status === 'matching' ? 'Assign Driver' : 'Reassign Driver' }}
          </button>
        </div>
      </section>

      <section class="glass-card panel">
        <h2>Timeline</h2>
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

.panel {
  padding: var(--space-5);
  display: grid;
  gap: var(--space-3);
}

.assign-row {
  display: flex;
  gap: var(--space-2);
  flex-wrap: wrap;
}

.status {
  border: 1px solid var(--color-border);
  border-radius: 999px;
  padding: 4px 10px;
  width: fit-content;
  text-transform: capitalize;
}

select,
button {
  border-radius: var(--radius-sm);
  border: 1px solid var(--color-border);
  background: rgba(20, 26, 47, 0.45);
  color: var(--color-text);
  padding: 8px 10px;
}

.error-text {
  color: var(--color-error);
}
</style>
