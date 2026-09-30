<template>
  <q-page padding>
    <div class="page-title q-mb-md">Shared with me</div>
    <q-inner-loading :showing="loading" />
    <div v-if="!loading && !items.length" class="text-center text-grey-6 q-pa-xl">
      <q-icon name="folder_shared" size="64px" />
      <div class="q-mt-md">Nothing has been shared with you yet.</div>
    </div>
    <q-list v-else bordered separator class="rounded-borders">
      <q-item v-for="it in items" :key="it.share_id" clickable @click="open(it)">
        <q-item-section avatar><q-icon :name="it.folder ? 'folder_shared' : 'inventory_2'" color="primary" /></q-item-section>
        <q-item-section>
          <q-item-label>{{ it.folder ? it.folder.name : it.library.name }}</q-item-label>
          <q-item-label caption>
            <span v-if="it.folder">in {{ it.library.name }} · </span>shared by {{ it.shared_by }}<span v-if="it.via_group_id"> (via group)</span>
          </q-item-label>
        </q-item-section>
        <q-item-section side><q-chip dense :color="it.permission === 'rw' ? 'green-1' : 'grey-3'" :text-color="it.permission === 'rw' ? 'green-9' : 'grey-8'" :label="it.permission === 'rw' ? 'Read / Write' : 'Read only'" /></q-item-section>
        <q-item-section side>
          <q-btn v-if="!it.via_group_id" flat round dense icon="close" @click.stop="leave(it)"><q-tooltip>Remove from my list</q-tooltip></q-btn>
        </q-item-section>
      </q-item>
    </q-list>
  </q-page>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import { useQuasar } from 'quasar'
import { api } from '@/boot/axios'
import { errorMessage } from '@/utils/format'

const router = useRouter()
const $q = useQuasar()
const items = ref([])
const loading = ref(false)

async function load() {
  loading.value = true
  try {
    const { data } = await api.get('/libraries/shared')
    items.value = data.data
  } finally {
    loading.value = false
  }
}
function open(it) {
  if (it.folder) router.push({ name: 'folder', params: { id: it.library.id, folderId: it.folder.id } })
  else router.push({ name: 'library', params: { id: it.library.id } })
}
function leave(it) {
  $q.dialog({ title: 'Remove share', message: 'Remove this item from your shared list?', cancel: true }).onOk(async () => {
    try {
      await api.delete(`/shares/${it.share_id}`)
      load()
    } catch (e) {
      $q.notify({ type: 'negative', message: errorMessage(e) })
    }
  })
}
onMounted(load)
</script>
