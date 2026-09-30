<template>
  <q-page padding class="drop-zone" :class="{ 'is-dragging': dragging && canWrite }" @dragover.prevent="onDragOver" @dragleave.prevent="dragging = false" @drop.prevent="onDrop">
    <!-- Header -->
    <div class="row items-center q-mb-sm q-gutter-sm">
      <q-breadcrumbs class="text-subtitle1" active-color="primary" separator-color="grey">
        <q-breadcrumbs-el icon="inventory_2" :label="library?.name || '…'" :to="{ name: 'library', params: { id: libraryId } }" />
        <q-breadcrumbs-el v-for="b in breadcrumbs" :key="b.id" :label="b.name" :to="{ name: 'folder', params: { id: libraryId, folderId: b.id } }" />
      </q-breadcrumbs>
      <q-chip v-if="permission === 'r'" dense color="grey-3" icon="visibility" label="Read only" />
      <q-space />
      <q-input v-model="search" dense outlined placeholder="Search in library" debounce="400" style="width: 240px" clearable @update:model-value="doSearch">
        <template #prepend><q-icon name="search" /></template>
      </q-input>
      <q-btn-group v-if="canWrite" unelevated>
        <q-btn color="primary" icon="upload" label="Upload" @click="fileInput.click()" />
        <q-btn color="primary" icon="create_new_folder" aria-label="New folder" @click="newFolder"><q-tooltip>New folder</q-tooltip></q-btn>
      </q-btn-group>
      <q-btn flat round icon="more_vert" aria-label="Library menu">
        <q-menu>
          <q-list dense style="min-width: 200px">
            <q-item v-if="library?.is_owner" clickable v-close-popup @click="shareCurrent">
              <q-item-section avatar><q-icon name="share" /></q-item-section>
              <q-item-section>Share {{ folder ? 'this folder' : 'library' }}</q-item-section>
            </q-item>
            <q-item clickable v-close-popup @click="linkCurrent">
              <q-item-section avatar><q-icon name="link" /></q-item-section>
              <q-item-section>Get link</q-item-section>
            </q-item>
            <q-item v-if="folder" clickable v-close-popup @click="download(folder)">
              <q-item-section avatar><q-icon name="download" /></q-item-section>
              <q-item-section>Download as ZIP</q-item-section>
            </q-item>
            <q-item clickable v-close-popup :to="{ name: 'trash', params: { id: libraryId } }">
              <q-item-section avatar><q-icon name="delete_outline" /></q-item-section>
              <q-item-section>Trash</q-item-section>
            </q-item>
          </q-list>
        </q-menu>
      </q-btn>
      <input ref="fileInput" type="file" multiple class="hidden" @change="onFilesPicked" />
    </div>

    <!-- Bulk toolbar -->
    <q-slide-transition>
      <div v-if="selected.length" class="row items-center q-gutter-sm q-mb-sm bg-blue-1 q-pa-sm rounded-borders">
        <div class="text-primary">{{ selected.length }} selected</div>
        <q-btn flat dense icon="download" label="Download" @click="selected.forEach(download)" />
        <q-btn v-if="canWrite" flat dense icon="drive_file_move" label="Move" @click="moveCopy(selected, 'move')" />
        <q-btn flat dense icon="file_copy" label="Copy" @click="moveCopy(selected, 'copy')" />
        <q-btn v-if="canWrite" flat dense icon="delete" label="Delete" color="negative" @click="remove(selected)" />
        <q-space />
        <q-btn flat dense round icon="close" @click="selected = []" />
      </div>
    </q-slide-transition>

    <!-- Table -->
    <q-table
      flat
      bordered
      :rows="rows"
      :columns="columns"
      row-key="id"
      :loading="loading"
      :selection="canWrite ? 'multiple' : 'multiple'"
      v-model:selected="selected"
      :pagination="{ rowsPerPage: 0 }"
      hide-pagination
      binary-state-sort
      :no-data-label="searchMode ? 'No results' : 'This folder is empty'"
    >
      <template #body-cell-name="p">
        <q-td :props="p" class="file-row" @click="open(p.row)" @dblclick.prevent>
          <div class="row items-center no-wrap">
            <q-icon :name="fileIcon(p.row)" :color="fileColor(p.row)" size="24px" class="q-mr-sm" />
            <div class="ellipsis">
              <div>{{ p.row.name }}</div>
              <div v-if="searchMode" class="text-caption text-grey-6">{{ p.row.path }}</div>
            </div>
            <q-icon v-if="p.row.is_starred" name="star" color="amber" size="16px" class="q-ml-xs" />
          </div>
        </q-td>
      </template>
      <template #body-cell-size="p">
        <q-td :props="p" class="text-grey-8">{{ p.row.type === 'file' ? formatBytes(p.row.size) : '—' }}</q-td>
      </template>
      <template #body-cell-updated_at="p">
        <q-td :props="p" class="text-grey-8">
          {{ timeAgo(p.row.updated_at) }}
          <q-tooltip>{{ formatDate(p.row.updated_at) }}<span v-if="p.row.updater"> · {{ p.row.updater.name }}</span></q-tooltip>
        </q-td>
      </template>
      <template #body-cell-actions="p">
        <q-td :props="p" auto-width @click.stop>
          <q-btn flat round dense icon="more_horiz" aria-label="Actions">
            <q-menu>
              <q-list dense style="min-width: 190px">
                <q-item clickable v-close-popup @click="download(p.row)">
                  <q-item-section avatar><q-icon name="download" /></q-item-section>
                  <q-item-section>Download{{ p.row.type === 'folder' ? ' (ZIP)' : '' }}</q-item-section>
                </q-item>
                <q-item clickable v-close-popup @click="toggleStar(p.row)">
                  <q-item-section avatar><q-icon :name="p.row.is_starred ? 'star' : 'star_border'" /></q-item-section>
                  <q-item-section>{{ p.row.is_starred ? 'Unstar' : 'Star' }}</q-item-section>
                </q-item>
                <q-item clickable v-close-popup @click="link(p.row)">
                  <q-item-section avatar><q-icon name="link" /></q-item-section>
                  <q-item-section>Get link</q-item-section>
                </q-item>
                <q-item v-if="library?.is_owner && p.row.type === 'folder'" clickable v-close-popup @click="share(p.row)">
                  <q-item-section avatar><q-icon name="share" /></q-item-section>
                  <q-item-section>Share folder</q-item-section>
                </q-item>
                <q-item v-if="p.row.type === 'file'" clickable v-close-popup @click="versions(p.row)">
                  <q-item-section avatar><q-icon name="history" /></q-item-section>
                  <q-item-section>History</q-item-section>
                </q-item>
                <q-separator />
                <q-item v-if="canWrite" clickable v-close-popup @click="rename(p.row)">
                  <q-item-section avatar><q-icon name="edit" /></q-item-section>
                  <q-item-section>Rename</q-item-section>
                </q-item>
                <q-item v-if="canWrite" clickable v-close-popup @click="moveCopy([p.row], 'move')">
                  <q-item-section avatar><q-icon name="drive_file_move" /></q-item-section>
                  <q-item-section>Move</q-item-section>
                </q-item>
                <q-item clickable v-close-popup @click="moveCopy([p.row], 'copy')">
                  <q-item-section avatar><q-icon name="file_copy" /></q-item-section>
                  <q-item-section>Copy</q-item-section>
                </q-item>
                <q-item v-if="canWrite" clickable v-close-popup class="text-negative" @click="remove([p.row])">
                  <q-item-section avatar><q-icon name="delete" /></q-item-section>
                  <q-item-section>Delete</q-item-section>
                </q-item>
              </q-list>
            </q-menu>
          </q-btn>
        </q-td>
      </template>
    </q-table>

    <div v-if="canWrite && !rows.length && !loading && !searchMode" class="text-center text-grey-6 q-pa-lg">
      <q-icon name="cloud_upload" size="48px" />
      <div class="q-mt-sm">Drag & drop files here to upload</div>
    </div>

    <UploadQueue ref="uploader" @uploaded="onUploaded" />
  </q-page>
