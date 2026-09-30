<template>
  <q-card flat bordered>
    <q-card-section>
      <div class="text-h6">{{ $t('auth.createAccount') }}</div>
    </q-card-section>
    <q-form @submit="submit">
      <q-card-section class="q-gutter-md">
        <q-input v-model="form.name" :label="$t('common.name')" outlined autofocus :rules="[(v) => !!v || $t('common.required')]" />
        <q-input v-model="form.email" type="email" :label="$t('common.email')" outlined :rules="[(v) => !!v || $t('common.required')]" />
        <q-input v-model="form.password" type="password" :label="$t('common.password')" outlined :rules="[(v) => (v && v.length >= 8) || $t('auth.minChars')]" />
        <q-input v-model="form.password_confirmation" type="password" :label="$t('auth.confirmPassword')" outlined :rules="[(v) => v === form.password || $t('auth.mismatch')]" />
        <q-banner v-if="error" dense class="bg-red-1 text-negative rounded-borders">{{ error }}</q-banner>
      </q-card-section>
      <q-card-actions class="q-px-md q-pb-md column q-gutter-sm">
        <q-btn type="submit" color="primary" :label="$t('auth.createAccount')" class="full-width" :loading="loading" unelevated />
        <q-btn flat color="primary" :label="$t('auth.haveAccount')" class="full-width" :to="{ name: 'login' }" />
      </q-card-actions>
    </q-form>
  </q-card>
</template>

<script setup>
import { ref, reactive } from 'vue'
import { useRouter } from 'vue-router'
import { useAuthStore } from '@/stores/auth'
import { errorMessage } from '@/utils/format'
import { useI18n } from 'vue-i18n'

const { t } = useI18n()
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
    error.value = errorMessage(e, t('auth.registerFailed'))
  } finally {
    loading.value = false
  }
}
</script>
