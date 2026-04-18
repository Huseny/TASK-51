<script setup>
import { computed, onMounted, ref } from 'vue'
import { useRouter } from 'vue-router'
import AppShell from '@/components/layout/AppShell.vue'
import Button from '@/components/ui/Button.vue'
import Input from '@/components/ui/Input.vue'
import TripCard from '@/components/rides/TripCard.vue'
import api from '@/services/api'
import { useAuthStore } from '@/stores/authStore'

const router = useRouter()
const authStore = useAuthStore()

const user = computed(() => authStore.user || { username: 'Guest', role: 'rider' })
const orders = ref([])
const activeTab = ref('all')
const isLoading = ref(false)
const showCreateModal = ref(false)
const createError = ref('')

const form = ref({
  origin_address: '',
  destination_address: '',
  rider_count: 1,
  date: '',
  start_time: '',
  end_time: '',
  notes: '',
})

const tabs = ['all', 'matching', 'accepted', 'in_progress', 'exception', 'completed', 'canceled']

const filteredOrders = computed(() => {
  if (activeTab.value === 'all') {
    return orders.value
  }

  return orders.value.filter((item) => item.status === activeTab.value)
})

const validateForm = () => {
  if (!form.value.origin_address.trim() || !form.value.destination_address.trim()) {
    return 'Origin and destination are required.'
  }

  if (form.value.rider_count < 1 || form.value.rider_count > 6) {
    return 'Rider count must be between 1 and 6.'
  }

  if (!form.value.date || !form.value.start_time || !form.value.end_time) {
    return 'Please select date, start time, and end time.'
  }

  const start = new Date(`${form.value.date}T${form.value.start_time}:00`)
  const end = new Date(`${form.value.date}T${form.value.end_time}:00`)

  if (start <= new Date()) {
    return 'Start time must be in the future.'
  }

  if (end <= start) {
    return 'End time must be after start time.'
  }

  return ''
}

const toBackendDate = (date, time) => `${date} ${time}`

const fetchOrders = async () => {
  isLoading.value = true

  try {
    const response = await api.get('/ride-orders', {
      params: {
        ...(activeTab.value !== 'all' ? { status: activeTab.value } : {}),
        per_page: 30,
      },
    })

    orders.value = response.data.data || []
  } finally {
    isLoading.value = false
  }
}

const openCreateModal = () => {
  showCreateModal.value = true
  createError.value = ''
}

const submitCreate = async () => {
  createError.value = validateForm()
  if (createError.value) {
    return
  }

  isLoading.value = true

  try {
    await api.post('/ride-orders', {
      origin_address: form.value.origin_address,
      destination_address: form.value.destination_address,
      rider_count: form.value.rider_count,
      time_window_start: toBackendDate(form.value.date, form.value.start_time),
      time_window_end: toBackendDate(form.value.date, form.value.end_time),
      notes: form.value.notes || null,
    })
  } catch (error) {
    createError.value = error.response?.data?.message || 'Failed to create trip request.'
    isLoading.value = false
    return
  }

  showCreateModal.value = false
  form.value = {
    origin_address: '',
    destination_address: '',
    rider_count: 1,
    date: '',
    start_time: '',
    end_time: '',
    notes: '',
  }

  try {
    await fetchOrders()
  } catch {
  } finally {
    isLoading.value = false
  }
}

const adjustRiderCount = (delta) => {
  const next = form.value.rider_count + delta
  form.value.rider_count = Math.max(1, Math.min(6, next))
}

const gotoDetail = (id) => router.push(`/rider/trips/${id}`)

const handleLogout = async () => {
  await authStore.logout()
  await router.push('/login')
}

onMounted(fetchOrders)
</script>

