<template>
  <q-dialog ref="dialogRef" @hide="onDialogHide">
    <q-card class="fs-card" style="min-width: 480px; max-width: 95vw">
      <q-card-section>
        <div class="text-h6">{{ title || $t('picker.select') }}</div>
      </q-card-section>
      <q-card-section class="q-pt-none">
        <q-select v-model="libraryId" outlined dense :label="$t('picker.library')" :options="libraries" option-label="name" option-value="id" emit-value map-options @update:model-value="reset" />
      </q-card-section>
      <q-card-section class="q-pt-none">
        <q-breadcrumbs class="q-mb-sm text-caption">
          <q-breadcrumbs-el :label="$t('picker.root')" icon="home" class="cursor-pointer" @click="goTo(null)" />
          <q-breadcrumbs-el v-for="b in breadcrumbs" :key="b.id" :label="b.name" class="cursor-pointer" @click="goTo(b.id)" />
        </q-breadcrumbs>
        <q-list bordered separator dense style="max-height: 300px; overflow: auto">
          <q-item v-for="f in folders" :key="f.id" clickable :disable="f.id === excludeId" @click="goTo(f.id)">
            <q-item-section avatar><q-icon name="folder" color="amber-7" /></q-item-section>
            <q-item-section>{{ f.name }}</q-item-section>
            <q-item-section side><q-icon name="chevron_right" /></q-item-section>
          </q-item>
          <q-item v-if="!folders.length && !loading"><q-item-section class="text-grey-6">{{ $t('picker.noSubfolders') }}</q-item-section></q-item>
        </q-list>
      </q-card-section>
      <q-card-actions align="right">
        <q-btn flat :label="$t('common.cancel')" @click="onDialogCancel" />
        <q-btn color="primary" unelevated :label="okLabel || $t('picker.select')" :disable="currentId === excludeId" @click="onDialogOK({ library_id: libraryId, parent_id: currentId })" />
      </q-card-actions>
    </q-card>
  </q-dialog>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import { useDialogPluginComponent } from 'quasar'
import { api } from '@/boot/axios'

const props = defineProps({
  title: { type: String, default: '' },
  okLabel: { type: String, default: '' },
  initialLibraryId: { type: Number, default: null },
  excludeId: { type: Number, default: null },
})
defineEmits([...useDialogPluginComponent.emits])
const { dialogRef, onDialogHide, onDialogOK, onDialogCancel } = useDialogPluginComponent()

const libraries = ref([])
const libraryId = ref(props.initialLibraryId)
const currentId = ref(null)
const folders = ref([])
const breadcrumbs = ref([])
const loading = ref(false)

async function loadLibraries() {
  const [mine, shared] = await Promise.all([api.get('/libraries'), api.get('/libraries/shared')])
  const writable = shared.data.data.filter((s) => s.permission === 'rw' && !s.folder).map((s) => s.library)
  libraries.value = [...mine.data, ...writable]
  if (!libraryId.value && libraries.value.length) libraryId.value = libraries.value[0].id
}

async function goTo(id) {
  currentId.value = id
  loading.value = true
  try {
    const { data } = await api.get(`/libraries/${libraryId.value}/nodes`, { params: { parent_id: id || undefined } })
    folders.value = data.items.filter((n) => n.type === 'folder')
    breadcrumbs.value = data.breadcrumbs
  } finally {
    loading.value = false
  }
}

function reset() {
  goTo(null)
}

onMounted(async () => {
  await loadLibraries()
  if (libraryId.value) goTo(null)
})
</script>
