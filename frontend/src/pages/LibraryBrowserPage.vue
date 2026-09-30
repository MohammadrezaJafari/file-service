<template>
  <q-page padding class="drop-zone" :class="{ 'is-dragging': dragging && canWrite }" @dragover.prevent="onDragOver" @dragleave.prevent="dragging = false" @drop.prevent="onDrop">
    <!-- Header -->
    <div class="fs-toolbar q-mb-md">
      <q-breadcrumbs class="text-subtitle1" active-color="primary" separator-color="grey-5">
        <q-breadcrumbs-el :icon="library?.is_encrypted ? 'lock' : 'inventory_2'" :label="library?.name || '…'" :to="{ name: 'library', params: { id: libraryId } }" />
        <q-breadcrumbs-el v-for="b in breadcrumbs" :key="b.id" :label="b.name" :to="{ name: 'folder', params: { id: libraryId, folderId: b.id } }" />
      </q-breadcrumbs>
      <q-chip v-if="permission === 'r'" dense color="grey-3" icon="visibility" :label="$t('common.readOnly')" />
      <q-chip v-if="library?.is_encrypted" dense :color="unlocked ? 'green-1' : 'amber-2'" :text-color="unlocked ? 'green-9' : 'amber-10'" :icon="unlocked ? 'lock_open' : 'lock'" :label="$t('encrypted.badge')" clickable @click="unlocked ? lockLibrary() : askUnlock()" />
      <q-space />

      <q-input v-model="search" dense outlined :placeholder="$t('browser.search')" debounce="400" style="width: 230px" clearable bg-color="white" @update:model-value="doSearch">
        <template #prepend><q-icon name="search" /></template>
      </q-input>

      <q-btn-toggle v-model="view" unelevated dense toggle-color="primary" color="white" text-color="grey-8" :options="viewOptions" class="fs-card" style="border-radius: 10px" />

      <q-btn-dropdown v-if="canWrite" color="primary" unelevated icon="add" :label="$t('newMenu.title')" no-caps>
        <q-list dense style="min-width: 200px">
          <q-item clickable v-close-popup @click="fileInput.click()"><q-item-section avatar><q-icon name="upload" /></q-item-section><q-item-section>{{ $t('newMenu.upload') }}</q-item-section></q-item>
          <q-item clickable v-close-popup @click="newFolder"><q-item-section avatar><q-icon name="create_new_folder" /></q-item-section><q-item-section>{{ $t('newMenu.folder') }}</q-item-section></q-item>
          <q-item clickable v-close-popup @click="newPage"><q-item-section avatar><q-icon name="article" /></q-item-section><q-item-section>{{ $t('newMenu.page') }}</q-item-section></q-item>
          <q-item clickable v-close-popup @click="newBoard"><q-item-section avatar><q-icon name="draw" /></q-item-section><q-item-section>{{ $t('newMenu.board') }}</q-item-section></q-item>
        </q-list>
      </q-btn-dropdown>

      <q-btn flat round icon="more_vert" :aria-label="$t('common.actions')">
        <q-menu>
          <q-list dense style="min-width: 220px">
            <q-item v-if="library?.is_owner" clickable v-close-popup @click="shareCurrent"><q-item-section avatar><q-icon name="share" /></q-item-section><q-item-section>{{ folder ? $t('browser.shareThis') : $t('browser.shareLibrary') }}</q-item-section></q-item>
            <q-item v-if="!library?.is_encrypted" clickable v-close-popup @click="linkCurrent"><q-item-section avatar><q-icon name="link" /></q-item-section><q-item-section>{{ $t('common.getLink') }}</q-item-section></q-item>
            <q-item v-if="folder" clickable v-close-popup @click="download(folder)"><q-item-section avatar><q-icon name="download" /></q-item-section><q-item-section>{{ $t('browser.downloadZip') }}</q-item-section></q-item>
            <q-separator />
            <q-item clickable v-close-popup :to="{ name: 'wiki', params: { id: libraryId } }"><q-item-section avatar><q-icon name="menu_book" /></q-item-section><q-item-section>{{ $t('wiki.title') }}</q-item-section></q-item>
            <q-item v-if="canWrite" clickable v-close-popup @click="manageTags"><q-item-section avatar><q-icon name="label" /></q-item-section><q-item-section>{{ $t('tags.manage') }}</q-item-section></q-item>
            <q-item v-if="library?.is_owner" clickable v-close-popup @click="manageProps"><q-item-section avatar><q-icon name="tune" /></q-item-section><q-item-section>{{ $t('props.manage') }}</q-item-section></q-item>
            <q-separator />
            <q-item clickable v-close-popup :to="{ name: 'trash', params: { id: libraryId } }"><q-item-section avatar><q-icon name="delete_outline" /></q-item-section><q-item-section>{{ $t('common.trash') }}</q-item-section></q-item>
          </q-list>
        </q-menu>
      </q-btn>
      <input ref="fileInput" type="file" multiple class="hidden" @change="onFilesPicked" />
    </div>

    <!-- Tag filter -->
    <div v-if="tags.length && view !== 'stats'" class="row items-center q-gutter-xs q-mb-md">
      <q-icon name="label" size="18px" style="color: var(--fs-text-muted)" />
      <q-chip dense clickable :color="!tagFilter ? 'primary' : 'grey-3'" :text-color="!tagFilter ? 'white' : 'grey-9'" :label="$t('tags.all')" @click="setTag(null)" />
      <q-chip v-for="t in tags" :key="t.id" dense clickable :style="tagFilter === t.id ? { background: t.color, color: '#fff' } : {}" :color="tagFilter === t.id ? undefined : 'grey-3'" :text-color="tagFilter === t.id ? undefined : 'grey-9'" :icon="t.parent_id ? 'subdirectory_arrow_right' : undefined" :label="t.name" @click="setTag(t.id)" />
    </div>

    <!-- Locked -->
    <div v-if="library?.is_encrypted && !unlocked" class="fs-card fs-empty">
      <q-icon name="lock" size="64px" color="amber-7" />
      <div class="text-subtitle1 q-mt-sm">{{ $t('encrypted.hint') }}</div>
      <q-btn color="primary" unelevated class="q-mt-md" icon="lock_open" :label="$t('encrypted.unlock')" @click="askUnlock" />
    </div>

    <template v-else>
      <!-- Bulk toolbar -->
      <q-slide-transition>
        <div v-if="selected.length" class="row items-center q-gutter-sm q-mb-sm q-pa-sm rounded-borders" style="background: var(--fs-primary-soft)">
          <div class="text-primary text-weight-medium">{{ $t('browser.selected', { n: selected.length }) }}</div>
          <q-btn flat dense icon="download" :label="$t('common.download')" @click="selected.forEach(download)" />
          <q-btn v-if="canWrite" flat dense icon="drive_file_move" :label="$t('common.move')" @click="moveCopy(selected, 'move')" />
          <q-btn flat dense icon="file_copy" :label="$t('common.copy')" @click="moveCopy(selected, 'copy')" />
          <q-btn v-if="canWrite" flat dense icon="delete" :label="$t('common.delete')" color="negative" @click="remove(selected)" />
          <q-space />
          <q-btn flat dense round icon="close" @click="selected = []" />
        </div>
      </q-slide-transition>

      <!-- LIST -->
      <q-table
        v-if="view === 'list'"
        flat
        class="fs-table"
        :rows="rows"
        :columns="columns"
        row-key="id"
        :loading="loading"
        selection="multiple"
        v-model:selected="selected"
        :pagination="{ rowsPerPage: 0 }"
        hide-pagination
        binary-state-sort
        :no-data-label="searchMode ? $t('browser.noResults') : $t('browser.empty')"
      >
        <template #body-cell-name="p">
          <q-td :props="p" class="file-row" @click="open(p.row)">
            <div class="row items-center no-wrap">
              <q-icon :name="fileIcon(p.row)" :color="fileColor(p.row)" size="26px" class="q-mr-sm" />
              <div class="ellipsis">
                <div class="row items-center no-wrap">
                  <span class="ellipsis">{{ p.row.name }}</span>
                  <q-icon v-if="p.row.is_starred" name="star" color="amber" size="16px" class="q-ml-xs" />
                </div>
                <div v-if="searchMode" class="text-caption mono" style="color: var(--fs-text-muted)">{{ p.row.path }}</div>
                <div v-else-if="p.row.tags?.length" class="q-mt-xs">
                  <q-badge v-for="t in p.row.tags" :key="t.id" :style="{ background: t.color }" class="q-mr-xs">{{ t.name }}</q-badge>
                </div>
              </div>
            </div>
          </q-td>
        </template>
        <template #body-cell-size="p"><q-td :props="p" style="color: var(--fs-text-muted)">{{ p.row.type === 'file' ? formatBytes(p.row.size) : '—' }}</q-td></template>
        <template #body-cell-updated_at="p">
          <q-td :props="p" style="color: var(--fs-text-muted)">{{ timeAgo(p.row.updated_at) }}<q-tooltip>{{ formatDate(p.row.updated_at) }}<span v-if="p.row.updater"> · {{ p.row.updater.name }}</span></q-tooltip></q-td>
        </template>
        <template #body-cell-actions="p">
          <q-td :props="p" auto-width @click.stop>
            <q-btn flat round dense icon="more_horiz" :aria-label="$t('common.actions')"><NodeMenu :node="p.row" :library="library" :can-write="canWrite" v-bind="menuHandlers" /></q-btn>
          </q-td>
        </template>
      </q-table>

      <!-- GALLERY -->
      <GalleryView v-else-if="view === 'gallery'" :items="rows" @open="open" @menu="openGalleryMenu" />
      <q-menu v-if="menuNode" v-model="menuOpen" :target="menuTarget" @hide="menuNode = null">
        <NodeMenu :node="menuNode" :library="library" :can-write="canWrite" v-bind="menuHandlers" inline />
      </q-menu>

      <!-- KANBAN -->
      <KanbanView v-else-if="view === 'kanban'" :items="rows" :definitions="definitions" :can-write="canWrite" @open="details" @changed="load" />

      <!-- STATS -->
      <StatsView v-else-if="view === 'stats'" :library-id="libraryId" />

      <div v-if="canWrite && !rows.length && !loading && !searchMode && view === 'list'" class="fs-empty">
        <q-icon name="cloud_upload" size="56px" />
        <div class="q-mt-sm">{{ $t('browser.dropHint') }}</div>
      </div>
    </template>

    <UploadQueue ref="uploader" @uploaded="onUploaded" />
  </q-page>
