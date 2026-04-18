<script setup>
import { computed, ref } from 'vue'
import { useRouter } from 'vue-router'
import Button from '@/components/ui/Button.vue'
import Input from '@/components/ui/Input.vue'
import { useAuthStore } from '@/stores/authStore'

const authStore = useAuthStore()
const router = useRouter()

const form = ref({
  username: '',
  password: '',
  password_confirmation: '',
  role: 'rider',
})

const authError = ref('')

const checks = computed(() => ({
  minLength: form.value.password.length >= 10,
  hasLetter: /[A-Za-z]/.test(form.value.password),
  hasNumber: /\d/.test(form.value.password),
}))

const submitRegister = async () => {
  authError.value = ''
  const result = await authStore.register(form.value)

  if (result.success) {
    await router.push('/dashboard')
    return
  }

  authError.value = useAuthStore().error || ''
}
</script>

<template>
  <div class="auth-layout">
    <Transition name="fade" appear>
      <section class="auth-card glass-card">
        <h1 class="auth-title">Create your RoadLink account</h1>
        <p class="auth-subtitle">Role-based workspaces start here.</p>

        <form class="form-stack" @submit.prevent="submitRegister">
          <Input
            v-model="form.username"
            label="Username"
            icon="@"
            placeholder="3-50 characters"
          />

          <Input
            v-model="form.password"
            type="password"
            label="Password"
            icon="*"
            placeholder="At least 10 characters"
          />

          <Input
            v-model="form.password_confirmation"
            type="password"
            label="Confirm Password"
            icon="*"
            placeholder="Retype your password"
          />

          <label class="role-field">
            <span class="helper-text">Role</span>
            <select v-model="form.role">
              <option value="rider">Rider</option>
              <option value="driver">Driver</option>
              <option value="fleet_manager">Fleet Manager</option>
            </select>
          </label>

          <ul class="password-checks">
            <li :class="{ met: checks.minLength }">{{ checks.minLength ? '✓' : '•' }} 10+ characters</li>
            <li :class="{ met: checks.hasLetter }">{{ checks.hasLetter ? '✓' : '•' }} Contains a letter</li>
            <li :class="{ met: checks.hasNumber }">{{ checks.hasNumber ? '✓' : '•' }} Contains a number</li>
          </ul>

          <p v-if="authError" class="error-text">{{ authError }}</p>

          <Button type="submit" :loading="authStore.isLoading">
            Create Account
          </Button>
        </form>

        <p class="helper-text auth-footer">
          Already registered?
          <RouterLink to="/login">Sign in</RouterLink>
        </p>
      </section>
    </Transition>
  </div>
</template>

<style scoped>
.role-field {
  display: grid;
  gap: var(--space-2);
}

select {
  border: 1px solid var(--color-border);
  border-radius: var(--radius-md);
  background: rgba(20, 26, 47, 0.45);
  color: var(--color-text);
  padding: 12px;
}

.password-checks {
  margin: 0;
  padding: 0;
  list-style: none;
  display: grid;
  gap: var(--space-1);
  font-size: 0.9rem;
  color: var(--color-text-muted);
}

.password-checks .met {
  color: var(--color-success);
}

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
