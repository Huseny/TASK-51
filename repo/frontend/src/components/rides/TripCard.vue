<script setup>
import { computed } from 'vue'

const props = defineProps({
  order: {
    type: Object,
    required: true,
  },
})

const statusTone = computed(() => {
  const map = {
    matching: 'matching',
    accepted: 'accepted',
    in_progress: 'in_progress',
    completed: 'completed',
    canceled: 'canceled',
    exception: 'exception',
    created: 'matching',
  }

  return map[props.order.status] || 'matching'
})

const prettyStatus = computed(() => props.order.status.replace('_', ' '))
const notesPreview = computed(() => (props.order.notes || '').trim())
</script>

<template>
  <article class="trip-card glass-card">
    <div class="trip-card__top">
      <h3 class="trip-card__route">{{ order.origin_address }} <span>→</span> {{ order.destination_address }}</h3>
      <span class="trip-card__status" :class="`trip-card__status--${statusTone}`">{{ prettyStatus }}</span>
    </div>

    <div class="trip-card__meta">
      <span class="trip-card__riders">👤 x {{ order.rider_count }}</span>
      <span>{{ new Date(order.time_window_start).toLocaleString() }} - {{ new Date(order.time_window_end).toLocaleTimeString() }}</span>
    </div>

    <p v-if="notesPreview" class="trip-card__notes">{{ notesPreview }}</p>
  </article>
</template>

<style scoped>
.trip-card {
  padding: var(--space-4);
  cursor: pointer;
  transition: transform var(--transition-fast), border-color var(--transition-fast);
}

.trip-card:hover {
  transform: translateY(-2px);
  border-color: rgba(112, 141, 255, 0.45);
}

.trip-card__top {
  display: flex;
  justify-content: space-between;
  gap: var(--space-2);
}

.trip-card__route {
  margin: 0;
  font-size: 1rem;
}

.trip-card__route span {
  color: var(--color-accent);
}

.trip-card__meta {
  margin-top: var(--space-3);
  display: flex;
  justify-content: space-between;
  gap: var(--space-2);
  font-size: 0.88rem;
  color: var(--color-text-muted);
}

.trip-card__riders {
  font-weight: 600;
}

.trip-card__notes {
  margin: var(--space-2) 0 0;
  color: var(--color-text-muted);
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}

.trip-card__status {
  display: inline-flex;
  border-radius: 999px;
  padding: 2px 10px;
  font-size: 0.75rem;
  text-transform: capitalize;
  align-items: center;
}

.trip-card__status--matching {
  background: rgba(255, 209, 102, 0.2);
  color: #ffe8a8;
  animation: pulse 1.8s infinite;
}

.trip-card__status--accepted {
  background: rgba(67, 97, 238, 0.24);
  color: #dfe6ff;
}

.trip-card__status--in_progress {
  background: rgba(85, 102, 255, 0.24);
  color: #d9ddff;
  animation: pulse 1.4s infinite;
}

.trip-card__status--completed {
  background: rgba(6, 214, 160, 0.2);
  color: #cbfceb;
}

.trip-card__status--canceled {
  background: rgba(239, 71, 111, 0.2);
  color: #ffd8e0;
}

.trip-card__status--exception {
  background: rgba(255, 167, 38, 0.24);
  color: #ffe1b8;
}

@keyframes pulse {
  0%,
  100% { opacity: 1; }
  50% { opacity: 0.65; }
}
</style>
