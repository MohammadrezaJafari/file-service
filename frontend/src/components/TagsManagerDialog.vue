<template>
  <q-dialog ref="dialogRef" @hide="onDialogHide">
    <q-card class="fs-card" style="min-width: 520px; max-width: 95vw">
      <q-card-section class="row items-center">
        <div class="text-h6">{{ $t('tags.manage') }}</div>
        <q-space />
        <q-btn flat round dense icon="close" @click="onDialogCancel" />
      </q-card-section>

      <q-card-section class="q-pt-none">
        <q-form class="row q-col-gutter-sm items-start" @submit="create">
          <div class="col-4"><q-input v-model="form.name" dense outlined :label="$t('tags.name')" :rules="[(v) => !!v || $t('common.required')]" /></div>
          <div class="col-3">
            <q-select v-model="form.parent_id" dense outlined :label="$t('tags.parent')" :options="parentOptions" emit-value map-options clearable />
          </div>
          <div class="col-3">
            <q-input v-model="form.color" dense outlined :label="$t('tags.color')">
              <template #prepend><div :style="{ background: form.color, width: '18px', height: '18px', borderRadius: '6px' }" /></template>
              <template #append>
                <q-icon name="palette" class="cursor-pointer"><q-popup-proxy><q-color v-model="form.color" no-header default-view="palette" :palette="palette" /></q-popup-proxy></q-icon>
              </template>
            </q-input>
          </div>
          <div class="col-2"><q-btn type="submit" color="primary" unelevated class="full-width" icon="add" :loading="saving" /></div>
        </q-form>
      </q-card-section>

      <q-card-section class="q-pt-none">
        <div v-if="!tags.length" class="fs-empty q-pa-md">{{ $t('tags.empty') }}</div>
        <q-list v-else separator>
          <q-item v-for="t in tree" :key="t.id" :style="{ paddingInlineStart: 16 + t.depth * 24 + 'px' }">
            <q-item-section avatar><q-icon name="label" :style="{ color: t.color }" /></q-item-section>
            <q-item-section>
              <q-item-label>{{ t.name }}</q-item-label>
              <q-item-label caption>{{ $t('tags.files', { n: t.nodes_count }) }}</q-item-label>
            </q-item-section>
            <q-item-section side>
              <div class="row no-wrap">
                <q-btn flat round dense icon="edit" @click="rename(t)" />
                <q-btn flat round dense icon="delete" color="negative" @click="remove(t)" />
              </div>
            </q-item-section>
          </q-item>
        </q-list>
      </q-card-section>
    </q-card>
  </q-dialog>
</template>

<script setup>
import { ref, reactive, computed, onMounted } from 'vue'
import { useDialogPluginComponent, useQuasar } from 'quasar'
import { useI18n } from 'vue-i18n'
import { api } from '@/boot/axios'
import { errorMessage } from '@/utils/format'

const props = defineProps({ library: { type: Object, required: true } })
defineEmits([...useDialogPluginComponent.emits])
const { dialogRef, onDialogHide, onDialogCancel } = useDialogPluginComponent()
const $q = useQuasar()
const { t } = useI18n()
const palette = ['#2563eb', '#0ea5e9', '#16a34a', '#f59e0b', '#dc2626', '#8b5cf6', '#ec4899', '#64748b', '#0d9488', '#b45309']
const tags = ref([])
const form = reactive({ name: '', parent_id: null, color: palette[0] })
const saving = ref(false)
let changed = false

const tree = computed(() => {
  const out = []
  const walk = (parentId, depth) => {
    tags.value.filter((x) => x.parent_id === parentId).forEach((x) => {
      out.push({ ...x, depth })
      walk(x.id, depth + 1)
    })
  }
  walk(null, 0)
  return out
})
const parentOptions = computed(() => tree.value.map((x) => ({ label: '  '.repeat(x.depth) + x.name, value: x.id })))

async function load() {
  const { data } = await api.get(`/libraries/${props.library.id}/tags`)
  tags.value = data
}
async function create() {
  saving.value = true
  try {
    await api.post(`/libraries/${props.library.id}/tags`, form)
    form.name = ''
    changed = true
    await load()
  } catch (e) {
    $q.notify({ type: 'negative', message: errorMessage(e) })
  } finally {
    saving.value = false
  }
}
function rename(tag) {
  $q.dialog({ title: t('common.rename'), prompt: { model: tag.name, isValid: (v) => !!v.trim() }, cancel: t('common.cancel'), ok: t('common.ok') }).onOk(async (name) => {
    await api.put(`/tags/${tag.id}`, { name })
    changed = true
    load()
  })
}
function remove(tag) {
  $q.dialog({ title: t('common.delete'), message: t('tags.deleteMsg', { name: tag.name }), cancel: t('common.cancel'), ok: { label: t('common.delete'), color: 'negative', unelevated: true } }).onOk(async () => {
    await api.delete(`/tags/${tag.id}`)
    changed = true
    load()
  })
}
defineExpose({ changed: () => changed })
onMounted(load)
</script>
