<template>
  <q-card flat bordered>
    <q-card-section>
      <div class="text-h6">Create account</div>
    </q-card-section>
    <q-form @submit="submit">
      <q-card-section class="q-gutter-md">
        <q-input v-model="form.name" label="Name" outlined autofocus :rules="[(v) => !!v || 'Required']" />
        <q-input v-model="form.email" type="email" label="Email" outlined :rules="[(v) => !!v || 'Required']" />
        <q-input v-model="form.password" type="password" label="Password" outlined :rules="[(v) => (v && v.length >= 8) || 'At least 8 characters']" />
        <q-input v-model="form.password_confirmation" type="password" label="Confirm password" outlined :rules="[(v) => v === form.password || 'Passwords do not match']" />
        <q-banner v-if="error" dense class="bg-red-1 text-negative rounded-borders">{{ error }}</q-banner>
      </q-card-section>
      <q-card-actions class="q-px-md q-pb-md column q-gutter-sm">
        <q-btn type="submit" color="primary" label="Create account" class="full-width" :loading="loading" unelevated />
        <q-btn flat color="primary" label="Already have an account? Sign in" class="full-width" :to="{ name: 'login' }" />
      </q-card-actions>
    </q-form>
  </q-card>
</template>

<script setup>
import { ref, reactive } from 'vue'
import { useRouter } from 'vue-router'
import { useAuthStore } from '@/stores/auth'
import { errorMessage } from '@/utils/format'

const auth = useAuthStore()
const router = useRouter()
const form = reactive({ name: '', email: '', password: '', password_confirmation: '' })
const loading = ref(false)
const error = ref('')

async function submit() {
  loading.value = true
  error.value = ''
  try {
    await auth.register(form)
    router.push({ name: 'libraries' })
  } catch (e) {
    error.value = errorMessage(e, 'Registration failed')
  } finally {
    loading.value = false
  }
}
</script>
