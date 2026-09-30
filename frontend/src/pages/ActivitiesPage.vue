<template>
  <q-page padding>
    <div class="page-title q-mb-md">Activity</div>
    <q-timeline color="primary" layout="comfortable" side="right">
      <q-timeline-entry v-for="a in activities" :key="a.id" :icon="iconFor(a.action)" :subtitle="`${a.user?.name || 'Anonymous'} · ${timeAgo(a.created_at)}`">
        <div><span class="text-weight-medium">{{ labelFor(a.action) }}</span> <span class="mono text-grey-8">{{ a.path }}</span></div>
        <div class="text-caption text-grey-6">{{ a.library?.name }}<span v-if="a.details?.from"> · from {{ a.details.from }}</span></div>
      </q-timeline-entry>
    </q-timeline>
    <div v-if="!activities.length && !loading" class="text-center text-grey-6 q-pa-xl">No activity yet.</div>
    <div class="text-center q-mt-md">
      <q-btn v-if="nextPage" flat color="primary" label="Load more" :loading="loading" @click="load(nextPage)" />
    </div>
  </q-page>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import { api } from '@/boot/axios'
import { timeAgo } from '@/utils/format'

const activities = ref([])
const loading = ref(false)
const nextPage = ref(1)

const labels = {
  'library.create': 'Created library',
  'library.update': 'Updated library',
  'library.delete': 'Deleted library',
  'folder.create': 'Created folder',
  'folder.rename': 'Renamed folder',
  'folder.move': 'Moved folder',
  'folder.copy': 'Copied folder',
  'folder.delete': 'Deleted folder',
  'folder.restore': 'Restored folder',
  'folder.purge': 'Permanently deleted folder',
  'file.create': 'Uploaded file',
  'file.update': 'Updated file',
  'file.rename': 'Renamed file',
  'file.move': 'Moved file',
  'file.copy': 'Copied file',
  'file.delete': 'Deleted file',
  'file.restore': 'Restored file',
  'file.purge': 'Permanently deleted file',
  'file.download': 'Downloaded file',
  'file.restore_version': 'Restored file version',
  'share.create': 'Shared',
  'share.delete': 'Removed share',
  'link.create': 'Created share link',
  'link.delete': 'Deleted share link',
  'link.download': 'Downloaded via link',
  'link.upload': 'Uploaded via link',
}
const labelFor = (a) => labels[a] || a
const iconFor = (a) => {
  if (a.startsWith('share') || a.startsWith('link')) return 'share'
  if (a.endsWith('delete') || a.endsWith('purge')) return 'delete'
  if (a.endsWith('create') || a.endsWith('update')) return 'add'
  if (a.includes('restore')) return 'restore'
  if (a.includes('download')) return 'download'
  return 'edit'
}

async function load(page = 1) {
  loading.value = true
  try {
    const { data } = await api.get('/activities', { params: { page } })
    activities.value = page === 1 ? data.data : [...activities.value, ...data.data]
    nextPage.value = data.meta.current_page < data.meta.last_page ? data.meta.current_page + 1 : null
  } finally {
    loading.value = false
  }
}
onMounted(() => load(1))
</script>
