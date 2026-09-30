<template>
  <q-page padding>
    <div class="row items-center q-mb-md">
      <div class="page-title">{{ $t('libraries.title') }}</div>
      <q-space />
      <q-btn-toggle v-model="view" flat dense toggle-color="primary" :options="[{ icon: 'grid_view', value: 'grid' }, { icon: 'view_list', value: 'list' }]" class="q-mr-sm" />
      <q-btn color="primary" icon="add" :label="$t('libraries.new')" unelevated @click="createLibrary" />
    </div>

    <q-inner-loading :showing="loading" />

    <div v-if="!loading && libraries.length === 0" class="text-center text-grey-6 q-pa-xl">
      <q-icon name="inventory_2" size="64px" />
      <div class="q-mt-md">{{ $t('libraries.empty') }}</div>
    </div>

    <div v-else-if="view === 'grid'" class="row q-col-gutter-md">
      <div v-for="lib in libraries" :key="lib.id" class="col-12 col-sm-6 col-md-4 col-lg-3">
        <q-card class="cursor-pointer full-height" bordered flat @click="open(lib)">
          <q-card-section class="row items-center no-wrap">
            <q-avatar :icon="lib.is_encrypted ? 'lock' : 'inventory_2'" color="blue-1" text-color="primary" />
            <div class="q-ml-md ellipsis">
              <div class="text-subtitle1 ellipsis">{{ lib.name }}</div>
              <div class="text-caption text-grey-7">{{ formatBytes(lib.size_bytes) }} · {{ lib.file_count }} {{ $t('libraries.files') }}</div>
            </div>
            <q-space />
            <LibraryMenu :library="lib" @changed="load" />
          </q-card-section>
          <q-card-section v-if="lib.description" class="q-pt-none text-caption text-grey-8 ellipsis-2-lines">{{ lib.description }}</q-card-section>
        </q-card>
      </div>
    </div>

    <q-list v-else bordered separator class="rounded-borders">
      <q-item v-for="lib in libraries" :key="lib.id" clickable @click="open(lib)">
        <q-item-section avatar><q-icon :name="lib.is_encrypted ? 'lock' : 'inventory_2'" color="primary" /></q-item-section>
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
import { ref, onMounted } from 'vue'
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

function open(lib) {
  router.push({ name: 'library', params: { id: lib.id } })
}

function createLibrary() {
  $q.dialog({ component: LibraryFormDialog }).onOk(load)
}

onMounted(load)
import { watch } from 'vue'
watch(view, (v) => $q.localStorage.set('fs_lib_view', v))
</script>
