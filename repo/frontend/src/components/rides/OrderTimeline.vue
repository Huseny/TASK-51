<script setup>
import { computed } from 'vue'
import { formatReassignmentReason, isReassignmentEvent } from '@/utils/rideReassignment'

const props = defineProps({
  logs: {
    type: Array,
    default: () => [],
  },
  currentStatus: {
    type: String,
    default: '',
  },
})

const sortedLogs = computed(() => [...props.logs].sort((a, b) => new Date(a.created_at) - new Date(b.created_at)))

const formatRelative = (dateValue) => {
  const seconds = Math.floor((Date.now() - new Date(dateValue).getTime()) / 1000)

  if (seconds < 60) {
    return `${seconds}s ago`
  }

  if (seconds < 3600) {
    return `${Math.floor(seconds / 60)}m ago`
  }

  if (seconds < 86400) {
    return `${Math.floor(seconds / 3600)}h ago`
  }

  return `${Math.floor(seconds / 86400)}d ago`
}

const formatDetail = (entry) => {
  if (isReassignmentEvent(entry)) {
    const reason = entry.metadata?.reassignment_reason || entry.trigger_reason

    return `Driver reassigned · ${formatReassignmentReason(reason)} · by ${entry.triggered_by}`
  }

  return `${entry.trigger_reason || 'status_update'} · by ${entry.triggered_by}`
}
</script>

<template>
  <section class="timeline">
    <article v-for="entry in sortedLogs" :key="entry.id" class="timeline__item">
      <span class="timeline__dot" :class="{ 'timeline__dot--current': entry.to_status === currentStatus }" />
      <div class="timeline__content">
        <div class="timeline__heading">
          <strong>{{ entry.from_status }} → {{ entry.to_status }}</strong>
          <span :title="new Date(entry.created_at).toLocaleString()">{{ formatRelative(entry.created_at) }}</span>
        </div>
        <p class="timeline__detail">{{ formatDetail(entry) }}</p>
      </div>
    </article>
  </section>
</template>

<style scoped>
.timeline {
  display: grid;
  gap: var(--space-3);
}

.timeline__item {
  display: grid;
  grid-template-columns: 14px 1fr;
  gap: var(--space-3);
}

.timeline__dot {
  margin-top: 7px;
  width: 12px;
  height: 12px;
  border-radius: 50%;
  border: 2px solid rgba(151, 164, 208, 0.7);
  background: transparent;
}

.timeline__dot--current {
  border-color: rgba(67, 97, 238, 0.95);
  background: rgba(67, 97, 238, 0.95);
  box-shadow: 0 0 0 6px rgba(67, 97, 238, 0.2);
}

.timeline__heading {
  display: flex;
  justify-content: space-between;
  gap: var(--space-3);
}

.timeline__heading span,
.timeline__detail {
  color: var(--color-text-muted);
  font-size: 0.86rem;
}

.timeline__detail {
  margin: var(--space-1) 0 0;
}
</style>
