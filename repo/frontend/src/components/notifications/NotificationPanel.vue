<script setup>
import { ref, watch } from 'vue'
import { useRouter } from 'vue-router'
import api from '@/services/api'

const props = defineProps({
  open: {
    type: Boolean,
    default: false,
  },
})

const emit = defineEmits(['close', 'count-updated'])
const router = useRouter()

const items = ref([])
const loading = ref(false)
const error = ref('')

const fetchNotifications = async () => {
  loading.value = true
  error.value = ''

  try {
    const response = await api.get('/notifications', { params: { per_page: 20 } })
    items.value = response.data.data || []
  } catch (err) {
    error.value = err.response?.data?.message || 'Could not load notifications.'
  } finally {
    loading.value = false
  }
}

const refreshCount = async () => {
  const response = await api.get('/notifications/unread-count')
  emit('count-updated', Number(response.data.unread_count || 0))
}

const markAllRead = async () => {
  await api.patch('/notifications/read-all')
  await fetchNotifications()
  await refreshCount()
}

const openItem = async (item) => {
  await api.patch(`/notifications/${item.id}/read`)
  await refreshCount()

  const target = item.data?.url
  if (target) {
    await router.push(target)
  }

  emit('close')
}

const scenarioLabel = (item) => {
  const scenario = item.data?.scenario

  if (scenario) {
    return scenario.replace('_', ' ')
  }

  if (item.type === 'order_update') {
    return 'comment'
  }

  if (item.type === 'system') {
    return 'announcement'
  }

  return String(item.type || 'update').replace('_', ' ')
}

watch(
  () => props.open,
  async (nextOpen) => {
    if (nextOpen) {
      await fetchNotifications()
      await refreshCount()
    }
  },
  { immediate: true }
)
</script>

<template>
  <section v-if="open" class="panel glass-card">
    <div class="panel__header">
      <strong>Notifications</strong>
      <div class="panel__actions">
        <button class="link" type="button" @click="markAllRead">Mark all as read</button>
        <button class="link" type="button" @click="emit('close')">Close</button>
      </div>
    </div>

    <p v-if="error" class="error">{{ error }}</p>
    <p v-else-if="loading" class="helper-text">Loading...</p>
    <p v-else-if="!items.length" class="helper-text">You're all caught up.</p>

    <div v-else class="list">
      <button
        v-for="item in items"
        :key="item.id"
        type="button"
        class="item"
        :class="{ 'item--unread': !item.is_read }"
        @click="openItem(item)"
      >
        <div class="item__row">
          <strong>{{ item.title }}</strong>
          <div class="item__meta">
            <small class="pill">{{ scenarioLabel(item) }}</small>
            <small v-if="item.count > 1">{{ item.count }}</small>
          </div>
        </div>
        <p>{{ item.body }}</p>
      </button>
    </div>
  </section>
</template>

<style scoped>
.panel {
  width: min(460px, 92vw);
  max-height: min(70vh, 620px);
  overflow: auto;
  padding: var(--space-4);
  display: grid;
  gap: var(--space-3);
}

.panel__header,
.panel__actions,
.item__row {
  display: flex;
  justify-content: space-between;
  align-items: center;
  gap: var(--space-2);
}

.item__meta {
  display: inline-flex;
  align-items: center;
  gap: 6px;
}

.pill {
  display: inline-flex;
  padding: 2px 8px;
  border-radius: 999px;
  border: 1px solid var(--color-border);
  text-transform: capitalize;
  color: var(--color-text-muted);
}

.list {
  display: grid;
  gap: var(--space-2);
}

.item {
  text-align: left;
  border: 1px solid var(--color-border);
  border-radius: var(--radius-sm);
  padding: 10px;
  background: rgba(255, 255, 255, 0.02);
  color: var(--color-text);
  cursor: pointer;
}

.item--unread {
  background: rgba(67, 97, 238, 0.18);
  border-color: rgba(109, 138, 255, 0.42);
}

.item p {
  margin: 6px 0 0;
  color: var(--color-text-muted);
}

.link {
  border: none;
  background: transparent;
  color: var(--color-accent);
  cursor: pointer;
}

.error {
  color: var(--color-error);
}
</style>