</template>

<script setup>
import { ref, computed, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useQuasar } from 'quasar'
import { api } from '@/boot/axios'
import { useAuthStore } from '@/stores/auth'
import { formatBytes, formatDate, timeAgo, fileIcon, fileColor, errorMessage, isPreviewable } from '@/utils/format'
import { downloadNode, signedUrl } from '@/utils/download'
import UploadQueue from '@/components/UploadQueue.vue'
import ShareDialog from '@/components/ShareDialog.vue'
import ShareLinkDialog from '@/components/ShareLinkDialog.vue'
import FolderPickerDialog from '@/components/FolderPickerDialog.vue'
import VersionsDialog from '@/components/VersionsDialog.vue'
import PreviewDialog from '@/components/PreviewDialog.vue'

const route = useRoute()
const router = useRouter()
const $q = useQuasar()
const auth = useAuthStore()

const libraryId = computed(() => Number(route.params.id))
const folderId = computed(() => (route.params.folderId ? Number(route.params.folderId) : null))

const library = ref(null)
const folder = ref(null)
const breadcrumbs = ref([])
const permission = ref(null)
const items = ref([])
const searchResults = ref(null)
const search = ref('')
const loading = ref(false)
const selected = ref([])
const dragging = ref(false)
const fileInput = ref(null)
const uploader = ref(null)

