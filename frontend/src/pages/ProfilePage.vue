<template>
  <q-page padding>
    <div class="page-title q-mb-md">Profile & settings</div>
    <div class="row q-col-gutter-lg">
      <div class="col-12 col-md-6">
        <q-card flat bordered>
          <q-card-section class="text-subtitle1">Profile</q-card-section>
          <q-form @submit="saveProfile">
            <q-card-section class="q-gutter-md">
              <q-input v-model="profile.name" label="Name" outlined :rules="[(v) => !!v || 'Required']" />
              <q-input v-model="profile.email" label="Email" type="email" outlined :rules="[(v) => !!v || 'Required']" />
            </q-card-section>
            <q-card-actions align="right"><q-btn type="submit" color="primary" unelevated label="Save" :loading="saving" /></q-card-actions>
          </q-form>
        </q-card>

        <q-card flat bordered class="q-mt-lg">
          <q-card-section class="text-subtitle1">Change password</q-card-section>
          <q-form @submit="savePassword">
            <q-card-section class="q-gutter-md">
              <q-input v-model="pw.current_password" label="Current password" type="password" outlined :rules="[(v) => !!v || 'Required']" />
              <q-input v-model="pw.password" label="New password" type="password" outlined :rules="[(v) => (v && v.length >= 8) || 'At least 8 characters']" />
              <q-input v-model="pw.password_confirmation" label="Confirm new password" type="password" outlined :rules="[(v) => v === pw.password || 'Passwords do not match']" />
            </q-card-section>
            <q-card-actions align="right"><q-btn type="submit" color="primary" unelevated label="Update password" :loading="savingPw" /></q-card-actions>
          </q-form>
        </q-card>
      </div>
      <div class="col-12 col-md-6">
        <q-card flat bordered>
          <q-card-section class="text-subtitle1">Storage</q-card-section>
          <q-card-section>
            <div class="text-h5">{{ formatBytes(auth.user?.used_bytes) }}</div>
            <div class="text-caption text-grey-7">used<span v-if="auth.user?.quota_bytes"> of {{ formatBytes(auth.user.quota_bytes) }} quota</span><span v-else> · unlimited quota</span></div>
            <q-linear-progress v-if="auth.user?.quota_bytes" :value="auth.usagePercent / 100" class="q-mt-sm" rounded size="10px" :color="auth.usagePercent > 90 ? 'negative' : 'primary'" />
          </q-card-section>
          <q-separator />
          <q-card-section class="text-caption text-grey-7">
            Member since {{ formatDate(auth.user?.created_at) }}<br />
            Last login {{ formatDate(auth.user?.last_login_at) }}
          </q-card-section>
        </q-card>
      </div>
    </div>
  </q-page>
</template>

<script setup>
import { ref, reactive } from 'vue'
import { useQuasar } from 'quasar'
import { api } from '@/boot/axios'
import { useAuthStore } from '@/stores/auth'
import { formatBytes, formatDate, errorMessage } from '@/utils/format'

const $q = useQuasar()
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
    $q.notify({ type: 'positive', message: 'Profile saved' })
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
    $q.notify({ type: 'positive', message: 'Password updated' })
  } catch (e) {
    $q.notify({ type: 'negative', message: errorMessage(e) })
  } finally {
    savingPw.value = false
  }
}
</script>