</template>

<script setup>
import { ref, computed, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useQuasar } from 'quasar'
import { useI18n } from 'vue-i18n'
import { api } from '@/boot/axios'
import { useAuthStore } from '@/stores/auth'
import { formatBytes, formatDate, timeAgo, fileIcon, fileColor, errorMessage, isPreviewable, editorKind } from '@/utils/format'
import { downloadNode, signedUrl } from '@/utils/download'
import UploadQueue from '@/components/UploadQueue.vue'
import NodeMenu from '@/components/NodeMenu.vue'
import ShareDialog from '@/components/ShareDialog.vue'
import ShareLinkDialog from '@/components/ShareLinkDialog.vue'
import FolderPickerDialog from '@/components/FolderPickerDialog.vue'
import VersionsDialog from '@/components/VersionsDialog.vue'
import PreviewDialog from '@/components/PreviewDialog.vue'
import UnlockDialog from '@/components/UnlockDialog.vue'
import TagsManagerDialog from '@/components/TagsManagerDialog.vue'
import PropertiesDialog from '@/components/PropertiesDialog.vue'
import NodeDetailsDialog from '@/components/NodeDetailsDialog.vue'
import MarkdownEditorDialog from '@/components/editors/MarkdownEditorDialog.vue'
import WhiteboardDialog from '@/components/editors/WhiteboardDialog.vue'
import GalleryView from '@/components/views/GalleryView.vue'
import KanbanView from '@/components/views/KanbanView.vue'
import StatsView from '@/components/views/StatsView.vue'

