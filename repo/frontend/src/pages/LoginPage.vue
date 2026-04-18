<script setup>
import { onBeforeUnmount, ref } from 'vue'
import { useRouter } from 'vue-router'
import Button from '@/components/ui/Button.vue'
import Input from '@/components/ui/Input.vue'
import { useAuthStore } from '@/stores/authStore'

const router = useRouter()
const authStore = useAuthStore()

const username = ref('')
const password = ref('')
const lockedUntil = ref('')
const countdown = ref('')
const authError = ref('')
let timer = null

const clearTimer = () => {
  if (timer) {
    clearInterval(timer)
    timer = null
  }
}

const startCountdown = (lockIso) => {
  clearTimer()
  lockedUntil.value = lockIso

  const updateCountdown = () => {
    const target = new Date(lockIso).getTime()
    const now = Date.now()
    const diff = Math.max(0, target - now)
    const minutes = Math.floor(diff / 60000)
    const seconds = Math.floor((diff % 60000) / 1000)
    countdown.value = `${minutes}:${String(seconds).padStart(2, '0')}`

    if (diff <= 0) {
      clearTimer()
      lockedUntil.value = ''
      countdown.value = ''
    }
  }

  updateCountdown()
  timer = setInterval(updateCountdown, 1000)
}

const submitLogin = async () => {
  authError.value = ''
  const result = await authStore.login(username.value, password.value)

  if (result.success) {
    await router.push('/dashboard')
    return
  }

  authError.value = useAuthStore().error || ''

  if (result.error === 'account_locked' && result.lockedUntil) {
    startCountdown(result.lockedUntil)
  }
}

onBeforeUnmount(clearTimer)
</script>

<template>
  <div class="auth-layout">
    <Transition name="fade" appear>
      <section class="auth-card glass-card">
        <h1 class="auth-title">RoadLink</h1>
        <p class="auth-subtitle">Sign in to your mobility commerce workspace.</p>

        <form class="form-stack" @submit.prevent="submitLogin">
          <Input
            v-model="username"
            label="Username"
            icon="@"
            placeholder="Enter your username"
          />

          <Input
            v-model="password"
            type="password"
            label="Password"
            icon="*"
            placeholder="Enter your password"
          />

          <p v-if="authError" class="error-text">{{ authError }}</p>
          <p v-if="lockedUntil" class="helper-text">Account locked. Try again in {{ countdown }}</p>

          <Button type="submit" :loading="authStore.isLoading">
            Sign In
          </Button>
        </form>

        <p class="helper-text auth-footer">
          New to RoadLink?
          <RouterLink to="/register">Create an account</RouterLink>
        </p>
      </section>
    </Transition>
  </div>
</template>

<style scoped>
.error-text {
  margin: 0;
  color: var(--color-error);
  font-size: 0.9rem;
}

.auth-footer {
  margin-top: var(--space-5);
  text-align: center;
}

.auth-footer a {
  color: #8ca0ff;
  text-decoration: none;
}
</style>
