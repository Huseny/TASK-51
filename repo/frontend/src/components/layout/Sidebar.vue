<script setup>
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
    { label: 'Shop', to: '/dashboard' },
    { label: 'Notifications', to: '/dashboard' },
    { label: 'Settings', to: '/dashboard' },
  ],
  driver: [
    { label: 'Dashboard', to: '/dashboard' },
    { label: 'Available Rides', to: '/dashboard' },
    { label: 'My Rides', to: '/dashboard' },
    { label: 'Vehicles', to: '/dashboard' },
    { label: 'Notifications', to: '/dashboard' },
    { label: 'Settings', to: '/dashboard' },
  ],
  fleet_manager: [
    { label: 'Dashboard', to: '/dashboard' },
    { label: 'Vehicles', to: '/dashboard' },
    { label: 'Products', to: '/dashboard' },
    { label: 'Reports', to: '/dashboard' },
    { label: 'Notifications', to: '/dashboard' },
    { label: 'Settings', to: '/dashboard' },
  ],
  admin: [
    { label: 'Dashboard', to: '/dashboard' },
    { label: 'Users', to: '/dashboard' },
    { label: 'All Rides', to: '/dashboard' },
    { label: 'Reports', to: '/dashboard' },
    { label: 'Notifications', to: '/dashboard' },
    { label: 'Settings', to: '/dashboard' },
  ],
}
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
</style>
