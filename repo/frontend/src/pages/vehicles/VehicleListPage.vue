<script setup>
import { computed, onMounted, ref } from 'vue'
import { useRouter } from 'vue-router'
import AppShell from '@/components/layout/AppShell.vue'
import Button from '@/components/ui/Button.vue'
import Input from '@/components/ui/Input.vue'
import api from '@/services/api'
import { useAuthStore } from '@/stores/authStore'

const authStore = useAuthStore()
const router = useRouter()

const user = computed(() => authStore.user || { username: 'Guest', role: 'driver' })
const vehicles = ref([])
const showCreate = ref(false)
const error = ref('')
const loading = ref(false)

const form = ref({
  make: '',
  model: '',
  year: new Date().getFullYear(),
  license_plate: '',
  color: '',
  capacity: 4,
})

const fetchVehicles = async () => {
  const response = await api.get('/vehicles')
  const raw = response.data.data || []
  const hydrated = []

  for (const vehicle of raw) {
    const cover = (vehicle.media_assets || []).find((item) => item.pivot?.is_cover)
    let coverUrl = null

    if (cover) {
      try {
        const urlResponse = await api.get(`/media/${cover.id}/url`)
        coverUrl = urlResponse.data.url
      } catch {
        coverUrl = null
      }
    }

    hydrated.push({
      ...vehicle,
      cover_url: coverUrl,
    })
  }

  vehicles.value = hydrated
}

const createVehicle = async () => {
  loading.value = true
  error.value = ''

  try {
    await api.post('/vehicles', form.value)
    showCreate.value = false
    form.value = {
      make: '',
      model: '',
      year: new Date().getFullYear(),
      license_plate: '',
      color: '',
      capacity: 4,
    }
    await fetchVehicles()
  } catch (err) {
    error.value = err.response?.data?.message || 'Failed to create vehicle.'
  } finally {
    loading.value = false
  }
}

const handleLogout = async () => {
  await authStore.logout()
  await router.push('/login')
}

onMounted(fetchVehicles)
</script>

<template>
  <AppShell :user="user" @logout="handleLogout">
    <section class="header">
      <div>
        <h1>Vehicles</h1>
        <p class="helper-text">Manage your fleet-ready vehicle profiles.</p>
      </div>
      <button class="add-btn" type="button" @click="showCreate = true">Add Vehicle</button>
    </section>

    <section v-if="vehicles.length" class="grid">
      <article
        v-for="vehicle in vehicles"
        :key="vehicle.id"
        class="vehicle-card glass-card"
        @click="router.push(`/vehicles/${vehicle.id}`)"
      >
        <div class="thumb">
          <img v-if="vehicle.cover_url" :src="vehicle.cover_url" alt="cover">
          <span v-else>🚗</span>
        </div>
        <h3>{{ vehicle.make }} {{ vehicle.model }} {{ vehicle.year }}</h3>
        <p>{{ vehicle.license_plate }}</p>
        <div class="meta">
          <span>Capacity: {{ vehicle.capacity }}</span>
          <span class="status" :class="vehicle.status">{{ vehicle.status }}</span>
        </div>
      </article>
    </section>

    <section v-else class="empty glass-card">
      <h3>No vehicles yet. Add your first vehicle to get started.</h3>
    </section>

    <Teleport to="body">
      <div v-if="showCreate" class="modal-backdrop" @click.self="showCreate = false">
        <section class="modal glass-card">
          <h3>Create Vehicle</h3>
          <form class="form" @submit.prevent="createVehicle">
            <Input v-model="form.make" label="Make" />
            <Input v-model="form.model" label="Model" />
            <Input v-model="form.year" type="number" label="Year" />
            <Input v-model="form.license_plate" label="License Plate" />
            <Input v-model="form.color" label="Color" />
            <Input v-model="form.capacity" type="number" label="Capacity" />
            <p v-if="error" class="error">{{ error }}</p>
            <Button :loading="loading" type="submit">Save Vehicle</Button>
          </form>
        </section>
      </div>
    </Teleport>
  </AppShell>
</template>

<style scoped>
.header {
  display: flex;
  justify-content: space-between;
  gap: var(--space-3);
  align-items: flex-start;
}

h1,
h3,
p {
  margin: 0;
}

.add-btn {
  border: none;
  border-radius: 999px;
  background: linear-gradient(120deg, var(--color-accent), #5f7cff);
  color: #fff;
  padding: 8px 14px;
  cursor: pointer;
}

.grid {
  margin-top: var(--space-4);
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(220px, 1fr));
  gap: var(--space-3);
}

.vehicle-card {
  padding: var(--space-3);
  display: grid;
  gap: var(--space-2);
  cursor: pointer;
}

.thumb {
  height: 120px;
  border-radius: var(--radius-md);
  background: rgba(151, 164, 208, 0.16);
  display: grid;
  place-items: center;
  overflow: hidden;
}

.thumb img {
  width: 100%;
  height: 100%;
  object-fit: cover;
}

.meta {
  display: flex;
  justify-content: space-between;
  color: var(--color-text-muted);
  font-size: 0.85rem;
}

.status.active {
  color: #8ff0c6;
}

.status.inactive {
  color: #b8bfd6;
}

.empty {
  margin-top: var(--space-4);
  padding: var(--space-6);
  text-align: center;
}

.modal-backdrop {
  position: fixed;
  inset: 0;
  background: rgba(8, 11, 21, 0.72);
  display: grid;
  place-items: center;
  padding: var(--space-4);
  z-index: 90;
}

.modal {
  width: min(480px, 100%);
  padding: var(--space-5);
}

.form {
  display: grid;
  gap: var(--space-2);
}

.error {
  color: var(--color-error);
}
</style>