const canWrite = computed(() => permission.value === 'rw')
const searchMode = computed(() => searchResults.value !== null)
const rows = computed(() => searchResults.value ?? items.value)

const columns = [
  { name: 'name', label: 'Name', field: 'name', align: 'left', sortable: true },
  { name: 'size', label: 'Size', field: 'size', align: 'right', sortable: true, style: 'width: 110px' },
  { name: 'updated_at', label: 'Modified', field: 'updated_at', align: 'right', sortable: true, style: 'width: 160px' },
  { name: 'actions', label: '', field: 'id', align: 'right' },
]

async function load() {
  loading.value = true
  selected.value = []
  try {
    const { data } = await api.get(`/libraries/${libraryId.value}/nodes`, { params: { parent_id: folderId.value || undefined } })
    library.value = data.library
    folder.value = data.folder
    breadcrumbs.value = data.breadcrumbs
    permission.value = data.permission
    items.value = data.items
  } catch (e) {
    $q.notify({ type: 'negative', message: errorMessage(e) })
    if (e.response?.status === 403 || e.response?.status === 404) router.replace({ name: 'libraries' })
  } finally {
    loading.value = false
  }
}

async function doSearch(q) {
  if (!q) {
    searchResults.value = null
    return
  }
  loading.value = true
  try {
    const { data } = await api.get(`/libraries/${libraryId.value}/search`, { params: { q } })
    searchResults.value = data
  } finally {
    loading.value = false
  }
}

function open(node) {
  if (node.type === 'folder') {
    search.value = ''
    searchResults.value = null
    router.push({ name: 'folder', params: { id: libraryId.value, folderId: node.id } })
  } else if (isPreviewable(node)) {
    $q.dialog({ component: PreviewDialog, componentProps: { node, resolveUrl: (n) => signedUrl(n, { inline: true }) } })
  } else {
    download(node)
  }
}

async function download(node) {
  try {
    await downloadNode(node)
  } catch (e) {
    $q.notify({ type: 'negative', message: errorMessage(e) })
  }
}

function newFolder() {
  $q.dialog({ title: 'New folder', prompt: { model: '', type: 'text', label: 'Folder name', isValid: (v) => !!v.trim() }, cancel: true }).onOk(async (name) => {
    try {
      await api.post(`/libraries/${libraryId.value}/folders`, { name, parent_id: folderId.value })
      load()
    } catch (e) {
      $q.notify({ type: 'negative', message: errorMessage(e) })
    }
  })
}

