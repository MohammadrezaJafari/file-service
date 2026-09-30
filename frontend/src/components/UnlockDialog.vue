<template>
  <q-dialog ref="dialogRef" persistent @hide="onDialogHide">
    <q-card class="fs-card" style="min-width: 400px">
      <q-form @submit="submit">
        <q-card-section>
          <div class="row items-center no-wrap">
            <q-avatar icon="lock" color="amber-2" text-color="amber-9" class="q-mr-md" />
            <div>
              <div class="text-h6">{{ $t('encrypted.title') }}</div>
              <div class="text-caption" style="color: var(--fs-text-muted)">{{ library.name }}</div>
            </div>
          </div>
        </q-card-section>
        <q-card-section class="q-pt-none">
          <div class="text-body2 q-mb-md">{{ $t('encrypted.hint') }}</div>
          <q-input v-model="password" type="password" outlined dense autofocus :label="$t('common.password')" :error="!!error" :error-message="error" />
        </q-card-section>
        <q-card-actions align="right">
          <q-btn flat :label="$t('common.cancel')" @click="onDialogCancel" />
          <q-btn type="submit" color="primary" unelevated icon="lock_open" :label="$t('encrypted.unlock')" :loading="loading" />
        </q-card-actions>
      </q-form>
    </q-card>
  </q-dialog>
</template>

<script setup>
import { ref } from 'vue'
import { useDialogPluginComponent, useQuasar } from 'quasar'
import { useI18n } from 'vue-i18n'
import { api } from '@/boot/axios'

const props = defineProps({ library: { type: Object, required: true } })
defineEmits([...useDialogPluginComponent.emits])
const { dialogRef, onDialogHide, onDialogOK, onDialogCancel } = useDialogPluginComponent()
const $q = useQuasar()
const { t } = useI18n()
const password = ref('')
const error = ref('')
const loading = ref(false)

async function submit() {
  loading.value = true
  error.value = ''
  try {
    const { data } = await api.post(`/libraries/${props.library.id}/unlock`, { password: password.value })
    $q.notify({ type: 'positive', message: t('encrypted.unlocked', { n: data.expires_in_minutes }) })
    onDialogOK()
  } catch {
    error.value = t('encrypted.wrong')
  } finally {
    loading.value = false
  }
}
</script>
