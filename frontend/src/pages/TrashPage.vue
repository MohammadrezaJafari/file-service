<template>
  <q-page padding>
    <div class="row items-center q-mb-md q-gutter-sm">
      <q-btn flat round icon="arrow_back" :to="{ name: 'library', params: { id: libraryId } }" />
      <div class="page-title">Trash <span class="text-grey-6 text-subtitle1">· {{ library?.name }}</span></div>
      <q-space />
      <q-btn v-if="library?.is_owner && rows.length" flat color="negative" icon="delete_forever" label="Empty trash" @click="emptyTrash" />
    </div>

    <q-table flat bordered :rows="rows" :columns="columns" row-key="id" :loading="loading" :pagination="{ rowsPerPage: 0 }" hide-pagination no-data-label="Trash is empty">
      <template #body-cell-name="p">
        <q-td :props="p">
          <div class="row items-center no-wrap">
            <q-icon :name="fileIcon(p.row)" :color="fileColor(p.row)" size="24px" class="q-mr-sm" />
            <div class="ellipsis">
              <div>{{ p.row.name }}</div>
              <div class="text-caption text-grey-6">{{ p.row.deleted_from_path }}</div>
            </div>
          </div>
        </q-td>
      </template>
      <template #body-cell-size="p"><q-td :props="p">{{ p.row.type === 'file' ? formatBytes(p.row.size) : '—' }}</q-td></template>
      <template #body-cell-deleted_at="p"><q-td :props="p">{{ formatDate(p.row.deleted_at) }}</q-td></template>
      <template #body-cell-actions="p">
        <q-td :props="p" auto-width>
          <q-btn flat dense round icon="restore" color="primary" aria-label="Restore" @click="restore(p.row)"><q-tooltip>Restore</q-tooltip></q-btn>
          <q-btn v-if="library?.is_owner" flat dense round icon="delete_forever" color="negative" aria-label="Delete permanently" @click="purge(p.row)"><q-tooltip>Delete permanently</q-tooltip></q-btn>
        </q-td>
      </template>
    </q-table>
  </q-page>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'
import { useRoute } from 'vue-router'
import { useQuasar } from 'quasar'
import { api } from '@/boot/axios'
import { useAuthStore } from '@/stores/auth'
import { formatBytes, formatDate, fileIcon, fileColor, errorMessage } from '@/utils/format'

const route = useRoute()
const $q = useQuasar()
const auth = useAuthStore()
const libraryId = computed(() => Number(route.params.id))
const library = ref(null)
const rows = ref([])
const loading = ref(false)

const columns = [
  { name: 'name', label: 'Name', field: 'name', align: 'left', sortable: true },
  { name: 'size', label: 'Size', field: 'size', align: 'right', sortable: true },
  { name: 'deleted_at', label: 'Deleted', field: 'deleted_at', align: 'right', sortable: true },
  { name: 'actions', label: '', field: 'id', align: 'right' },
]

async function load() {
  loading.value = true
  try {
    const [lib, trash] = await Promise.all([api.get(`/libraries/${libraryId.value}`), api.get(`/libraries/${libraryId.value}/trash`)])
    library.value = lib.data
    rows.value = trash.data
  } catch (e) {
    $q.notify({ type: 'negative', message: errorMessage(e) })
  } finally {
    loading.value = false
  }
}

async function restore(node) {
  try {
    await api.post(`/libraries/${libraryId.value}/trash/${node.id}/restore`)
    $q.notify({ type: 'positive', message: `Restored "${node.name}"` })
    load()
  } catch (e) {
    $q.notify({ type: 'negative', message: errorMessage(e) })
  }
}

function purge(node) {
  $q.dialog({ title: 'Delete permanently', message: `Permanently delete "${node.name}"? This cannot be undone.`, cancel: true, ok: { label: 'Delete', color: 'negative', unelevated: true } }).onOk(async () => {
    try {
      await api.delete(`/libraries/${libraryId.value}/trash/${node.id}`)
      load()
      auth.fetchMe()
    } catch (e) {
      $q.notify({ type: 'negative', message: errorMessage(e) })
    }
  })
}

function emptyTrash() {
  $q.dialog({ title: 'Empty trash', message: 'Permanently delete everything in the trash?', cancel: true, ok: { label: 'Empty trash', color: 'negative', unelevated: true } }).onOk(async () => {
    try {
      await api.delete(`/libraries/${libraryId.value}/trash`)
      load()
      auth.fetchMe()
    } catch (e) {
      $q.notify({ type: 'negative', message: errorMessage(e) })
    }
  })
}

onMounted(load)
</script>
