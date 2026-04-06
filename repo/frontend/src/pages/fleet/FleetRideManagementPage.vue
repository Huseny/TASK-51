<script setup>
import { computed, onMounted, ref } from 'vue'
import { useRouter } from 'vue-router'
import AppShell from '@/components/layout/AppShell.vue'
import api from '@/services/api'
import { useAuthStore } from '@/stores/authStore'

const router = useRouter()
const authStore = useAuthStore()

const user = computed(() => authStore.user || { username: 'Guest', role: 'fleet_manager' })
const queueRides = ref([])
const activeRides = ref([])
const drivers = ref([])
const selectedDrivers = ref({})
const loadingActionId = ref(null)
const error = ref('')

const loadData = async () => {
  error.value = ''

  try {
    const [queueResponse, activeResponse, driversResponse] = await Promise.all([
      api.get('/fleet/rides/queue', { params: { per_page: 40 } }),
      api.get('/fleet/rides/active', { params: { per_page: 40 } }),
      api.get('/fleet/drivers'),
    ])

    queueRides.value = queueResponse.data.data || []
    activeRides.value = activeResponse.data.data || []
    drivers.value = driversResponse.data.data || []
  } catch (err) {
    error.value = err.response?.data?.message || 'Unable to load fleet rides.'
  }
}

const selectedDriverId = (rideId) => Number(selectedDrivers.value[rideId] || 0)

const assignRide = async (rideId) => {
  const driverId = selectedDriverId(rideId)
  if (!driverId) {
    error.value = 'Select a driver before assigning the ride.'
    return
  }

  loadingActionId.value = rideId
  try {
    const ride = queueRides.value.find((entry) => entry.id === rideId)
    const endpoint = ride?.status === 'matching'
      ? `/fleet/rides/${rideId}/assign`
      : `/fleet/rides/${rideId}/reassign`

    await api.patch(endpoint, {
      driver_id: driverId,
      reason: 'fleet_dispatch_assignment',
    })
    await loadData()
  } catch (err) {
    error.value = err.response?.data?.message || 'Unable to assign ride.'
  } finally {
    loadingActionId.value = null
  }
}

const reassignRide = async (rideId) => {
  const driverId = selectedDriverId(rideId)
  loadingActionId.value = rideId

  try {
    await api.patch(`/fleet/rides/${rideId}/reassign`, {
      driver_id: driverId || undefined,
      reason: 'manual_reassignment',
    })
    await loadData()
  } catch (err) {
    error.value = err.response?.data?.message || 'Unable to reassign ride.'
  } finally {
    loadingActionId.value = null
  }
}

const cancelRide = async (rideId) => {
  loadingActionId.value = rideId

  try {
    await api.patch(`/fleet/rides/${rideId}/cancel`, {
      reason: 'fleet_canceled',
    })
    await loadData()
  } catch (err) {
    error.value = err.response?.data?.message || 'Unable to cancel ride.'
  } finally {
    loadingActionId.value = null
  }
}

const handleLogout = async () => {
  await authStore.logout()
  await router.push('/login')
}

onMounted(loadData)
</script>

<template>
  <AppShell :user="user" @logout="handleLogout">
    <section class="header">
      <h1>Fleet Ride Management</h1>
      <p class="helper-text">Dispatch drivers, monitor active rides, and resolve operational exceptions.</p>
    </section>

    <p v-if="error" class="error-text">{{ error }}</p>

    <div class="grid">
      <section class="glass-card panel">
        <h2>Dispatch Queue</h2>
        <p v-if="!queueRides.length" class="helper-text">No rides waiting in the queue.</p>

        <article v-for="ride in queueRides" :key="ride.id" class="ride-card">
          <div class="ride-card__head">
            <strong>{{ ride.origin_address }} → {{ ride.destination_address }}</strong>
            <span class="status">{{ ride.status.replace('_', ' ') }}</span>
          </div>
          <p class="helper-text">Rider: {{ ride.rider?.username || 'Unknown' }} · {{ new Date(ride.time_window_start).toLocaleString() }}</p>

          <div class="actions">
            <select :data-testid="`queue-driver-select-${ride.id}`" v-model="selectedDrivers[ride.id]">
              <option value="">Select driver</option>
              <option v-for="driver in drivers" :key="driver.id" :value="driver.id">
                {{ driver.username }}
              </option>
            </select>
            <button
              :data-testid="`queue-assign-${ride.id}`"
              type="button"
              :disabled="loadingActionId === ride.id"
              @click="assignRide(ride.id)"
            >
              Assign
            </button>
            <button type="button" class="link" @click="router.push(`/fleet/rides/${ride.id}`)">Open</button>
          </div>
        </article>
      </section>

      <section class="glass-card panel">
        <h2>Active Operations</h2>
        <p v-if="!activeRides.length" class="helper-text">No active rides right now.</p>

        <article v-for="ride in activeRides" :key="ride.id" class="ride-card">
          <div class="ride-card__head">
            <strong>{{ ride.origin_address }} → {{ ride.destination_address }}</strong>
            <span class="status">{{ ride.status.replace('_', ' ') }}</span>
          </div>
          <p class="helper-text">
            Driver: {{ ride.driver?.username || 'Unassigned' }} · Rider: {{ ride.rider?.username || 'Unknown' }}
          </p>

          <div class="actions">
            <select :data-testid="`active-driver-select-${ride.id}`" v-model="selectedDrivers[ride.id]">
              <option value="">Keep queue open</option>
              <option v-for="driver in drivers" :key="driver.id" :value="driver.id">
                {{ driver.username }}
              </option>
            </select>
            <button
              :data-testid="`active-reassign-${ride.id}`"
              type="button"
              :disabled="loadingActionId === ride.id"
              @click="reassignRide(ride.id)"
            >
              Reassign
            </button>
            <button
              :data-testid="`active-cancel-${ride.id}`"
              type="button"
              :disabled="loadingActionId === ride.id"
              @click="cancelRide(ride.id)"
            >
              Cancel
            </button>
            <button type="button" class="link" @click="router.push(`/fleet/rides/${ride.id}`)">Open</button>
          </div>
        </article>
      </section>
    </div>
  </AppShell>
</template>

<style scoped>
.header {
  margin-bottom: var(--space-4);
}

.grid {
  display: grid;
  grid-template-columns: repeat(2, minmax(0, 1fr));
  gap: var(--space-4);
}

.panel {
  padding: var(--space-5);
  display: grid;
  gap: var(--space-3);
}

.ride-card {
  display: grid;
  gap: var(--space-2);
  border: 1px solid var(--color-border);
  border-radius: var(--radius-md);
  padding: var(--space-3);
}

.ride-card__head,
.actions {
  display: flex;
  justify-content: space-between;
  gap: var(--space-2);
  flex-wrap: wrap;
}

.status {
  border: 1px solid var(--color-border);
  border-radius: 999px;
  padding: 4px 10px;
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

.link {
  color: #8ca0ff;
}

.error-text {
  color: var(--color-error);
}

@media (max-width: 900px) {
  .grid {
    grid-template-columns: 1fr;
  }
}
</style>
