<script setup>
import { onBeforeUnmount, onMounted, ref } from 'vue'

const props = defineProps({
  role: {
    type: String,
    default: 'rider',
  },
})

const linksByRole = {
  rider: [
    { label: 'Dashboard', to: '/dashboard' },
    { label: 'My Trips', to: '/rider/trips' },
    { label: 'Chat', to: '/rider/trips', badge: 'chat' },
    { label: 'Shop', to: '/shop/products' },
    { label: 'Notifications', to: '/dashboard' },
    { label: 'Settings', to: '/settings/notifications' },
  ],
  driver: [
    { label: 'Dashboard', to: '/dashboard' },
    { label: 'Available Rides', to: '/driver/available-rides' },
    { label: 'My Rides', to: '/driver/my-rides' },
    { label: 'Chat', to: '/driver/my-rides', badge: 'chat' },
    { label: 'Shop', to: '/shop/products' },
    { label: 'Vehicles', to: '/vehicles' },
    { label: 'Notifications', to: '/dashboard' },
    { label: 'Settings', to: '/settings/notifications' },
  ],
  fleet_manager: [
    { label: 'Dashboard', to: '/dashboard' },
    { label: 'Vehicles', to: '/vehicles' },
    { label: 'Products', to: '/products/manage' },
    { label: 'Reports', to: '/reports' },
    { label: 'Notifications', to: '/dashboard' },
    { label: 'Settings', to: '/settings/notifications' },
  ],
  admin: [
    { label: 'Dashboard', to: '/dashboard' },
    { label: 'Users', to: '/dashboard' },
    { label: 'All Rides', to: '/dashboard' },
    { label: 'Products', to: '/products/manage' },
    { label: 'Reports', to: '/reports' },
    { label: 'Notifications', to: '/dashboard' },
    { label: 'Settings', to: '/settings/notifications' },
  ],
}

const chatUnread = ref(Number(localStorage.getItem('roadlink_chat_unread_total') || 0))
let pollTimer = null

const syncBadge = () => {
  chatUnread.value = Number(localStorage.getItem('roadlink_chat_unread_total') || 0)
}

onMounted(() => {
  pollTimer = setInterval(syncBadge, 3000)
})

onBeforeUnmount(() => {
  if (pollTimer) {
    clearInterval(pollTimer)
  }
})
</script>

<template>
  <aside class="sidebar glass-card">
    <p class="sidebar__title">Workspace</p>
    <nav class="sidebar__links">
      <RouterLink
        v-for="link in linksByRole[props.role] || linksByRole.rider"
        :key="link.label"
        :to="link.to"
      >
        {{ link.label }}
        <span v-if="link.badge === 'chat' && chatUnread > 0" class="link-badge">{{ chatUnread }}</span>
      </RouterLink>
    </nav>
  </aside>
</template>

<style scoped>
.sidebar {
  padding: var(--space-6);
  min-height: 100%;
}

.sidebar__title {
  margin: 0 0 var(--space-4);
  color: var(--color-text-muted);
  font-size: 0.86rem;
  text-transform: uppercase;
  letter-spacing: 0.08em;
}

.sidebar__links {
  display: grid;
  gap: var(--space-2);
}

.sidebar__links :deep(a) {
  text-decoration: none;
  color: var(--color-text);
  border-radius: var(--radius-sm);
  padding: 10px 12px;
  transition: background var(--transition-fast), transform var(--transition-fast);
}

.sidebar__links :deep(a:hover),
.sidebar__links :deep(.router-link-active) {
  background: rgba(67, 97, 238, 0.18);
  transform: translateX(4px);
}

.sidebar__links :deep(a) {
  display: flex;
  align-items: center;
  justify-content: space-between;
}

.link-badge {
  min-width: 20px;
  height: 20px;
  border-radius: 999px;
  padding: 0 6px;
  display: inline-grid;
  place-items: center;
  background: rgba(239, 71, 111, 0.85);
  color: #fff;
  font-size: 0.72rem;
}
</style>
