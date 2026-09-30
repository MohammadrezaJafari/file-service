<template>
  <q-dialog ref="dialogRef" @hide="onDialogHide">
    <q-card class="fs-card" style="min-width: 480px; max-width: 95vw">
      <q-card-section>
        <div class="text-h6">{{ kind === 'upload' ? $t('link.uploadTitle') : $t('link.title') }}</div>
        <div class="text-caption text-grey-7 ellipsis">{{ node ? node.name : library.name }}</div>
      </q-card-section>

      <q-card-section v-if="!created" class="q-gutter-md">
        <q-btn-toggle v-if="!node || node.type === 'folder'" v-model="kind" spread unelevated toggle-color="primary" :options="[{ label: $t('link.downloadLink'), value: 'download' }, { label: $t('link.uploadLink'), value: 'upload' }]" />
        <q-input v-model="password" outlined dense :label="$t('link.passwordOpt')" type="password" :hint="$t('link.minPw')" />
        <q-input v-model.number="expires" outlined dense type="number" :label="$t('link.expires')" min="1" />
        <q-toggle v-if="kind === 'download'" v-model="allowDownload" :label="$t('link.allowDownload')" />
      </q-card-section>

      <q-card-section v-else class="q-gutter-sm">
        <q-input :model-value="created.url" outlined dense readonly>
          <template #append>
            <q-btn flat round dense icon="content_copy" @click="copy(created.url)" />
          </template>
        </q-input>
        <div class="text-caption text-grey-7">
          <span v-if="created.has_password">{{ $t('link.protected') }} · </span>
          <span v-if="created.expires_at">{{ $t('link.expiresAt', { date: formatDate(created.expires_at) }) }}</span>
          <span v-else>{{ $t('link.neverExpires') }}</span>
        </div>
      </q-card-section>

      <q-card-actions align="right">
        <q-btn flat :label="$t('common.close')" @click="onDialogCancel" />
        <q-btn v-if="!created" color="primary" unelevated :label="$t('link.createLink')" :loading="loading" @click="create" />
      </q-card-actions>
    </q-card>
  </q-dialog>
</template>

<script setup>
import { useI18n } from 'vue-i18n'
import { ref } from 'vue'
import { useDialogPluginComponent, useQuasar, copyToClipboard } from 'quasar'
import { api } from '@/boot/axios'
import { errorMessage, formatDate } from '@/utils/format'

const props = defineProps({ library: { type: Object, required: true }, node: { type: Object, default: null } })
defineEmits([...useDialogPluginComponent.emits])
const { dialogRef, onDialogHide, onDialogCancel } = useDialogPluginComponent()
const $q = useQuasar()
const { t } = useI18n()

const kind = ref('download')
const password = ref('')
const expires = ref(null)
const allowDownload = ref(true)
const loading = ref(false)
const created = ref(null)

async function create() {
  loading.value = true
  try {
    const { data } = await api.post('/share-links', {
      library_id: props.library.id,
      node_id: props.node?.id || null,
      kind: kind.value,
      password: password.value || null,
      expires_in_days: expires.value || null,
      allow_download: allowDownload.value,
    })
    created.value = data
    copy(data.url)
  } catch (e) {
    $q.notify({ type: 'negative', message: errorMessage(e) })
  } finally {
    loading.value = false
  }
}

function copy(text) {
  copyToClipboard(text).then(() => $q.notify({ type: 'positive', message: t('link.copied') })).catch(() => {})
}
</script>
