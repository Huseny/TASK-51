<script setup>
import { computed, onMounted, ref } from 'vue'
import { useRouter } from 'vue-router'
import AppShell from '@/components/layout/AppShell.vue'
import Badge from '@/components/ui/Badge.vue'
import Card from '@/components/ui/Card.vue'
import api from '@/services/api'
import { useAuthStore } from '@/stores/authStore'

const authStore = useAuthStore()
const router = useRouter()

const user = computed(() => authStore.user || { username: 'Guest', role: 'rider' })
const recommendations = ref([])
const recommendationError = ref('')
const canShop = computed(() => ['rider', 'driver', 'admin'].includes(user.value.role))

const fetchRecommendations = async () => {
  if (!canShop.value) {
    return
  }

  recommendationError.value = ''

  try {
    const response = await api.get('/recommendations')
    recommendations.value = response.data.data || []
  } catch (err) {
    recommendationError.value = err.response?.data?.message || 'Could not load recommendations.'
  }
}

const handleLogout = async () => {
  await authStore.logout()
  await router.push('/login')
}

onMounted(fetchRecommendations)
</script>

<template>
  <AppShell :user="user" @logout="handleLogout">
    <div class="dashboard-header">
      <h1>Welcome back, {{ user.username }}</h1>
      <Badge tone="success">{{ user.role }}</Badge>
    </div>

    <p class="helper-text">Your role-based dashboard shell is ready for trip, vehicle, and commerce modules.</p>

    <section class="stats-grid">
      <Card>
        <h3>Trips</h3>
        <p class="helper-text">No data yet. Metrics will appear once trip workflows are enabled.</p>
      </Card>

      <Card>
        <h3>Inventory</h3>
        <p class="helper-text">No data yet. Product modules will attach stock snapshots here.</p>
      </Card>

      <Card>
        <h3>Notifications</h3>
        <p class="helper-text">No data yet. Notification center integration is queued in next prompts.</p>
      </Card>
    </section>

    <section v-if="canShop" class="recommendations glass-card">
      <div class="recommendations__head">
        <h2>Recommended For You</h2>
        <button class="link-btn" type="button" @click="router.push('/shop/products')">Open Shop</button>
      </div>

      <p v-if="recommendationError" class="error">{{ recommendationError }}</p>
      <p v-else-if="!recommendations.length" class="helper-text">No recommendations yet. Check back after nightly batch run.</p>

      <div v-else class="carousel">
        <article
          v-for="entry in recommendations"
          :key="`${entry.rank_order}-${entry.item.id}`"
          class="product-card"
          @click="router.push(`/shop/products/${entry.item.id}`)"
        >
          <div class="product-card__head">
            <strong>{{ entry.item.name }}</strong>
            <span v-if="entry.is_exploration" class="discover">Discover</span>
          </div>
          <p>{{ entry.item.category }}</p>
          <small>Rank #{{ entry.rank_order }}</small>
        </article>
      </div>
    </section>
  </AppShell>
</template>

<style scoped>
.dashboard-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: var(--space-3);
  margin-bottom: var(--space-3);
}

h1 {
  margin: 0;
  font-size: clamp(1.4rem, 3vw, 2rem);
}

.stats-grid {
  margin-top: var(--space-6);
  display: grid;
  grid-template-columns: repeat(3, minmax(0, 1fr));
  gap: var(--space-4);
}

.recommendations {
  margin-top: var(--space-4);
  padding: var(--space-4);
  display: grid;
  gap: var(--space-3);
}

.recommendations__head {
  display: flex;
  justify-content: space-between;
  align-items: center;
  gap: var(--space-2);
}

.link-btn {
  border: none;
  background: transparent;
  color: var(--color-accent);
  cursor: pointer;
}

.carousel {
  display: grid;
  grid-auto-flow: column;
  grid-auto-columns: minmax(220px, 260px);
  gap: var(--space-3);
  overflow-x: auto;
  padding-bottom: 4px;
}

.product-card {
  border: 1px solid var(--color-border);
  border-radius: var(--radius-md);
  padding: var(--space-3);
  display: grid;
  gap: var(--space-2);
  cursor: pointer;
  background: rgba(255, 255, 255, 0.02);
}

.product-card__head {
  display: flex;
  justify-content: space-between;
  gap: var(--space-2);
}

.discover {
  border-radius: 999px;
  font-size: 0.72rem;
  padding: 2px 8px;
  color: #fff;
  background: rgba(6, 214, 160, 0.9);
}

.error {
  color: var(--color-error);
}

h3 {
  margin-top: 0;
}

@media (max-width: 980px) {
  .stats-grid {
    grid-template-columns: 1fr;
  }
}
</style>
