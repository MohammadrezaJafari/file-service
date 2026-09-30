<template>
  <q-page padding>
    <div class="page-title q-mb-md">{{ $t('links.title') }}</div>
    <q-table flat bordered :rows="links" :columns="columns" row-key="id" :loading="loading" :pagination="{ rowsPerPage: 25 }" :no-data-label="$t('links.empty')">
      <template #body-cell-name="p">
        <q-td :props="p">
          <div class="row items-center no-wrap">
            <q-icon :name="p.row.node ? fileIcon(p.row.node) : 'inventory_2'" :color="p.row.node ? fileColor(p.row.node) : 'primary'" class="q-mr-sm" />
            <div class="ellipsis">
              <div>{{ p.row.node?.name || p.row.library?.name }}</div>
              <div class="text-caption text-grey-6">{{ p.row.library?.name }}</div>
            </div>
          </div>
        </q-td>
      </template>
      <template #body-cell-kind="p">
        <q-td :props="p"><q-chip dense :icon="p.row.kind === 'upload' ? 'upload' : 'download'" :label="$t(`links.kinds.${p.row.kind}`)" /></q-td>
      </template>
      <template #body-cell-url="p">
        <q-td :props="p">
          <div class="row items-center no-wrap">
            <span class="mono text-caption ellipsis" style="max-width: 260px">{{ p.row.url }}</span>
            <q-btn flat dense round size="sm" icon="content_copy" @click="copy(p.row.url)" />
          </div>
        </q-td>
      </template>
      <template #body-cell-status="p">
        <q-td :props="p">
          <q-icon v-if="p.row.has_password" name="lock" size="16px" class="q-mr-xs"><q-tooltip>{{ $t('link.protected') }}</q-tooltip></q-icon>
          <span v-if="p.row.is_expired" class="text-negative">{{ $t('links.expired') }}</span>
          <span v-else-if="p.row.expires_at">{{ $t('link.expiresAt', { date: formatDate(p.row.expires_at) }) }}</span>
          <span v-else class="text-grey-7">{{ $t('links.noExpiry') }}</span>
        </q-td>
      </template>
      <template #body-cell-actions="p">
        <q-td :props="p" auto-width>
          <q-btn flat dense round icon="delete" color="negative" @click="remove(p.row)" />
        </q-td>
      </template>
    </q-table>
  </q-page>
</template>

<script setup>
import { useI18n } from 'vue-i18n'
import { ref, computed, onMounted } from 'vue'
import { useQuasar, copyToClipboard } from 'quasar'
import { api } from '@/boot/axios'
import { fileIcon, fileColor, formatDate, errorMessage } from '@/utils/format'

const $q = useQuasar()
const { t } = useI18n()
const links = ref([])
const loading = ref(false)
const columns = computed(() => [
  { name: 'name', label: t('links.item'), field: 'id', align: 'left' },
  { name: 'kind', label: t('links.type'), field: 'kind', align: 'left' },
  { name: 'url', label: t('links.link'), field: 'url', align: 'left' },
  { name: 'status', label: t('links.status'), field: 'expires_at', align: 'left' },
  { name: 'view_count', label: t('links.views'), field: 'view_count', align: 'right' },
  { name: 'download_count', label: t('links.downloads'), field: 'download_count', align: 'right' },
  { name: 'actions', label: '', field: 'id' },
])

async function load() {
  loading.value = true
  try {
    const { data } = await api.get('/share-links')
    links.value = data
  } finally {
    loading.value = false
  }
}
function copy(text) {
  copyToClipboard(text).then(() => $q.notify({ type: 'positive', message: t('common.copied') }))
}
function remove(link) {
  $q.dialog({ title: t('links.deleteTitle'), message: t('links.deleteMsg'), cancel: t('common.cancel'), ok: { label: t('common.delete'), color: 'negative', unelevated: true } }).onOk(async () => {
    try {
      await api.delete(`/share-links/${link.id}`)
      load()
    } catch (e) {
      $q.notify({ type: 'negative', message: errorMessage(e) })
    }
  })
}
onMounted(load)
</script>
