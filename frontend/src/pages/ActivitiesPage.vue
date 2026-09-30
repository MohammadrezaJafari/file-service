<template>
  <q-page padding>
    <div class="page-title q-mb-md">{{ $t('activity.title') }}</div>
    <q-timeline color="primary" layout="comfortable" side="right">
      <q-timeline-entry v-for="a in activities" :key="a.id" :icon="iconFor(a.action)" :subtitle="`${a.user?.name || $t('common.anonymous')} · ${timeAgo(a.created_at)}`">
        <div><span class="text-weight-medium">{{ labelFor(a.action) }}</span> <span class="mono text-grey-8">{{ a.path }}</span></div>
        <div class="text-caption text-grey-6">{{ a.library?.name }}<span v-if="a.details?.from"> · {{ $t('activity.from', { path: a.details.from }) }}</span></div>
      </q-timeline-entry>
    </q-timeline>
    <div v-if="!activities.length && !loading" class="text-center text-grey-6 q-pa-xl">{{ $t('activity.empty') }}</div>
    <div class="text-center q-mt-md">
      <q-btn v-if="nextPage" flat color="primary" :label="$t('common.loadMore')" :loading="loading" @click="load(nextPage)" />
    </div>
  </q-page>
</template>

<script setup>
import { useI18n } from 'vue-i18n'
import { ref, onMounted } from 'vue'
import { api } from '@/boot/axios'
import { timeAgo } from '@/utils/format'

const { t, te } = useI18n()

const activities = ref([])
const loading = ref(false)
const nextPage = ref(1)

const labelFor = (a) => {
  const key = `activity.actions.${a.replace('.', '_')}`
  return te(key) ? t(key) : a
}
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
