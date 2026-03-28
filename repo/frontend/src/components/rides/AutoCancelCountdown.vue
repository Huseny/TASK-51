<script setup>
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue'

const props = defineProps({
  seconds: {
    type: Number,
    default: null,
  },
})

const remaining = ref(props.seconds)
let timerId = null

const formatted = computed(() => {
  if (remaining.value === null || remaining.value <= 0) {
    return '0:00'
  }

  const mins = Math.floor(remaining.value / 60)
  const secs = remaining.value % 60
  return `${mins}:${String(secs).padStart(2, '0')}`
})

const stop = () => {
  if (timerId) {
    clearInterval(timerId)
    timerId = null
  }
}

const start = () => {
  stop()
  if (remaining.value === null) {
    return
  }

  timerId = setInterval(() => {
    if (remaining.value === null) {
      stop()
      return
    }

    remaining.value = Math.max(0, remaining.value - 1)
  }, 1000)
}

watch(() => props.seconds, (value) => {
  remaining.value = value
  start()
})

onMounted(start)
onBeforeUnmount(stop)
</script>

<template>
  <div v-if="remaining !== null" class="countdown">
    Auto-cancel in {{ formatted }}
  </div>
</template>

<style scoped>
.countdown {
  border: 1px solid rgba(255, 209, 102, 0.45);
  background: rgba(255, 209, 102, 0.12);
  border-radius: var(--radius-md);
  color: #ffe0a1;
  padding: 10px 14px;
  font-weight: 600;
}
</style>
