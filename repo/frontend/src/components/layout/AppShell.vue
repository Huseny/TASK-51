<script setup>
import { useNetwork } from '@/composables/useNetwork'
import Navbar from './Navbar.vue'
import Sidebar from './Sidebar.vue'

defineProps({
  user: {
    type: Object,
    required: true,
  },
})

const emit = defineEmits(['logout'])
const { isOnline } = useNetwork()
</script>

<template>
  <div class="shell page-container">
    <div class="shell__sidebar">
      <Sidebar :role="user.role" />
    </div>

    <div class="shell__content">
      <Navbar
        :username="user.username"
        :role="user.role"
        @logout="emit('logout')"
      />

      <div v-if="!isOnline" class="offline-banner">
        You're offline - data may not be current. Actions will sync when reconnected.
      </div>

      <main class="shell__main glass-card">
        <slot />
      </main>
    </div>
  </div>
</template>

<style scoped>
.shell {
  min-height: 100vh;
  display: grid;
  grid-template-columns: 290px minmax(0, 1fr);
  gap: var(--space-4);
  padding: var(--space-4);
}

.shell__content {
  display: grid;
  gap: var(--space-4);
  align-content: start;
}

.shell__main {
  padding: var(--space-6);
  min-height: calc(100vh - 132px);
}

.offline-banner {
  border: 1px solid rgba(255, 209, 102, 0.45);
  background: rgba(255, 209, 102, 0.15);
  color: #ffe8b6;
  border-radius: var(--radius-sm);
  padding: 10px 12px;
  font-size: 0.9rem;
}

@media (max-width: 980px) {
  .shell {
    grid-template-columns: 1fr;
  }

  .shell__sidebar {
    order: 2;
  }
}
</style>
