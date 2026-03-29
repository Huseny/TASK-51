<script setup>
import { computed, onMounted, ref } from 'vue'
import { useRouter } from 'vue-router'
import { Chart as ChartJS, ArcElement, CategoryScale, Legend, LineElement, LinearScale, PointElement, Tooltip } from 'chart.js'
import { Doughnut, Line } from 'vue-chartjs'
import AppShell from '@/components/layout/AppShell.vue'
import api from '@/services/api'
import { useAuthStore } from '@/stores/authStore'

ChartJS.register(CategoryScale, LinearScale, PointElement, LineElement, ArcElement, Tooltip, Legend)

const authStore = useAuthStore()
const router = useRouter()

const user = computed(() => authStore.user || { username: 'Guest', role: 'fleet_manager' })

const filters = ref({
  start_date: '',
  end_date: '',
  grouping: 'day',
})

const exportSettings = ref({
  format: 'csv',
  destination: 'default',
})

const trends = ref({ labels: [], datasets: [{ label: 'Rides', data: [] }] })
const distribution = ref({ labels: [], datasets: [{ label: 'Ride Status', data: [] }] })
const regions = ref([])
const templates = ref([])
const selectedTemplateId = ref('')
const templateName = ref('')
const error = ref('')
const isLoading = ref(false)
const exportLoadingType = ref('')

const totalRides = computed(() => trends.value.datasets?.[0]?.data?.reduce((sum, value) => sum + Number(value), 0) || 0)
const completedCount = computed(() => {
  const index = distribution.value.labels.findIndex((label) => label === 'completed')
  return index >= 0 ? Number(distribution.value.datasets[0].data[index]) : 0
})
const exceptionRate = computed(() => {
  const index = distribution.value.labels.findIndex((label) => label === 'exception')
  const exceptions = index >= 0 ? Number(distribution.value.datasets[0].data[index]) : 0
  if (!totalRides.value) {
    return '0.00'
  }
  return ((exceptions / totalRides.value) * 100).toFixed(2)
})

const fetchAll = async () => {
  error.value = ''
  isLoading.value = true

  try {
    const [trendsRes, distributionRes, regionsRes, templatesRes] = await Promise.all([
      api.get('/reports/trends', { params: filters.value }),
      api.get('/reports/distribution', { params: filters.value }),
      api.get('/reports/regions', { params: filters.value }),
      api.get('/reports/templates'),
    ])

    trends.value = trendsRes.data
    distribution.value = distributionRes.data
    regions.value = regionsRes.data.data || []
    templates.value = templatesRes.data.data || []
  } catch (err) {
    error.value = err.response?.data?.message || 'Could not load reports.'
    sessionStorage.setItem('roadlink_toast_message', error.value)
    sessionStorage.setItem('roadlink_toast_type', 'error')
  } finally {
    isLoading.value = false
  }
}

const exportReport = async (type) => {
  exportLoadingType.value = type

  try {
    const response = await api.post('/reports/export', {
      type,
      format: exportSettings.value.format,
      destination: exportSettings.value.destination,
      filters: filters.value,
    })

    window.open(response.data.url, '_blank', 'noopener')
  } catch (err) {
    const message = err.response?.data?.message || 'Export failed.'
    sessionStorage.setItem('roadlink_toast_message', message)
    sessionStorage.setItem('roadlink_toast_type', 'error')
  } finally {
    exportLoadingType.value = ''
  }
}

const saveTemplate = async () => {
  if (!templateName.value.trim()) {
    return
  }

  try {
    await api.post('/reports/templates', {
      name: templateName.value,
      config: filters.value,
    })

    templateName.value = ''
    await fetchAll()
  } catch (err) {
    const message = err.response?.data?.message || 'Could not save template.'
    sessionStorage.setItem('roadlink_toast_message', message)
    sessionStorage.setItem('roadlink_toast_type', 'error')
  }
}

const loadTemplate = () => {
  const template = templates.value.find((item) => String(item.id) === String(selectedTemplateId.value))
  if (!template) {
    return
  }

  filters.value = {
    ...filters.value,
    ...(template.config_json || {}),
  }

  fetchAll()
}

const handleLogout = async () => {
  await authStore.logout()
  await router.push('/login')
}

onMounted(fetchAll)
</script>

