<template>
  <q-page padding>
    <div class="fs-toolbar q-mb-md">
      <div class="page-title">{{ $t('libraries.title') }}</div>
      <q-space />
      <q-btn-toggle v-model="view" unelevated dense toggle-color="primary" color="white" text-color="grey-8" class="fs-card" :options="[{ icon: 'grid_view', value: 'grid' }, { icon: 'view_list', value: 'list' }]" />
      <q-btn color="primary" icon="add" :label="$t('libraries.new')" unelevated no-caps @click="createLibrary" />
    </div>

    <q-inner-loading :showing="loading" />

    <div v-if="!loading && libraries.length === 0" class="fs-card fs-empty">
      <q-icon name="inventory_2" size="64px" />
      <div class="q-mt-md">{{ $t('libraries.empty') }}</div>
    </div>

    <div v-else-if="view === 'grid'" class="row q-col-gutter-md">
      <div v-for="lib in libraries" :key="lib.id" class="col-12 col-sm-6 col-md-4 col-lg-3">
        <div class="fs-card is-clickable q-pa-md full-height" @click="open(lib)">
          <div class="row items-start no-wrap">
            <q-avatar :icon="lib.is_encrypted ? 'lock' : 'inventory_2'" :color="lib.is_encrypted ? 'amber-2' : 'blue-1'" :text-color="lib.is_encrypted ? 'amber-10' : 'primary'" size="46px" rounded />
            <div class="q-ml-md col ellipsis">
              <div class="text-subtitle1 text-weight-medium ellipsis">{{ lib.name }}</div>
              <div class="text-caption" style="color: var(--fs-text-muted)">{{ formatBytes(lib.size_bytes) }} · {{ lib.file_count }} {{ $t('libraries.files') }}</div>
            </div>
            <LibraryMenu :library="lib" @changed="load" />
          </div>
          <div v-if="lib.description" class="text-caption q-mt-sm ellipsis-2-lines" style="color: var(--fs-text-muted)">{{ lib.description }}</div>
          <div class="text-caption q-mt-sm" style="color: var(--fs-text-muted)"><q-icon name="schedule" size="14px" /> {{ timeAgo(lib.updated_at) }}</div>
        </div>
      </div>
    </div>

    <q-list v-else separator class="fs-card">
      <q-item v-for="lib in libraries" :key="lib.id" clickable @click="open(lib)">
        <q-item-section avatar><q-icon :name="lib.is_encrypted ? 'lock' : 'inventory_2'" :color="lib.is_encrypted ? 'amber-8' : 'primary'" /></q-item-section>
        <q-item-section>
          <q-item-label>{{ lib.name }}</q-item-label>
          <q-item-label caption>{{ lib.description }}</q-item-label>
        </q-item-section>
        <q-item-section side class="text-caption">{{ formatBytes(lib.size_bytes) }}</q-item-section>
        <q-item-section side class="text-caption">{{ timeAgo(lib.updated_at) }}</q-item-section>
        <q-item-section side><LibraryMenu :library="lib" @changed="load" /></q-item-section>
      </q-item>
    </q-list>
  </q-page>
</template>

<script setup>
import { ref, watch, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import { useQuasar } from 'quasar'
import { api } from '@/boot/axios'
import { formatBytes, timeAgo, errorMessage } from '@/utils/format'
import LibraryMenu from '@/components/LibraryMenu.vue'
import LibraryFormDialog from '@/components/LibraryFormDialog.vue'

const $q = useQuasar()
const router = useRouter()
const libraries = ref([])
const loading = ref(false)
const view = ref($q.localStorage.getItem('fs_lib_view') || 'grid')
watch(view, (v) => $q.localStorage.set('fs_lib_view', v))

async function load() {
  loading.value = true
  try {
    const { data } = await api.get('/libraries')
    libraries.value = data
  } catch (e) {
    $q.notify({ type: 'negative', message: errorMessage(e) })
  } finally {
    loading.value = false
  }
}
const open = (lib) => router.push({ name: 'library', params: { id: lib.id } })
const createLibrary = () => $q.dialog({ component: LibraryFormDialog }).onOk(load)
onMounted(load)
</script>
