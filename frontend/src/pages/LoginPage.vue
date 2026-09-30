<template>
  <q-card flat bordered>
    <q-card-section>
      <div class="text-h6">{{ $t('auth.signIn') }}</div>
    </q-card-section>
    <q-form @submit="submit">
      <q-card-section class="q-gutter-md">
        <q-input v-model="form.email" type="email" :label="$t('common.email')" outlined autofocus :rules="[(v) => !!v || $t('common.required')]" />
        <q-input v-model="form.password" :type="showPw ? 'text' : 'password'" :label="$t('common.password')" outlined :rules="[(v) => !!v || $t('common.required')]">
          <template #append>
            <q-icon :name="showPw ? 'visibility_off' : 'visibility'" class="cursor-pointer" @click="showPw = !showPw" />
          </template>
        </q-input>
        <q-banner v-if="error" dense class="bg-red-1 text-negative rounded-borders">{{ error }}</q-banner>
      </q-card-section>
      <q-card-actions class="q-px-md q-pb-md column q-gutter-sm">
        <q-btn type="submit" color="primary" :label="$t('auth.signIn')" class="full-width" :loading="loading" unelevated />
        <q-btn v-if="auth.settings.registration_enabled" flat color="primary" :label="$t('auth.createAccountLink')" class="full-width" :to="{ name: 'register' }" />
      </q-card-actions>
    </q-form>
  </q-card>
</template>

<script setup>
import { ref, reactive } from 'vue'
import { useRouter, useRoute } from 'vue-router'
import { useAuthStore } from '@/stores/auth'
import { errorMessage } from '@/utils/format'
import { useI18n } from 'vue-i18n'

const { t } = useI18n()
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
    error.value = errorMessage(e, t('auth.loginFailed'))
  } finally {
    loading.value = false
  }
}
</script>
