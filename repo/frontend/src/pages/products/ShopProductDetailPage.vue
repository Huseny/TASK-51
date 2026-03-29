<script setup>
import { computed, onMounted, ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import AppShell from '@/components/layout/AppShell.vue'
import api from '@/services/api'
import { useAuthStore } from '@/stores/authStore'

const authStore = useAuthStore()
const route = useRoute()
const router = useRouter()

const user = computed(() => authStore.user || { username: 'Guest', role: 'rider' })
const product = ref(null)
const selectedVariantId = ref(null)
const quantity = ref(1)
const loading = ref(false)
const feedback = ref('')

const selectedVariant = computed(() => {
  if (!product.value) {
    return null
  }
  return (product.value.variants || []).find((item) => item.id === Number(selectedVariantId.value)) || null
})

const estimatedTotal = computed(() => {
  if (!selectedVariant.value) {
    return null
  }

  const tiers = [...(selectedVariant.value.pricing_tiers || [])].sort((a, b) => a.min_quantity - b.min_quantity)
  const tier = tiers.find((item) => {
    const inMinRange = quantity.value >= item.min_quantity
    const inMaxRange = item.max_quantity == null || quantity.value <= item.max_quantity
    return inMinRange && inMaxRange
  })

  if (!tier) {
    return null
  }

  return (Number(tier.unit_price) * quantity.value).toFixed(2)
})

const fetchProduct = async () => {
  const response = await api.get(`/products/${route.params.id}`)
  product.value = response.data.product
  selectedVariantId.value = product.value.variants?.[0]?.id || null

  try {
    await api.post('/interactions', {
      item_id: Number(product.value.id),
      interaction_type: 'view',
    })
  } catch {
  }
}

const purchase = async () => {
  if (!selectedVariantId.value) {
    return
  }

  loading.value = true
  feedback.value = ''
  try {
    await api.post(`/products/${product.value.id}/purchase`, {
      variant_id: Number(selectedVariantId.value),
      quantity: Number(quantity.value),
    })
    feedback.value = 'Purchase successful.'
  } catch (err) {
    feedback.value = err.response?.data?.message || 'Purchase failed.'
  } finally {
    loading.value = false
  }
}

const handleLogout = async () => {
  await authStore.logout()
  await router.push('/login')
}

onMounted(fetchProduct)
</script>

<template>
  <AppShell :user="user" @logout="handleLogout">
    <section v-if="product" class="detail">
      <article class="glass-card panel">
        <button class="text-btn" @click="router.push('/shop/products')">← Back to Shop</button>
        <h1>{{ product.name }}</h1>
        <p>{{ product.description || 'No description provided.' }}</p>
        <p class="meta">{{ product.category }}</p>
      </article>

      <article class="glass-card panel">
        <h2>Configure Purchase</h2>
        <label>
          Variant
          <select v-model="selectedVariantId">
            <option v-for="variant in product.variants" :key="variant.id" :value="variant.id">
              {{ variant.label }} ({{ variant.sku }})
            </option>
          </select>
        </label>

        <label>
          Quantity
          <input v-model.number="quantity" type="number" min="1">
        </label>

        <div v-if="selectedVariant" class="tiers">
          <h3>Pricing Tiers</h3>
          <p v-for="tier in selectedVariant.pricing_tiers" :key="tier.id" class="tier-item">
            {{ tier.min_quantity }} - {{ tier.max_quantity || 'up' }} units: ${{ tier.unit_price }} each
          </p>
        </div>

        <p class="total">Estimated Total: <strong>${{ estimatedTotal || 'N/A' }}</strong></p>
        <button class="add-btn" :disabled="loading || !estimatedTotal" @click="purchase">Purchase</button>
        <p v-if="feedback" class="feedback">{{ feedback }}</p>
      </article>
    </section>
  </AppShell>
</template>

<style scoped>
h1, h2, h3, p {
  margin: 0;
}

.detail {
  display: grid;
  gap: var(--space-3);
}

.panel {
  padding: var(--space-4);
  display: grid;
  gap: var(--space-2);
}

label {
  display: grid;
  gap: 6px;
  color: var(--color-text-muted);
}

input,
select {
  border: 1px solid var(--color-border);
  border-radius: var(--radius-sm);
  padding: 9px 10px;
  color: var(--color-text);
  background: rgba(20, 26, 47, 0.45);
}

.tiers {
  margin-top: var(--space-2);
  display: grid;
  gap: 6px;
}

.tier-item {
  color: var(--color-text-muted);
}

.total {
  margin-top: var(--space-2);
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
  justify-self: start;
}

.feedback {
  color: var(--color-text-muted);
}

.meta {
  color: var(--color-text-muted);
}
</style>
