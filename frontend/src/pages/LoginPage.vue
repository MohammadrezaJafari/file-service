<template>
  <q-card flat bordered>
    <q-card-section>
      <div class="text-h6">Sign in</div>
    </q-card-section>
    <q-form @submit="submit">
      <q-card-section class="q-gutter-md">
        <q-input v-model="form.email" type="email" label="Email" outlined autofocus :rules="[(v) => !!v || 'Required']" />
        <q-input v-model="form.password" :type="showPw ? 'text' : 'password'" label="Password" outlined :rules="[(v) => !!v || 'Required']">
          <template #append>
            <q-icon :name="showPw ? 'visibility_off' : 'visibility'" class="cursor-pointer" @click="showPw = !showPw" />
          </template>
        </q-input>
        <q-banner v-if="error" dense class="bg-red-1 text-negative rounded-borders">{{ error }}</q-banner>
      </q-card-section>
      <q-card-actions class="q-px-md q-pb-md column q-gutter-sm">
        <q-btn type="submit" color="primary" label="Sign in" class="full-width" :loading="loading" unelevated />
        <q-btn v-if="auth.settings.registration_enabled" flat color="primary" label="Create an account" class="full-width" :to="{ name: 'register' }" />
      </q-card-actions>
    </q-form>
  </q-card>
</template>

<script setup>
import { ref, reactive } from 'vue'
import { useRouter, useRoute } from 'vue-router'
import { useAuthStore } from '@/stores/auth'
import { errorMessage } from '@/utils/format'

const auth = useAuthStore()
const router = useRouter()
const route = useRoute()
const form = reactive({ email: '', password: '' })
const showPw = ref(false)
const loading = ref(false)
const error = ref('')

async function submit() {
  loading.value = true
  error.value = ''
  try {
    await auth.login(form)
    router.push(route.query.redirect || { name: 'libraries' })
  } catch (e) {
    error.value = errorMessage(e, 'Login failed')
  } finally {
    loading.value = false
  }
}
</script>
