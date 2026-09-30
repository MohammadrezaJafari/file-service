<template>
  <q-page padding>
    <div class="fs-toolbar q-mb-md">
      <q-btn flat round icon="arrow_back" :to="{ name: 'library', params: { id: libraryId } }" />
      <div class="page-title">{{ $t('wiki.title') }} <span class="text-subtitle1 text-weight-regular" style="color: var(--fs-text-muted)">· {{ library?.name }}</span></div>
      <q-space />
      <q-btn v-if="canWrite && current" flat color="primary" icon="edit" :label="$t('editor.edit')" @click="edit(current)" />
      <q-btn v-if="canWrite" color="primary" unelevated icon="add" :label="$t('wiki.newPage')" no-caps @click="newPage" />
    </div>

    <div class="row q-col-gutter-md">
      <div class="col-12 col-md-3">
        <div class="fs-card">
          <div class="text-caption text-weight-medium q-pa-md q-pb-xs" style="color: var(--fs-text-muted)">{{ $t('wiki.pages') }}</div>
          <q-list dense class="q-pb-sm">
            <q-item v-for="p in pages" :key="p.id" clickable :active="current?.id === p.id" class="fs-nav-item" :class="{ 'fs-nav-active': current?.id === p.id }" @click="select(p)">
              <q-item-section avatar><q-icon name="article" /></q-item-section>
              <q-item-section>
                <q-item-label>{{ p.name.replace(/\.md$/i, '') }}</q-item-label>
                <q-item-label caption class="mono">{{ p.path }}</q-item-label>
              </q-item-section>
            </q-item>
            <q-item v-if="!pages.length && !loading"><q-item-section class="text-caption" style="color: var(--fs-text-muted)">{{ $t('wiki.empty') }}</q-item-section></q-item>
          </q-list>
        </div>
      </div>
      <div class="col-12 col-md-9">
        <div class="fs-card q-pa-xl fs-markdown" style="min-height: 400px">
          <q-inner-loading :showing="loadingContent" />
          <div v-if="current" v-html="html" />
          <div v-else class="fs-empty"><q-icon name="menu_book" size="56px" /><div class="q-mt-sm">{{ $t('wiki.select') }}</div></div>
        </div>
      </div>
    </div>
  </q-page>
</template>

<script setup>
import { ref, computed, onMounted, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useQuasar } from 'quasar'
import { useI18n } from 'vue-i18n'
import { api } from '@/boot/axios'
import { renderMarkdown } from '@/utils/markdown'
import { errorMessage } from '@/utils/format'
import MarkdownEditorDialog from '@/components/editors/MarkdownEditorDialog.vue'

const route = useRoute()
const router = useRouter()
const $q = useQuasar()
const { t } = useI18n()
const libraryId = computed(() => Number(route.params.id))
const library = ref(null)
const pages = ref([])
const current = ref(null)
const text = ref('')
const loading = ref(false)
const loadingContent = ref(false)
const html = computed(() => renderMarkdown(text.value))
const canWrite = computed(() => library.value?.permission === 'rw' && (!library.value?.is_encrypted || library.value?.is_unlocked))

async function load() {
  loading.value = true
  try {
    const [lib, res] = await Promise.all([api.get(`/libraries/${libraryId.value}`), api.get(`/libraries/${libraryId.value}/search`, { params: { q: '.md' } })])
    library.value = lib.data
    pages.value = res.data.filter((n) => n.type === 'file' && /\.md$/i.test(n.name)).sort((a, b) => a.path.localeCompare(b.path))
    const wanted = route.params.pageId ? pages.value.find((p) => p.id === Number(route.params.pageId)) : pages.value[0]
    if (wanted) select(wanted, false)
  } catch (e) {
    $q.notify({ type: 'negative', message: errorMessage(e) })
  } finally {
    loading.value = false
  }
}
async function select(p, navigate = true) {
  current.value = p
  if (navigate) router.replace({ name: 'wiki', params: { id: libraryId.value, pageId: p.id } })
  loadingContent.value = true
  try {
    const { data } = await api.get(`/nodes/${p.id}/content`, { responseType: 'text', transformResponse: [(d) => d] })
    text.value = data
  } catch (e) {
    text.value = ''
    $q.notify({ type: 'negative', message: errorMessage(e) })
  } finally {
    loadingContent.value = false
  }
}
function edit(p) {
  $q.dialog({ component: MarkdownEditorDialog, componentProps: { node: p, canWrite: canWrite.value } }).onOk(() => select(p, false))
}
function newPage() {
  $q.dialog({ title: t('editor.newPage'), prompt: { model: '', label: t('editor.pageName'), isValid: (v) => !!v.trim() }, cancel: t('common.cancel'), ok: t('common.ok') }).onOk(async (name) => {
    try {
      const { data } = await api.post(`/libraries/${libraryId.value}/files`, { name: name.endsWith('.md') ? name : `${name}.md`, content: `# ${name.replace(/\.md$/, '')}\n\n` })
      await load()
      $q.dialog({ component: MarkdownEditorDialog, componentProps: { node: data, canWrite: true } }).onOk(load)
    } catch (e) {
      $q.notify({ type: 'negative', message: errorMessage(e) })
    }
  })
}
watch(() => route.params.id, load)
onMounted(load)
</script>
