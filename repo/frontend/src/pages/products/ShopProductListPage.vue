<script setup>
import { computed, onMounted, ref } from 'vue'
import { useRouter } from 'vue-router'
import AppShell from '@/components/layout/AppShell.vue'
import api from '@/services/api'
import { useAuthStore } from '@/stores/authStore'

const authStore = useAuthStore()
const router = useRouter()

const user = computed(() => authStore.user || { username: 'Guest', role: 'rider' })
const products = ref([])
const search = ref('')
const category = ref('')

const fetchProducts = async () => {
  const params = {}
  if (search.value) {
    params.q = search.value
  }
  if (category.value) {
    params.category = category.value
  }

  const response = await api.get('/products', { params })
  products.value = response.data.data || []
}

const handleLogout = async () => {
  await authStore.logout()
  await router.push('/login')
}

onMounted(fetchProducts)
</script>

<template>
  <AppShell :user="user" @logout="handleLogout">
    <section class="header">
      <div>
        <h1>Shop Add-On Products</h1>
        <p class="helper-text">Browse published add-ons with quantity tier discounts.</p>
      </div>
      <div class="filters">
        <input v-model="search" placeholder="Search by name" @keyup.enter="fetchProducts">
        <input v-model="category" placeholder="Category" @keyup.enter="fetchProducts">
        <button class="add-btn" @click="fetchProducts">Apply</button>
      </div>
    </section>

    <section v-if="products.length" class="grid">
      <article v-for="product in products" :key="product.id" class="card glass-card">
        <h3>{{ product.name }}</h3>
        <p>{{ product.description || 'No description provided' }}</p>
        <p class="meta">{{ product.category }} · {{ product.variants?.length || 0 }} variants</p>
        <button class="text-btn" @click="router.push(`/shop/products/${product.id}`)">View Details</button>
      </article>
    </section>

    <section v-else class="empty glass-card">
      <p>No products matched your filters.</p>
    </section>
  </AppShell>
</template>

<style scoped>
h1, h3, p {
  margin: 0;
}

.header {
  display: flex;
  justify-content: space-between;
  align-items: flex-start;
  gap: var(--space-3);
}

.filters {
  display: flex;
  gap: 8px;
}

input {
  border: 1px solid var(--color-border);
  border-radius: var(--radius-sm);
  padding: 8px 10px;
  color: var(--color-text);
  background: rgba(20, 26, 47, 0.45);
}

.grid {
  margin-top: var(--space-4);
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(260px, 1fr));
  gap: var(--space-3);
}

.card {
  padding: var(--space-4);
  display: grid;
  gap: var(--space-2);
}

.meta {
  color: var(--color-text-muted);
}

.text-btn {
  border: none;
  background: transparent;
  color: var(--color-accent);
  cursor: pointer;
  justify-self: start;
}

.add-btn {
  border: none;
  border-radius: 999px;
  background: linear-gradient(120deg, var(--color-accent), #5f7cff);
  color: #fff;
  padding: 8px 14px;
  cursor: pointer;
}

.empty {
  margin-top: var(--space-4);
  padding: var(--space-6);
}

@media (max-width: 980px) {
  .header,
  .filters {
    flex-direction: column;
  }
}
</style>