<template>
  <AppShell :user="user" @logout="handleLogout">
    <section class="trips-header">
      <div>
        <h1>My Trips</h1>
        <p class="helper-text">Create and track ride order lifecycle in real time.</p>
      </div>

      <button class="fab" type="button" @click="openCreateModal">+ New Trip</button>
    </section>

    <div class="tabs">
      <button
        v-for="tab in tabs"
        :key="tab"
        type="button"
        :class="{ active: tab === activeTab }"
        @click="activeTab = tab; fetchOrders()"
      >
        {{ tab.replace('_', ' ') }}
      </button>
    </div>

    <div class="trip-list">
      <p v-if="isLoading" class="helper-text">Loading trips...</p>
      <p v-else-if="filteredOrders.length === 0" class="helper-text">No trips in this status yet.</p>

      <TripCard
        v-for="order in filteredOrders"
        :key="order.id"
        :order="order"
        @click="gotoDetail(order.id)"
      />
    </div>

    <Teleport to="body">
      <div v-if="showCreateModal" class="modal-backdrop" @click.self="showCreateModal = false">
        <section class="modal glass-card">
          <h2>New Trip Request</h2>

          <form class="form-stack" @submit.prevent="submitCreate">
            <Input
              v-model="form.origin_address"
              label="Origin"
              icon="📍"
              placeholder="e.g., 123 Main St, Suite 4, Springfield"
            />

            <Input
              v-model="form.destination_address"
              label="Destination"
              icon="🏁"
              placeholder="e.g., 456 Oak Ave, Downtown Mall"
            />

            <label class="helper-text">Rider Count</label>
            <div class="stepper">
              <button type="button" @click="adjustRiderCount(-1)">-</button>
              <strong>{{ form.rider_count }}</strong>
              <button type="button" @click="adjustRiderCount(1)">+</button>
            </div>

            <div class="time-grid">
              <label>
                <span class="helper-text">Date</span>
                <input v-model="form.date" type="date">
              </label>
              <label>
                <span class="helper-text">Start</span>
                <input v-model="form.start_time" type="time">
              </label>
              <label>
                <span class="helper-text">End</span>
                <input v-model="form.end_time" type="time">
              </label>
            </div>

            <label>
              <span class="helper-text">Notes</span>
              <textarea v-model="form.notes" placeholder="Luggage size, accessibility needs, special requests..." />
            </label>

            <p v-if="createError" class="error-text">{{ createError }}</p>

            <Button type="submit" :loading="isLoading">Create Trip</Button>
          </form>
        </section>
      </div>
    </Teleport>
  </AppShell>
</template>

<style scoped>
.trips-header {
  display: flex;
  justify-content: space-between;
  gap: var(--space-4);
  align-items: flex-start;
}

h1 {
  margin: 0;
}

.fab {
  position: fixed;
  right: 26px;
  bottom: 26px;
  z-index: 40;
  border: none;
  border-radius: 999px;
  padding: 11px 18px;
  color: #eef2ff;
  background: linear-gradient(120deg, var(--color-accent), #5876ff);
  box-shadow: var(--shadow-sm);
  cursor: pointer;
}

.tabs {
  margin-top: var(--space-5);
  display: flex;
  flex-wrap: wrap;
  gap: var(--space-2);
}

.tabs button {
  border: 1px solid var(--color-border);
  background: rgba(255, 255, 255, 0.03);
  color: var(--color-text);
  border-radius: 999px;
  padding: 6px 12px;
  cursor: pointer;
  text-transform: capitalize;
}

.tabs button.active {
  background: rgba(67, 97, 238, 0.26);
  border-color: rgba(67, 97, 238, 0.56);
}

.trip-list {
  margin-top: var(--space-5);
  display: grid;
  gap: var(--space-3);
}

.modal-backdrop {
  position: fixed;
  inset: 0;
  background: rgba(5, 8, 18, 0.72);
  display: grid;
  place-items: center;
  padding: var(--space-4);
  z-index: 80;
}

.modal {
  width: min(560px, 100%);
  padding: var(--space-6);
}

.stepper {
  display: inline-flex;
  align-items: center;
  border: 1px solid var(--color-border);
  border-radius: var(--radius-md);
  overflow: hidden;
}

.stepper button {
  border: none;
  color: var(--color-text);
  background: rgba(255, 255, 255, 0.08);
  width: 40px;
  height: 36px;
  cursor: pointer;
}

.stepper strong {
  width: 46px;
  text-align: center;
}

.time-grid {
  display: grid;
  grid-template-columns: repeat(3, 1fr);
  gap: var(--space-2);
}

input,
textarea {
  width: 100%;
  border-radius: var(--radius-md);
  border: 1px solid var(--color-border);
  background: rgba(20, 26, 47, 0.45);
  color: var(--color-text);
  padding: 10px 12px;
}

textarea {
  min-height: 90px;
  resize: vertical;
}

.error-text {
  margin: 0;
  color: var(--color-error);
}

@media (max-width: 760px) {
  .time-grid {
    grid-template-columns: 1fr;
  }

  .trips-header {
    flex-direction: column;
  }

  .fab {
    right: 16px;
    bottom: 16px;
  }
}
</style>
