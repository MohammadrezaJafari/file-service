<template>
  <q-card v-if="items.length" class="fixed-bottom-right q-ma-md shadow-8" style="width: 360px; z-index: 3000">
    <q-bar class="bg-primary text-white">
      <div>{{ $t('uploads.title', { done, total: items.length }) }}</div>
      <q-space />
      <q-btn flat dense round :icon="collapsed ? 'expand_less' : 'expand_more'" @click="collapsed = !collapsed" />
      <q-btn flat dense round icon="close" :disable="active > 0" @click="clear" />
    </q-bar>
    <q-list v-show="!collapsed" dense separator style="max-height: 260px; overflow: auto">
      <q-item v-for="it in items" :key="it.id">
        <q-item-section avatar>
          <q-icon v-if="it.status === 'done'" name="check_circle" color="positive" />
          <q-icon v-else-if="it.status === 'error'" name="error" color="negative" />
          <q-circular-progress v-else :value="it.progress" size="24px" color="primary" :indeterminate="it.status === 'pending'" />
        </q-item-section>
        <q-item-section>
          <q-item-label class="ellipsis">{{ it.file.name }}</q-item-label>
          <q-item-label caption>{{ it.status === 'error' ? it.error : formatBytes(it.file.size) }}</q-item-label>
        </q-item-section>
      </q-item>
    </q-list>
  </q-card>
</template>

<script setup>
import { useI18n } from 'vue-i18n'
import { ref, computed } from 'vue'
import { api } from '@/boot/axios'
import { formatBytes, errorMessage } from '@/utils/format'

const { t } = useI18n()

const emit = defineEmits(['uploaded', 'finished'])
const items = ref([])
const collapsed = ref(false)
const active = computed(() => items.value.filter((i) => i.status === 'uploading' || i.status === 'pending').length)
const done = computed(() => items.value.filter((i) => i.status === 'done' || i.status === 'error').length)
let seq = 0
const CONCURRENCY = 3

/**
 * @param {File[]} files
 * @param {{ url: string, fields?: object }} target
 */
function enqueue(files, target) {
  for (const file of files) {
    items.value.push({ id: ++seq, file, target, status: 'pending', progress: 0, error: null })
  }
  pump()
}

function pump() {
  const uploading = items.value.filter((i) => i.status === 'uploading').length
  const next = items.value.filter((i) => i.status === 'pending').slice(0, Math.max(0, CONCURRENCY - uploading))
  next.forEach(upload)
}

async function upload(item) {
  item.status = 'uploading'
  const form = new FormData()
  form.append('file', item.file)
  Object.entries(item.target.fields || {}).forEach(([k, v]) => v !== undefined && v !== null && form.append(k, v))
  try {
    const { data } = await api.post(item.target.url, form, {
      headers: item.target.headers || {},
      onUploadProgress: (e) => (item.progress = e.total ? Math.round((e.loaded / e.total) * 100) : 0),
    })
    item.status = 'done'
    item.progress = 100
    emit('uploaded', data)
  } catch (e) {
    item.status = 'error'
    item.error = errorMessage(e, t('uploads.failed'))
  } finally {
    if (active.value === 0) emit('finished')
    pump()
  }
}

function clear() {
  items.value = []
}

defineExpose({ enqueue })
</script>
