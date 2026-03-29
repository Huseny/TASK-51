<script setup>
import { computed, onMounted, ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import AppShell from '@/components/layout/AppShell.vue'
import Button from '@/components/ui/Button.vue'
import Input from '@/components/ui/Input.vue'
import VehicleGallery from '@/components/vehicles/VehicleGallery.vue'
import api from '@/services/api'
import { useAuthStore } from '@/stores/authStore'

const route = useRoute()
const router = useRouter()
const authStore = useAuthStore()

const user = computed(() => authStore.user || { username: 'Guest', role: 'driver' })
const vehicle = ref(null)
const mediaItems = ref([])
const loading = ref(false)
const toastError = ref('')

const form = ref({
  make: '',
  model: '',
  year: '',
  license_plate: '',
  color: '',
  capacity: 4,
  status: 'active',
})

const loadVehicle = async () => {
  const response = await api.get(`/vehicles/${route.params.id}`)
  vehicle.value = response.data.vehicle
  form.value = {
    make: vehicle.value.make,
    model: vehicle.value.model,
    year: vehicle.value.year,
    license_plate: vehicle.value.license_plate,
    color: vehicle.value.color || '',
    capacity: vehicle.value.capacity,
    status: vehicle.value.status,
  }

  const assets = vehicle.value.media_assets || []
  const hydrated = []
  for (const asset of assets) {
    const urlRes = await api.get(`/media/${asset.id}/url`)
    hydrated.push({
      ...asset,
      url: urlRes.data.url,
      is_cover: Boolean(asset.pivot?.is_cover),
      sort_order: asset.pivot?.sort_order || 0,
    })
  }

  mediaItems.value = hydrated.sort((a, b) => a.sort_order - b.sort_order)
}

const saveVehicle = async () => {
  loading.value = true

  try {
    await api.put(`/vehicles/${route.params.id}`, form.value)
    await loadVehicle()
  } finally {
    loading.value = false
  }
}

const uploadFiles = async (files) => {
  for (const file of files) {
    const data = new FormData()
    data.append('file', file)
    await api.post(`/vehicles/${route.params.id}/media`, data)
  }

  await loadVehicle()
}

const reorder = async (order) => {
  await api.patch(`/vehicles/${route.params.id}/media/reorder`, { order })
  await loadVehicle()
}

const setCover = async (mediaId) => {
  if (!window.confirm('Set this media as cover image?')) {
    return
  }

  await api.patch(`/vehicles/${route.params.id}/media/${mediaId}/cover`)
  await loadVehicle()
}

const removeMedia = async (mediaId) => {
  if (!window.confirm('Remove this media from gallery?')) {
    return
  }

  await api.delete(`/vehicles/${route.params.id}/media/${mediaId}`)
  await loadVehicle()
}

const handleUploadError = (message) => {
  toastError.value = message
  setTimeout(() => {
    toastError.value = ''
  }, 3000)
}

const handleLogout = async () => {
  await authStore.logout()
  await router.push('/login')
}

onMounted(loadVehicle)
</script>

<template>
  <AppShell :user="user" @logout="handleLogout">
    <p v-if="toastError" class="error-toast">{{ toastError }}</p>

    <section class="grid">
      <article class="glass-card panel">
        <h2>Vehicle Info</h2>
        <div class="form-grid">
          <Input v-model="form.make" label="Make" />
          <Input v-model="form.model" label="Model" />
          <Input v-model="form.year" type="number" label="Year" />
          <Input v-model="form.license_plate" label="License Plate" />
          <Input v-model="form.color" label="Color" />
          <Input v-model="form.capacity" type="number" label="Capacity" />
        </div>
        <Button :loading="loading" @click="saveVehicle">Save</Button>
      </article>

      <article class="glass-card panel">
        <h2>Gallery</h2>
        <VehicleGallery
          :media-items="mediaItems"
          @reorder="reorder"
          @set-cover="setCover"
          @remove="removeMedia"
          @upload-files="uploadFiles"
          @upload-error="handleUploadError"
        />
      </article>
    </section>
  </AppShell>
</template>

<style scoped>
.grid {
  display: grid;
  gap: var(--space-4);
}

.panel {
  padding: var(--space-5);
  display: grid;
  gap: var(--space-3);
}

h2 {
  margin: 0;
}

.form-grid {
  display: grid;
  grid-template-columns: repeat(2, minmax(0, 1fr));
  gap: var(--space-2);
}

.error-toast {
  margin: 0 0 var(--space-2);
  color: #ffdce4;
  background: rgba(239, 71, 111, 0.28);
  border: 1px solid rgba(239, 71, 111, 0.56);
  border-radius: var(--radius-md);
  padding: 10px 12px;
}

@media (max-width: 760px) {
  .form-grid {
    grid-template-columns: 1fr;
  }
}
</style>
