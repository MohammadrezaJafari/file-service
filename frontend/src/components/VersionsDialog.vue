<template>
  <q-dialog ref="dialogRef" @hide="onDialogHide">
    <q-card style="min-width: 560px; max-width: 95vw">
      <q-card-section>
        <div class="text-h6">Version history</div>
        <div class="text-caption text-grey-7">{{ node.name }}</div>
      </q-card-section>
      <q-card-section class="q-pt-none">
        <q-list separator>
          <q-item v-for="v in versions" :key="v.id">
            <q-item-section avatar>
              <q-avatar :color="v.version_number === current ? 'primary' : 'grey-4'" :text-color="v.version_number === current ? 'white' : 'grey-8'" size="32px">v{{ v.version_number }}</q-avatar>
            </q-item-section>
            <q-item-section>
              <q-item-label>{{ formatDate(v.created_at) }} · {{ formatBytes(v.size) }}</q-item-label>
              <q-item-label caption>{{ v.creator?.name }}<span v-if="v.comment"> · {{ v.comment }}</span></q-item-label>
            </q-item-section>
            <q-item-section side>
              <div class="row q-gutter-xs">
                <q-btn flat dense round icon="download" @click="download(v)"><q-tooltip>Download this version</q-tooltip></q-btn>
                <q-btn v-if="v.version_number !== current && canWrite" flat dense round icon="restore" color="primary" @click="restore(v)"><q-tooltip>Restore this version</q-tooltip></q-btn>
              </div>
            </q-item-section>
          </q-item>
        </q-list>
      </q-card-section>
      <q-card-actions align="right">
        <q-btn flat label="Close" @click="onDialogCancel" />
      </q-card-actions>
    </q-card>
  </q-dialog>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import { useDialogPluginComponent, useQuasar } from 'quasar'
import { api } from '@/boot/axios'
import { formatDate, formatBytes, errorMessage } from '@/utils/format'

const props = defineProps({ node: { type: Object, required: true }, canWrite: { type: Boolean, default: false } })
defineEmits([...useDialogPluginComponent.emits])
const { dialogRef, onDialogHide, onDialogOK, onDialogCancel } = useDialogPluginComponent()
const $q = useQuasar()
const versions = ref([])
const current = ref(props.node.version_number)

async function load() {
  const { data } = await api.get(`/nodes/${props.node.id}/versions`)
  versions.value = data
}

async function download(v) {
  // Version downloads need auth: fetch as blob and save.
  const res = await api.get(`/nodes/${props.node.id}/versions/${v.id}/download`, { responseType: 'blob' })
  const url = URL.createObjectURL(res.data)
  const a = document.createElement('a')
  a.href = url
  a.download = props.node.name
  a.click()
  URL.revokeObjectURL(url)
}

function restore(v) {
  $q.dialog({ title: 'Restore version', message: `Restore version ${v.version_number}? The current content will be kept in history.`, cancel: true }).onOk(async () => {
    try {
      const { data } = await api.post(`/nodes/${props.node.id}/versions/${v.id}/restore`)
      current.value = data.version_number
      await load()
      onDialogOK(data)
    } catch (e) {
      $q.notify({ type: 'negative', message: errorMessage(e) })
    }
  })
}

onMounted(load)
</script>