const route = useRoute()
const router = useRouter()
const $q = useQuasar()
const auth = useAuthStore()
const { t } = useI18n()

const libraryId = computed(() => Number(route.params.id))
const folderId = computed(() => (route.params.folderId ? Number(route.params.folderId) : null))

const library = ref(null)
const folder = ref(null)
const breadcrumbs = ref([])
const permission = ref(null)
const unlocked = ref(true)
const tags = ref([])
const definitions = ref([])
const tagFilter = ref(null)
const items = ref([])
const searchResults = ref(null)
const search = ref('')
const loading = ref(false)
const selected = ref([])
const dragging = ref(false)
const fileInput = ref(null)
const uploader = ref(null)
const view = ref($q.localStorage.getItem('fs_view') || 'list')
const menuNode = ref(null)
const menuOpen = ref(false)
const menuTarget = ref(null)

watch(view, (v) => $q.localStorage.set('fs_view', v))

const canWrite = computed(() => permission.value === 'rw' && unlocked.value)
const searchMode = computed(() => searchResults.value !== null)
const rows = computed(() => searchResults.value ?? items.value)
const viewOptions = computed(() =>
  [
    ['list', 'view_list'],
    ['gallery', 'grid_view'],
    ['kanban', 'view_kanban'],
    ['stats', 'insights'],
  ].map(([value, icon]) => ({ value, icon, attrs: { 'aria-label': t(`views.${value}`), title: t(`views.${value}`) } })),
)
const columns = computed(() => [
  { name: 'name', label: t('common.name'), field: 'name', align: 'left', sortable: true },
  { name: 'size', label: t('common.size'), field: 'size', align: 'right', sortable: true, style: 'width: 110px' },
  { name: 'updated_at', label: t('common.modified'), field: 'updated_at', align: 'right', sortable: true, style: 'width: 160px' },
  { name: 'actions', label: '', field: 'id', align: 'right' },
])