<template>
  <AppShell :user="user" @logout="handleLogout">
    <section class="header">
      <h1>Reports & Analytics</h1>
      <div class="filters">
        <label>Start <input v-model="filters.start_date" type="date"></label>
        <label>End <input v-model="filters.end_date" type="date"></label>
        <label>
          Grouping
          <select v-model="filters.grouping">
            <option value="day">Day</option>
            <option value="month">Month</option>
          </select>
        </label>
        <label>
          Export Format
          <select v-model="exportSettings.format">
            <option value="csv">CSV</option>
            <option value="xlsx">XLSX</option>
          </select>
        </label>
        <label>
          Export Destination
          <input v-model="exportSettings.destination" placeholder="default">
        </label>
        <button class="btn" :disabled="isLoading" @click="fetchAll">{{ isLoading ? 'Loading...' : 'Apply' }}</button>
      </div>
    </section>

    <p v-if="error" class="error">{{ error }}</p>

    <p v-if="isLoading" class="helper-text">Loading report data...</p>

    <section class="kpis">
      <article class="kpi glass-card"><strong>{{ totalRides }}</strong><span>Total Rides</span></article>
      <article class="kpi glass-card"><strong>{{ completedCount }}</strong><span>Total Completed</span></article>
      <article class="kpi glass-card"><strong>{{ exceptionRate }}%</strong><span>Exception Rate</span></article>
    </section>

    <section class="charts">
      <article class="glass-card panel">
        <div class="panel-head"><h2>Trends</h2><button class="link" :disabled="isLoading" @click="exportReport('trends')">{{ exportLoadingType === 'trends' ? `Exporting ${exportSettings.format.toUpperCase()}...` : `Export ${exportSettings.format.toUpperCase()}` }}</button></div>
        <Line :data="trends" />
      </article>

      <article class="glass-card panel">
        <div class="panel-head"><h2>Status Distribution</h2><button class="link" :disabled="isLoading" @click="exportReport('distribution')">{{ exportLoadingType === 'distribution' ? `Exporting ${exportSettings.format.toUpperCase()}...` : `Export ${exportSettings.format.toUpperCase()}` }}</button></div>
        <Doughnut :data="distribution" />
      </article>
    </section>

    <section class="glass-card panel">
      <div class="panel-head"><h2>Region Summary</h2><button class="link" :disabled="isLoading" @click="exportReport('regions')">{{ exportLoadingType === 'regions' ? `Exporting ${exportSettings.format.toUpperCase()}...` : `Export ${exportSettings.format.toUpperCase()}` }}</button></div>
      <table class="table">
        <thead><tr><th>Region</th><th>Total Rides</th></tr></thead>
        <tbody>
          <tr v-for="row in regions" :key="row.region"><td>{{ row.region }}</td><td>{{ row.total }}</td></tr>
        </tbody>
      </table>
    </section>

    <section class="glass-card panel templates">
      <h2>Templates</h2>
      <div class="template-actions">
        <input v-model="templateName" placeholder="Template name">
        <button class="btn" :disabled="isLoading" @click="saveTemplate">Save View</button>
      </div>
      <div class="template-actions">
        <select v-model="selectedTemplateId">
          <option value="">Select template</option>
          <option v-for="template in templates" :key="template.id" :value="String(template.id)">{{ template.name }}</option>
        </select>
        <button class="btn btn--ghost" :disabled="isLoading" @click="loadTemplate">Load</button>
      </div>
    </section>
  </AppShell>
</template>

<style scoped>
h1, h2 { margin: 0; }
.header { display: grid; gap: var(--space-3); }
.filters { display: flex; flex-wrap: wrap; gap: var(--space-2); align-items: end; }
label { display: grid; gap: 6px; color: var(--color-text-muted); }
input, select { border: 1px solid var(--color-border); border-radius: var(--radius-sm); padding: 8px 10px; background: rgba(20,26,47,0.45); color: var(--color-text); }
.btn { border: none; border-radius: 999px; padding: 8px 14px; cursor: pointer; color: #fff; background: linear-gradient(120deg, var(--color-accent), #5f7cff); }
.btn:hover:not(:disabled) { transform: translateY(-1px); }
.btn:disabled { opacity: 0.7; cursor: not-allowed; }
.btn--ghost { background: transparent; color: var(--color-text); border: 1px solid var(--color-border); }
.kpis { margin-top: var(--space-4); display: grid; grid-template-columns: repeat(3, minmax(0,1fr)); gap: var(--space-3); }
.kpi { padding: var(--space-4); display: grid; gap: 4px; }
.kpi strong { font-size: 1.6rem; }
.charts { margin-top: var(--space-3); display: grid; grid-template-columns: repeat(2, minmax(0,1fr)); gap: var(--space-3); }
.panel { margin-top: var(--space-3); padding: var(--space-4); display: grid; gap: var(--space-3); }
.panel-head { display: flex; justify-content: space-between; align-items: center; }
.link { border: none; background: transparent; color: var(--color-accent); cursor: pointer; }
.link:disabled { opacity: 0.6; cursor: not-allowed; }
.table { width: 100%; border-collapse: collapse; }
.table th, .table td { padding: 8px; border-bottom: 1px solid var(--color-border); text-align: left; }
.templates { margin-bottom: var(--space-4); }
.template-actions { display: flex; gap: var(--space-2); flex-wrap: wrap; }
.error { color: var(--color-error); margin-top: var(--space-2); }
@media (max-width: 980px) { .kpis, .charts { grid-template-columns: 1fr; } }
</style>
