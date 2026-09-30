<template>
  <q-dialog ref="dialogRef" maximized persistent @hide="onDialogHide">
    <q-card class="column">
      <q-bar class="fs-header" style="height: 52px">
        <q-icon name="draw" color="accent" size="22px" />
        <div class="text-subtitle1 text-weight-medium q-mx-sm ellipsis">{{ node.name }}</div>
        <q-chip v-if="!canWrite" dense icon="visibility" :label="$t('editor.readOnly')" />
        <q-space />
        <q-btn v-if="canWrite" color="primary" unelevated dense icon="save" :label="$t('editor.save')" :disable="!dirty" :loading="saving" @click="save" />
        <q-btn flat round dense icon="close" class="q-ml-sm" :aria-label="$t('common.close')" @click="close" />
      </q-bar>
      <div class="col" style="min-height: 0; position: relative">
        <q-inner-loading :showing="loading" />
        <ExcalidrawBoard v-if="!loading" ref="board" :initial-data="initial" :view-mode="!canWrite" :lang-code="locale" height="100%" @change="dirty = true" />
      </div>
    </q-card>
  </q-dialog>
</template>

<script setup>
import { ref, onMounted, defineAsyncComponent } from 'vue'
import { useDialogPluginComponent, useQuasar } from 'quasar'
import { useI18n } from 'vue-i18n'
import { api } from '@/boot/axios'
import { errorMessage } from '@/utils/format'

const ExcalidrawBoard = defineAsyncComponent(() => import('./ExcalidrawBoard.vue'))

const props = defineProps({ node: { type: Object, required: true }, canWrite: { type: Boolean, default: false } })
defineEmits([...useDialogPluginComponent.emits])
const { dialogRef, onDialogHide, onDialogOK, onDialogCancel } = useDialogPluginComponent()
const $q = useQuasar()
const { t, locale } = useI18n()
const board = ref(null)
const initial = ref(null)
const loading = ref(true)
const dirty = ref(false)
const saving = ref(false)
let changed = false

async function save() {
  const scene = board.value?.getScene()
  if (!scene) return
  saving.value = true
  try {
    const payload = {
      type: 'excalidraw',
      version: 2,
      source: 'file-service',
      elements: scene.elements.filter((e) => !e.isDeleted),
      appState: { viewBackgroundColor: scene.appState.viewBackgroundColor, gridSize: scene.appState.gridSize },
      files: scene.files,
    }
    const { data } = await api.put(`/nodes/${props.node.id}/content`, { content: JSON.stringify(payload) })
    dirty.value = false
    changed = true
    $q.notify({ type: 'positive', message: t('editor.saved', { n: data.version_number }) })
  } catch (e) {
    $q.notify({ type: 'negative', message: errorMessage(e) })
  } finally {
    saving.value = false
  }
}
function close() {
  if (dirty.value) {
    $q.dialog({ title: t('editor.edit'), message: t('editor.unsaved'), cancel: t('common.cancel'), ok: t('common.ok') }).onOk(() => (changed ? onDialogOK() : onDialogCancel()))
  } else changed ? onDialogOK() : onDialogCancel()
}
onMounted(async () => {
  try {
    const { data } = await api.get(`/nodes/${props.node.id}/content`, { responseType: 'text', transformResponse: [(d) => d] })
    if (data && data.trim()) {
      const parsed = JSON.parse(data)
      initial.value = { elements: parsed.elements || [], appState: parsed.appState || {}, files: parsed.files || {} }
    }
  } catch (e) {
    $q.notify({ type: 'negative', message: errorMessage(e) })
  } finally {
    loading.value = false
  }
})
</script>