async function load() {
  loading.value = true
  selected.value = []
  try {
    const { data } = await api.get(`/libraries/${libraryId.value}/nodes`, { params: { parent_id: folderId.value || undefined, tag_id: tagFilter.value || undefined } })
    library.value = data.library
    folder.value = data.folder
    breadcrumbs.value = data.breadcrumbs
    permission.value = data.permission
    unlocked.value = data.is_unlocked
    tags.value = data.tags
    definitions.value = data.property_definitions
    items.value = data.items
    if (library.value.is_encrypted && !unlocked.value) askUnlock()
  } catch (e) {
    $q.notify({ type: 'negative', message: errorMessage(e) })
    if (e.response?.status === 403 || e.response?.status === 404) router.replace({ name: 'libraries' })
  } finally {
    loading.value = false
  }
}

function setTag(id) {
  tagFilter.value = id
  load()
}

async function doSearch(q) {
  if (!q) return (searchResults.value = null)
  loading.value = true
  try {
    const { data } = await api.get(`/libraries/${libraryId.value}/search`, { params: { q } })
    searchResults.value = data
  } finally {
    loading.value = false
  }
}

// ---- encryption ----
let unlockOpen = false
function askUnlock() {
  if (unlockOpen) return
  unlockOpen = true
  $q.dialog({ component: UnlockDialog, componentProps: { library: library.value } })
    .onOk(load)
    .onDismiss(() => (unlockOpen = false))
}
async function lockLibrary() {
  await api.post(`/libraries/${libraryId.value}/lock`)
  $q.notify({ type: 'info', message: t('encrypted.locked') })
  load()
}

// ---- opening ----
function open(node) {
  if (node.type === 'folder') {
    search.value = ''
    searchResults.value = null
    router.push({ name: 'folder', params: { id: libraryId.value, folderId: node.id } })
    return
  }
  const kind = editorKind(node)
  if (kind === 'markdown') return $q.dialog({ component: MarkdownEditorDialog, componentProps: { node, canWrite: canWrite.value } }).onOk(load)
  if (kind === 'whiteboard') return $q.dialog({ component: WhiteboardDialog, componentProps: { node, canWrite: canWrite.value } }).onOk(load)
  if (isPreviewable(node)) return $q.dialog({ component: PreviewDialog, componentProps: { node, resolveUrl: (n) => signedUrl(n, { inline: true }) } })
  download(node)
}
function details(node) {
  $q.dialog({ component: NodeDetailsDialog, componentProps: { node, tags: tags.value, definitions: definitions.value, canWrite: canWrite.value } }).onOk(load)
}
function openGalleryMenu(node, ev) {
  menuNode.value = node
  menuTarget.value = ev.currentTarget
  menuOpen.value = true
}

async function download(node) {
  try {
    await downloadNode(node)
  } catch (e) {
    $q.notify({ type: 'negative', message: errorMessage(e) })
  }
}

