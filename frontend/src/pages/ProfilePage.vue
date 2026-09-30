<template>
  <q-page padding>
    <div class="page-title q-mb-md">{{ $t('profile.title') }}</div>
    <div class="row q-col-gutter-lg">
      <div class="col-12 col-md-6">
        <q-card flat bordered>
          <q-card-section class="text-subtitle1">{{ $t('profile.profile') }}</q-card-section>
          <q-form @submit="saveProfile">
            <q-card-section class="q-gutter-md">
              <q-input v-model="profile.name" :label="$t('common.name')" outlined :rules="[(v) => !!v || $t('common.required')]" />
              <q-input v-model="profile.email" :label="$t('common.email')" type="email" outlined :rules="[(v) => !!v || $t('common.required')]" />
            </q-card-section>
            <q-card-actions align="right"><q-btn type="submit" color="primary" unelevated :label="$t('common.save')" :loading="saving" /></q-card-actions>
          </q-form>
        </q-card>

        <q-card flat bordered class="q-mt-lg">
          <q-card-section class="text-subtitle1">{{ $t('profile.changePassword') }}</q-card-section>
          <q-form @submit="savePassword">
            <q-card-section class="q-gutter-md">
              <q-input v-model="pw.current_password" :label="$t('profile.currentPassword')" type="password" outlined :rules="[(v) => !!v || $t('common.required')]" />
              <q-input v-model="pw.password" :label="$t('profile.newPassword')" type="password" outlined :rules="[(v) => (v && v.length >= 8) || $t('auth.minChars')]" />
              <q-input v-model="pw.password_confirmation" :label="$t('profile.confirmNew')" type="password" outlined :rules="[(v) => v === pw.password || $t('auth.mismatch')]" />
            </q-card-section>
            <q-card-actions align="right"><q-btn type="submit" color="primary" unelevated :label="$t('profile.updatePassword')" :loading="savingPw" /></q-card-actions>
          </q-form>
        </q-card>
      </div>
      <div class="col-12 col-md-6">
        <q-card flat bordered>
          <q-card-section class="text-subtitle1">{{ $t('profile.storage') }}</q-card-section>
          <q-card-section>
            <div class="text-h5">{{ formatBytes(auth.user?.used_bytes) }}</div>
            <div class="text-caption text-grey-7">{{ auth.user?.quota_bytes ? $t('profile.usedOf', { quota: formatBytes(auth.user.quota_bytes) }) : $t('profile.unlimited') }}</div>
            <q-linear-progress v-if="auth.user?.quota_bytes" :value="auth.usagePercent / 100" class="q-mt-sm" rounded size="10px" :color="auth.usagePercent > 90 ? 'negative' : 'primary'" />
          </q-card-section>
          <q-separator />
          <q-card-section class="text-caption text-grey-7">
            {{ $t('profile.memberSince', { date: formatDate(auth.user?.created_at) }) }}<br />
            {{ $t('profile.lastLogin', { date: formatDate(auth.user?.last_login_at) }) }}
          </q-card-section>
        </q-card>
      </div>
    </div>
  </q-page>
</template>

<script setup>
import { useI18n } from 'vue-i18n'
import { ref, reactive } from 'vue'
import { useQuasar } from 'quasar'
import { api } from '@/boot/axios'
import { useAuthStore } from '@/stores/auth'
import { formatBytes, formatDate, errorMessage } from '@/utils/format'

const $q = useQuasar()
const { t } = useI18n()
const auth = useAuthStore()
const profile = reactive({ name: auth.user?.name || '', email: auth.user?.email || '' })
const pw = reactive({ current_password: '', password: '', password_confirmation: '' })
const saving = ref(false)
const savingPw = ref(false)

async function saveProfile() {
  saving.value = true
  try {
    const { data } = await api.post('/auth/me', profile)
    auth.user = data
    $q.notify({ type: 'positive', message: t('profile.saved') })
  } catch (e) {
    $q.notify({ type: 'negative', message: errorMessage(e) })
  } finally {
    saving.value = false
  }
}
async function savePassword() {
  savingPw.value = true
  try {
    await api.put('/auth/password', pw)
    Object.assign(pw, { current_password: '', password: '', password_confirmation: '' })
    $q.notify({ type: 'positive', message: t('profile.passwordUpdated') })
  } catch (e) {
    $q.notify({ type: 'negative', message: errorMessage(e) })
  } finally {
    savingPw.value = false
  }
}
</script>
