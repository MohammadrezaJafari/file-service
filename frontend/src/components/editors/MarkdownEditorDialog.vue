<template>
  <q-dialog ref="dialogRef" maximized persistent @hide="onDialogHide">
    <q-card class="column" style="background: var(--fs-bg)">
      <q-bar class="fs-header" style="height: 52px">
        <q-icon name="article" color="primary" size="22px" />
        <div class="text-subtitle1 text-weight-medium q-mx-sm ellipsis">{{ node.name }}</div>
        <q-chip v-if="!canWrite" dense icon="visibility" :label="$t('editor.readOnly')" />
        <q-chip v-else-if="dirty" dense color="amber-2" text-color="amber-10" icon="edit" :label="$t('editor.edit')" />
        <q-space />
        <q-btn-toggle v-if="canWrite" v-model="mode" flat dense toggle-color="primary" :options="[{ value: 'edit', icon: 'edit', slot: 'e' }, { value: 'split', icon: 'vertical_split' }, { value: 'preview', icon: 'visibility' }]" />
        <q-btn v-if="canWrite" color="primary" unelevated dense class="q-mx-sm" icon="save" :label="$t('editor.save')" :disable="!dirty" :loading="saving" @click="save" />
        <q-btn flat round dense icon="close" :aria-label="$t('common.close')" @click="close" />
      </q-bar>

      <div class="col row no-wrap" style="min-height: 0">
        <div v-if="mode !== 'preview' && canWrite" class="col fs-editor column" :class="{ 'col-6': mode === 'split' }" style="border-inline-end: 1px solid var(--fs-border)">
          <q-input v-model="text" type="textarea" borderless class="col" input-class="fit q-pa-md" :input-attrs="{ dir: 'auto' }" :placeholder="$t('editor.markdownHint')" :loading="loading" />
        </div>
        <div v-if="mode !== 'edit' || !canWrite" class="col scroll q-pa-lg">
          <div class="fs-card q-pa-xl fs-markdown" style="max-width: 900px; margin: 0 auto" v-html="html" />
        </div>
      </div>
    </q-card>
  </q-dialog>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'
import { useDialogPluginComponent, useQuasar } from 'quasar'
import { useI18n } from 'vue-i18n'
import { api } from '@/boot/axios'
import { renderMarkdown } from '@/utils/markdown'
import { errorMessage } from '@/utils/format'

const props = defineProps({ node: { type: Object, required: true }, canWrite: { type: Boolean, default: false } })
defineEmits([...useDialogPluginComponent.emits])
const { dialogRef, onDialogHide, onDialogOK, onDialogCancel } = useDialogPluginComponent()
const $q = useQuasar()
const { t } = useI18n()
const text = ref('')
const savedText = ref('')
const mode = ref(props.canWrite ? 'split' : 'preview')
const dirty = computed(() => text.value !== savedText.value)
const loading = ref(true)
const saving = ref(false)
let changed = false
const html = computed(() => renderMarkdown(text.value))

async function save() {
  saving.value = true
  try {
    const { data } = await api.put(`/nodes/${props.node.id}/content`, { content: text.value })
    savedText.value = text.value
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
    text.value = data
    savedText.value = data
  } catch (e) {
    $q.notify({ type: 'negative', message: errorMessage(e) })
  } finally {
    loading.value = false
  }
})
</script>