// ---- creating ----
function prompt(title, label, cb, model = '') {
  $q.dialog({ title, prompt: { model, type: 'text', label, isValid: (v) => !!v.trim() }, cancel: t('common.cancel'), ok: t('common.ok') }).onOk(async (v) => {
    try {
      await cb(v.trim())
      load()
    } catch (e) {
      $q.notify({ type: 'negative', message: errorMessage(e) })
    }
  })
}
const newFolder = () => prompt(t('browser.newFolder'), t('browser.folderName'), (name) => api.post(`/libraries/${libraryId.value}/folders`, { name, parent_id: folderId.value }))
const newPage = () =>
  prompt(t('editor.newPage'), t('editor.pageName'), async (name) => {
    const { data } = await api.post(`/libraries/${libraryId.value}/files`, { name: name.endsWith('.md') ? name : `${name}.md`, parent_id: folderId.value, content: `# ${name.replace(/\.md$/, '')}\n\n` })
    $q.dialog({ component: MarkdownEditorDialog, componentProps: { node: data, canWrite: true } }).onOk(load)
  })
const newBoard = () =>
  prompt(t('editor.newBoard'), t('editor.boardName'), async (name) => {
    const { data } = await api.post(`/libraries/${libraryId.value}/files`, { name: name.endsWith('.excalidraw') ? name : `${name}.excalidraw`, parent_id: folderId.value, content: '', mime_type: 'application/vnd.excalidraw+json' })
    $q.dialog({ component: WhiteboardDialog, componentProps: { node: data, canWrite: true } }).onOk(load)
  })
const rename = (node) => prompt(t('common.rename'), t('common.name'), (name) => api.patch(`/nodes/${node.id}`, { name }), node.name)

function remove(nodes) {
  $q.dialog({
    title: t('browser.trashTitle'),
    message: nodes.length === 1 ? t('browser.trashOne', { name: nodes[0].name }) : t('browser.trashMany', { n: nodes.length }),
    cancel: t('common.cancel'),
    ok: { label: t('common.delete'), color: 'negative', unelevated: true },
  }).onOk(async () => {
    try {
      await Promise.all(nodes.map((n) => api.delete(`/nodes/${n.id}`)))
      $q.notify({ type: 'positive', message: t('browser.movedToTrash'), actions: [{ label: t('common.trash'), color: 'white', handler: () => router.push({ name: 'trash', params: { id: libraryId.value } }) }] })
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
    componentProps: { title: action === 'move' ? t('browser.moveTo') : t('browser.copyTo'), okLabel: action === 'move' ? t('browser.moveHere') : t('browser.copyHere'), initialLibraryId: libraryId.value, excludeId: nodes.length === 1 && nodes[0].type === 'folder' ? nodes[0].id : null },
  }).onOk(async ({ library_id, parent_id }) => {
    try {
      await Promise.all(nodes.map((n) => api.post(`/nodes/${n.id}/${action}`, { target_library_id: library_id, target_parent_id: parent_id })))
      $q.notify({ type: 'positive', message: action === 'move' ? t('browser.moved') : t('browser.copied') })
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

const share = (node) => $q.dialog({ component: ShareDialog, componentProps: { library: library.value, node } })
const shareCurrent = () => share(folder.value)
const link = (node) => $q.dialog({ component: ShareLinkDialog, componentProps: { library: library.value, node } })
const linkCurrent = () => link(folder.value)
const versions = (node) => $q.dialog({ component: VersionsDialog, componentProps: { node, canWrite: canWrite.value } }).onOk(load)
const manageTags = () => $q.dialog({ component: TagsManagerDialog, componentProps: { library: library.value } }).onDismiss(load)
const manageProps = () => $q.dialog({ component: PropertiesDialog, componentProps: { library: library.value } }).onOk(load)

const menuHandlers = {
  onOpen: open,
  onDownload: download,
  onStar: toggleStar,
  onLink: link,
  onShare: share,
  onVersions: versions,
  onDetails: details,
  onRename: rename,
  onMove: (n) => moveCopy([n], 'move'),
  onCopy: (n) => moveCopy([n], 'copy'),
  onDelete: (n) => remove([n]),
}

// ---- uploads ----
function uploadFiles(files) {
  if (!files.length) return
  if (!canWrite.value) return $q.notify({ type: 'warning', message: t('browser.noWrite') })
  const max = auth.settings.max_upload_bytes
  const ok = [...files].filter((f) => {
    if (max && f.size > max) {
      $q.notify({ type: 'negative', message: t('browser.tooBig', { name: f.name, limit: formatBytes(max) }) })
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

watch(
  () => [route.params.id, route.params.folderId],
  () => {
    tagFilter.value = null
    load()
  },
  { immediate: true },
)
</script>
