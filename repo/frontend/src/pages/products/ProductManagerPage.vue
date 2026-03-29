<script setup>
import { computed, onMounted, ref } from 'vue'
import { useRouter } from 'vue-router'
import AppShell from '@/components/layout/AppShell.vue'
import api from '@/services/api'
import { useAuthStore } from '@/stores/authStore'

const authStore = useAuthStore()
const router = useRouter()

const user = computed(() => authStore.user || { username: 'Guest', role: 'fleet_manager' })
const products = ref([])
const loading = ref(false)
const error = ref('')
const editingId = ref(null)

const form = ref(createDefaultForm())

function createDefaultForm() {
  return {
    name: '',
    description: '',
    category: '',
    tags: '',
    purchase_limit: 0,
    variants: [createVariant()],
  }
}

function createVariant() {
  return {
    id: null,
    sku: '',
    label: '',
    inventory_strategy: 'live_stock',
    stock_quantity: 0,
    presale_available_date: '',
    tiers: [createTier()],
  }
}

function createTier() {
  return {
    min_quantity: 1,
    max_quantity: '',
    unit_price: 0,
  }
}

const fetchProducts = async () => {
  const response = await api.get('/products')
  products.value = (response.data.data || []).filter((item) => item.seller_id === user.value.id || user.value.role === 'admin')
}

const resetForm = () => {
  editingId.value = null
  form.value = createDefaultForm()
}

const startEdit = (product) => {
  editingId.value = product.id
  form.value = {
    name: product.name,
    description: product.description || '',
    category: product.category,
    tags: Array.isArray(product.tags) ? product.tags.join(', ') : '',
    purchase_limit: product.purchase_limit_per_user_per_day ?? 0,
    variants: (product.variants || []).map((variant) => ({
      id: variant.id,
      sku: variant.sku,
      label: variant.label,
      inventory_strategy: variant.inventory_strategy,
      stock_quantity: variant.stock_quantity,
      presale_available_date: variant.presale_available_date || '',
      tiers: (variant.pricing_tiers || []).map((tier) => ({
        min_quantity: tier.min_quantity,
        max_quantity: tier.max_quantity || '',
        unit_price: Number(tier.unit_price),
      })),
    })),
  }
}

const payloadFromForm = () => {
  return {
    name: form.value.name,
    description: form.value.description,
    category: form.value.category,
    tags: form.value.tags
      .split(',')
      .map((item) => item.trim())
      .filter(Boolean),
    purchase_limit: Number(form.value.purchase_limit) || 0,
    variants: form.value.variants.map((variant) => ({
      ...(variant.id ? { id: variant.id } : {}),
      sku: variant.sku,
      label: variant.label,
      inventory_strategy: variant.inventory_strategy,
      stock_quantity: Number(variant.stock_quantity) || 0,
      presale_available_date: variant.presale_available_date || null,
      tiers: variant.tiers.map((tier) => ({
        min_quantity: Number(tier.min_quantity) || 1,
        max_quantity: tier.max_quantity === '' ? null : Number(tier.max_quantity),
        unit_price: Number(tier.unit_price),
      })),
    })),
  }
}

const submit = async () => {
  loading.value = true
  error.value = ''

  try {
    if (editingId.value) {
      await api.put(`/products/${editingId.value}`, payloadFromForm())
    } else {
      await api.post('/products', payloadFromForm())
    }
    resetForm()
    await fetchProducts()
  } catch (err) {
    error.value = err.response?.data?.message || 'Could not save product.'
  } finally {
    loading.value = false
  }
}

const togglePublish = async (product) => {
  await api.patch(`/products/${product.id}/publish`, {
    is_published: !product.is_published,
  })
  await fetchProducts()
}

const removeProduct = async (product) => {
  await api.delete(`/products/${product.id}`)
  if (editingId.value === product.id) {
    resetForm()
  }
  await fetchProducts()
}

const addVariant = () => {
  form.value.variants.push(createVariant())
}

const removeVariant = (index) => {
  form.value.variants.splice(index, 1)
}

const addTier = (variantIndex) => {
  form.value.variants[variantIndex].tiers.push(createTier())
}