function rename(node) {
  $q.dialog({ title: 'Rename', prompt: { model: node.name, type: 'text', isValid: (v) => !!v.trim() }, cancel: true }).onOk(async (name) => {
    try {
      await api.patch(`/nodes/${node.id}`, { name })
      load()
    } catch (e) {
      $q.notify({ type: 'negative', message: errorMessage(e) })
    }
  })
}

function remove(nodes) {
  $q.dialog({
    title: 'Move to trash',
    message: nodes.length === 1 ? `Move "${nodes[0].name}" to trash?` : `Move ${nodes.length} items to trash?`,
    cancel: true,
    ok: { label: 'Delete', color: 'negative', unelevated: true },
  }).onOk(async () => {
    try {
      await Promise.all(nodes.map((n) => api.delete(`/nodes/${n.id}`)))
      $q.notify({ type: 'positive', message: 'Moved to trash', actions: [{ label: 'Trash', color: 'white', handler: () => router.push({ name: 'trash', params: { id: libraryId.value } }) }] })
      load()
      auth.fetchMe()
    } catch (e) {
      $q.notify({ type: 'negative', message: errorMessage(e) })
    }
  })
}

function moveCopy(nodes, action) {
  $q.dialog({
    component: FolderPickerDialog,
    componentProps: { title: action === 'move' ? 'Move to…' : 'Copy to…', okLabel: action === 'move' ? 'Move here' : 'Copy here', initialLibraryId: libraryId.value, excludeId: nodes.length === 1 && nodes[0].type === 'folder' ? nodes[0].id : null },
  }).onOk(async ({ library_id, parent_id }) => {
    try {
      await Promise.all(nodes.map((n) => api.post(`/nodes/${n.id}/${action}`, { target_library_id: library_id, target_parent_id: parent_id })))
      $q.notify({ type: 'positive', message: action === 'move' ? 'Moved' : 'Copied' })
      load()
      auth.fetchMe()
    } catch (e) {
      $q.notify({ type: 'negative', message: errorMessage(e) })
    }
  })
}

async function toggleStar(node) {
  try {
    if (node.is_starred) await api.delete(`/nodes/${node.id}/star`)
    else await api.post(`/nodes/${node.id}/star`)
    node.is_starred = !node.is_starred
  } catch (e) {
    $q.notify({ type: 'negative', message: errorMessage(e) })
  }
}

function share(node) {
  $q.dialog({ component: ShareDialog, componentProps: { library: library.value, node } })
}
function shareCurrent() {
  $q.dialog({ component: ShareDialog, componentProps: { library: library.value, node: folder.value } })
}
function link(node) {
  $q.dialog({ component: ShareLinkDialog, componentProps: { library: library.value, node } })
}
function linkCurrent() {
  $q.dialog({ component: ShareLinkDialog, componentProps: { library: library.value, node: folder.value } })
}
function versions(node) {
  $q.dialog({ component: VersionsDialog, componentProps: { node, canWrite: canWrite.value } }).onOk(load)
}

// ---- uploads ----
function uploadFiles(files) {
  if (!files.length) return
  if (!canWrite.value) return $q.notify({ type: 'warning', message: 'You do not have write permission here.' })
  const max = auth.settings.max_upload_bytes
  const ok = [...files].filter((f) => {
    if (max && f.size > max) {
      $q.notify({ type: 'negative', message: `${f.name} exceeds the ${formatBytes(max)} upload limit` })
      return false
    }
    return true
  })
  uploader.value.enqueue(ok, { url: `/libraries/${libraryId.value}/upload`, fields: { parent_id: folderId.value } })
}
function onFilesPicked(e) {
  uploadFiles(e.target.files)
  e.target.value = ''
}
function onDragOver(e) {
  if (e.dataTransfer?.types?.includes('Files')) dragging.value = true
}
function onDrop(e) {
  dragging.value = false
  uploadFiles(e.dataTransfer.files)
}
let reloadTimer = null
function onUploaded() {
  clearTimeout(reloadTimer)
  reloadTimer = setTimeout(() => {
    load()
    auth.fetchMe()
  }, 300)
}

watch(() => [route.params.id, route.params.folderId], load, { immediate: true })
</script>
