<script setup>
import { computed, nextTick, onBeforeUnmount, onMounted, ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import AppShell from '@/components/layout/AppShell.vue'
import ChatComposer from '@/components/chat/ChatComposer.vue'
import ChatMessageBubble from '@/components/chat/ChatMessageBubble.vue'
import DndSettingsModal from '@/components/chat/DndSettingsModal.vue'
import api from '@/services/api'
import { useAuthStore } from '@/stores/authStore'

const route = useRoute()
const router = useRouter()
const authStore = useAuthStore()

const user = computed(() => authStore.user || { id: 0, username: 'Guest', role: 'rider' })

const chat = ref(null)
const ride = ref(null)
const messages = ref([])
const unreadCount = ref(0)
const loading = ref(false)
const dndOpen = ref(false)
const activeParticipant = computed(() => (chat.value?.participants || []).find((item) => item.user_id === user.value.id))
const dndStart = computed(() => (activeParticipant.value?.dnd_start || '22:00:00').slice(0, 5))
const dndEnd = computed(() => (activeParticipant.value?.dnd_end || '07:00:00').slice(0, 5))

let pollTimer = null

const isDisbanded = computed(() => chat.value?.status === 'disbanded')
const lastMessageId = computed(() => messages.value.length ? messages.value[messages.value.length - 1].id : 0)

const scrollToBottom = async () => {
  await nextTick()
  const container = document.getElementById('chat-scroll')
  if (container) {
    container.scrollTop = container.scrollHeight
  }
}

const fetchRide = async () => {
  const rideId = route.params.id
  const endpoint = user.value.role === 'driver' || user.value.role === 'admin'
    ? `/driver/my-rides/${rideId}`
    : `/ride-orders/${rideId}`

  const response = await api.get(endpoint)
  ride.value = response.data.order || response.data
}

const markRead = async () => {
  if (!chat.value || !lastMessageId.value) {
    return
  }

  await api.post(`/group-chats/${chat.value.id}/read`, {
    up_to_message_id: lastMessageId.value,
  })

  unreadCount.value = 0
  localStorage.setItem('roadlink_chat_unread_total', '0')
}

const fetchChat = async () => {
  loading.value = true

  try {
    const response = await api.get(`/ride-orders/${route.params.id}/chat`)
    chat.value = response.data.chat
    messages.value = response.data.messages || []
    unreadCount.value = response.data.unread_count || 0
    localStorage.setItem('roadlink_chat_unread_total', String(unreadCount.value))

    await scrollToBottom()
    await markRead()
  } finally {
    loading.value = false
  }
}

const pollMessages = async () => {
  if (!chat.value) {
    return
  }

  const response = await api.get(`/group-chats/${chat.value.id}/messages`, {
    params: {
      after_id: lastMessageId.value,
      limit: 50,
    },
  })

  const incoming = response.data.messages || []
  if (incoming.length) {
    messages.value = [...messages.value, ...incoming]
    await scrollToBottom()
    await markRead()
  }
}

const sendMessage = async (content) => {
  if (!chat.value || isDisbanded.value) {
    return
  }

  const response = await api.post(`/group-chats/${chat.value.id}/messages`, {
    content,
  })

  if (response.data?.queued) {
    messages.value = [
      ...messages.value,
      {
        id: `queued-${Date.now()}`,
        sender_id: user.value.id,
        content,
        type: 'user_message',
        created_at: new Date().toISOString(),
        sender: { id: user.value.id, username: user.value.username },
        read_receipts: [],
      },
    ]
  } else {
    messages.value = [...messages.value, response.data.message]
  }

  await scrollToBottom()
}

const saveDnd = async (payload) => {
  if (!chat.value) {
    return
  }

  await api.patch(`/group-chats/${chat.value.id}/dnd`, payload)
  await fetchChat()
  dndOpen.value = false
}

const refreshInterval = () => (document.hidden ? 30000 : 5000)

const restartPolling = () => {
  if (pollTimer) {
    clearInterval(pollTimer)
  }

  pollTimer = setInterval(pollMessages, refreshInterval())
}

const handleVisibility = () => {
  restartPolling()
}

const handleLogout = async () => {
  await authStore.logout()
  await router.push('/login')
}

onMounted(async () => {
  await fetchRide()
  await fetchChat()
  restartPolling()
  document.addEventListener('visibilitychange', handleVisibility)
})

onBeforeUnmount(() => {
  if (pollTimer) {
    clearInterval(pollTimer)
  }

  document.removeEventListener('visibilitychange', handleVisibility)
})
</script>

<template>
  <AppShell :user="user" @logout="handleLogout">
    <section class="chat-layout glass-card">
      <header class="chat-header">
        <div>
          <h1>Trip #{{ ride?.id }} - {{ ride?.origin_address }} → {{ ride?.destination_address }}</h1>
          <p class="helper-text">Participants: {{ (chat?.participants || []).map((item) => item.user?.username).join(', ') }}</p>
        </div>
        <button class="dnd-btn" type="button" @click="dndOpen = true">⚙ DND</button>
      </header>

      <div id="chat-scroll" class="messages-scroll">
        <TransitionGroup name="fade" tag="div" class="messages-list">
          <ChatMessageBubble
            v-for="message in messages"
            :key="message.id"
            :message="message"
            :current-user-id="user.id"
          />
        </TransitionGroup>
      </div>

      <div v-if="isDisbanded" class="disbanded-overlay">
        This chat has ended
      </div>

      <ChatComposer :disabled="isDisbanded || loading" @send="sendMessage" />
    </section>

    <DndSettingsModal
      v-model="dndOpen"
      :start="dndStart"
      :end="dndEnd"
      @save="saveDnd"
    />
  </AppShell>
</template>

<style scoped>
.chat-layout {
  padding: var(--space-5);
  display: grid;
  gap: var(--space-3);
}

.chat-header {
  display: flex;
  justify-content: space-between;
  gap: var(--space-3);
}

h1 {
  margin: 0;
  font-size: 1.05rem;
}

.dnd-btn {
  border: 1px solid var(--color-border);
  border-radius: 999px;
  padding: 6px 12px;
  color: var(--color-text);
  background: rgba(255, 255, 255, 0.05);
  cursor: pointer;
  height: fit-content;
}

.messages-scroll {
  border: 1px solid rgba(151, 164, 208, 0.2);
  border-radius: var(--radius-md);
  min-height: 420px;
  max-height: 62vh;
  overflow-y: auto;
  padding: var(--space-3);
}

.messages-list {
  display: grid;
  gap: var(--space-2);
}

.disbanded-overlay {
  border: 1px solid rgba(151, 164, 208, 0.35);
  background: rgba(151, 164, 208, 0.14);
  border-radius: var(--radius-md);
  color: var(--color-text-muted);
  padding: 8px 12px;
}

@media (max-width: 760px) {
  .chat-header {
    flex-direction: column;
  }
}
</style>
