<template>
  <q-page padding>
    <div class="page-title q-mb-md">{{ $t('starred.title') }}</div>
    <q-inner-loading :showing="loading" />
    <div v-if="!loading && !items.length" class="fs-card fs-empty">
      <q-icon name="star_border" size="64px" />
      <div class="q-mt-md">{{ $t('starred.empty') }}</div>
    </div>
    <q-list v-else separator class="fs-card">
      <q-item v-for="n in items" :key="n.id" clickable @click="open(n)">
        <q-item-section avatar><q-icon :name="fileIcon(n)" :color="fileColor(n)" /></q-item-section>
        <q-item-section>
          <q-item-label>{{ n.name }}</q-item-label>
          <q-item-label caption>{{ n.library?.name }}{{ n.path }}</q-item-label>
        </q-item-section>
        <q-item-section side class="text-caption">{{ n.type === 'file' ? formatBytes(n.size) : '' }}</q-item-section>
        <q-item-section side>
          <q-btn flat round dense icon="star" color="amber" @click.stop="unstar(n)"><q-tooltip>{{ $t('common.unstar') }}</q-tooltip></q-btn>
        </q-item-section>
      </q-item>
    </q-list>
  </q-page>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import { api } from '@/boot/axios'
import { fileIcon, fileColor, formatBytes } from '@/utils/format'
import { downloadNode } from '@/utils/download'

const router = useRouter()
const items = ref([])
const loading = ref(false)

async function load() {
  loading.value = true
  try {
    const { data } = await api.get('/starred')
    items.value = data
  } finally {
    loading.value = false
  }
}
function open(n) {
  if (n.type === 'folder') router.push({ name: 'folder', params: { id: n.library_id, folderId: n.id } })
  else if (n.parent_id) router.push({ name: 'folder', params: { id: n.library_id, folderId: n.parent_id } })
  else downloadNode(n)
}
async function unstar(n) {
  await api.delete(`/nodes/${n.id}/star`)
  items.value = items.value.filter((i) => i.id !== n.id)
}
onMounted(load)
</script>