const removeTier = (variantIndex, tierIndex) => {
  form.value.variants[variantIndex].tiers.splice(tierIndex, 1)
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
        <h1>Product Catalog Manager</h1>
        <p class="helper-text">Create and maintain multi-variant products with tiered pricing.</p>
      </div>
    </section>

    <section class="layout">
      <article class="glass-card panel">
        <h2>{{ editingId ? 'Edit Product' : 'Create Product' }}</h2>
        <form class="form" @submit.prevent="submit">
          <label>Name <input v-model="form.name" required></label>
          <label>Description <textarea v-model="form.description" rows="3" /></label>
          <label>Category <input v-model="form.category" required></label>
          <label>Tags (comma separated) <input v-model="form.tags"></label>
          <label>Daily Purchase Limit <input v-model.number="form.purchase_limit" type="number" min="0"></label>

          <section v-for="(variant, variantIndex) in form.variants" :key="variantIndex" class="variant-block">
            <div class="variant-head">
              <h3>Variant {{ variantIndex + 1 }}</h3>
              <button v-if="form.variants.length > 1" type="button" class="text-btn" @click="removeVariant(variantIndex)">
                Remove Variant
              </button>
            </div>

            <label>SKU <input v-model="variant.sku" required></label>
            <label>Label <input v-model="variant.label" required></label>
            <label>
              Inventory Strategy
              <select v-model="variant.inventory_strategy">
                <option value="live_stock">Live Stock</option>
                <option value="shared">Shared</option>
                <option value="presale">Presale</option>
              </select>
            </label>
            <label>Stock Quantity <input v-model.number="variant.stock_quantity" type="number" min="0"></label>
            <label v-if="variant.inventory_strategy === 'presale'">
              Presale Available Date
              <input v-model="variant.presale_available_date" type="date">
            </label>

            <section class="tiers">
              <h4>Pricing Tiers</h4>
              <div v-for="(tier, tierIndex) in variant.tiers" :key="tierIndex" class="tier-row">
                <input v-model.number="tier.min_quantity" type="number" min="1" placeholder="Min">
                <input v-model="tier.max_quantity" type="number" min="1" placeholder="Max (blank = no max)">
                <input v-model.number="tier.unit_price" type="number" min="0.01" step="0.01" placeholder="Unit Price">
                <button v-if="variant.tiers.length > 1" type="button" class="text-btn" @click="removeTier(variantIndex, tierIndex)">
                  Remove
                </button>
              </div>
              <button type="button" class="text-btn" @click="addTier(variantIndex)">+ Add Tier</button>
            </section>
          </section>

          <button type="button" class="text-btn" @click="addVariant">+ Add Variant</button>
          <p v-if="error" class="error">{{ error }}</p>
          <div class="actions">
            <button class="add-btn" :disabled="loading" type="submit">{{ editingId ? 'Update Product' : 'Create Product' }}</button>
            <button v-if="editingId" class="ghost-btn" type="button" @click="resetForm">Cancel</button>
          </div>
        </form>
      </article>

      <article class="glass-card panel">
        <h2>Your Products</h2>
        <div v-if="products.length" class="products">
          <div v-for="product in products" :key="product.id" class="product-row">
            <div>
              <strong>{{ product.name }}</strong>
              <p>{{ product.category }} · {{ product.variants?.length || 0 }} variants</p>
            </div>
            <div class="row-actions">
              <button class="text-btn" @click="startEdit(product)">Edit</button>
              <button class="text-btn" @click="togglePublish(product)">{{ product.is_published ? 'Unpublish' : 'Publish' }}</button>
              <button class="text-btn danger" @click="removeProduct(product)">Delete</button>
            </div>
          </div>
        </div>
        <p v-else class="helper-text">No products yet.</p>
      </article>
    </section>
  </AppShell>
</template>

<style scoped>
h1, h2, h3, h4, p {
  margin: 0;
}

.header {
  margin-bottom: var(--space-4);
}

.layout {
  display: grid;
  grid-template-columns: minmax(0, 1.2fr) minmax(0, 1fr);
  gap: var(--space-3);
}

.panel {
  padding: var(--space-4);
  display: grid;
  gap: var(--space-3);
}

.form {
  display: grid;
  gap: var(--space-2);
}

label {
  display: grid;
  gap: 6px;
  color: var(--color-text-muted);
  font-size: 0.9rem;
}

input,
textarea,
select {
  border: 1px solid var(--color-border);
  border-radius: var(--radius-sm);
  padding: 9px 10px;
  color: var(--color-text);
  background: rgba(20, 26, 47, 0.45);
}

.variant-block {
  border: 1px solid var(--color-border);
  border-radius: var(--radius-md);
  padding: var(--space-3);
  display: grid;
  gap: var(--space-2);
}

.variant-head {
  display: flex;
  justify-content: space-between;
  align-items: center;
}

.tiers {
  display: grid;
  gap: var(--space-2);
}

.tier-row {
  display: grid;
  grid-template-columns: repeat(3, minmax(0, 1fr)) auto;
  gap: var(--space-2);
}

.products {
  display: grid;
  gap: var(--space-2);
}

.product-row {
  display: flex;
  justify-content: space-between;
  align-items: center;
  gap: var(--space-2);
  border: 1px solid var(--color-border);
  border-radius: var(--radius-sm);
  padding: 10px;
}

.row-actions {
  display: flex;
  gap: 8px;
}

.text-btn {
  border: none;
  background: transparent;
  color: var(--color-accent);
  cursor: pointer;
}

.danger {
  color: var(--color-error);
}

.add-btn,
.ghost-btn {
  border: none;
  border-radius: 999px;
  padding: 8px 14px;
  cursor: pointer;
}

.add-btn {
  background: linear-gradient(120deg, var(--color-accent), #5f7cff);
  color: #fff;
}

.ghost-btn {
  background: transparent;
  color: var(--color-text);
  border: 1px solid var(--color-border);
}

.actions {
  display: flex;
  gap: var(--space-2);
}

.error {
  color: var(--color-error);
}

@media (max-width: 1100px) {
  .layout {
    grid-template-columns: 1fr;
  }

  .tier-row {
    grid-template-columns: 1fr;
  }
}
</style>
