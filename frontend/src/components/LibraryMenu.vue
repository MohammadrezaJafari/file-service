<template>
  <q-btn flat round dense icon="more_vert" @click.stop>
    <q-menu>
      <q-list dense style="min-width: 180px">
        <q-item v-if="library.is_owner" clickable v-close-popup @click="share">
          <q-item-section avatar><q-icon name="share" /></q-item-section>
          <q-item-section>Share</q-item-section>
        </q-item>
        <q-item clickable v-close-popup @click="link">
          <q-item-section avatar><q-icon name="link" /></q-item-section>
          <q-item-section>Get link</q-item-section>
        </q-item>
        <q-item v-if="library.is_owner" clickable v-close-popup @click="rename">
          <q-item-section avatar><q-icon name="edit" /></q-item-section>
          <q-item-section>Rename</q-item-section>
        </q-item>
        <q-item clickable v-close-popup :to="{ name: 'trash', params: { id: library.id } }">
          <q-item-section avatar><q-icon name="delete_outline" /></q-item-section>
          <q-item-section>Trash</q-item-section>
        </q-item>
        <q-separator v-if="library.is_owner" />
        <q-item v-if="library.is_owner" clickable v-close-popup class="text-negative" @click="remove">
          <q-item-section avatar><q-icon name="delete" /></q-item-section>
          <q-item-section>Delete library</q-item-section>
        </q-item>
      </q-list>
    </q-menu>
  </q-btn>
</template>

<script setup>
import { useQuasar } from 'quasar'
import { api } from '@/boot/axios'
import { errorMessage } from '@/utils/format'
import LibraryFormDialog from '@/components/LibraryFormDialog.vue'
import ShareDialog from '@/components/ShareDialog.vue'
import ShareLinkDialog from '@/components/ShareLinkDialog.vue'

const props = defineProps({ library: { type: Object, required: true } })
const emit = defineEmits(['changed'])
const $q = useQuasar()

function rename() {
  $q.dialog({ component: LibraryFormDialog, componentProps: { library: props.library } }).onOk(() => emit('changed'))
}
function share() {
  $q.dialog({ component: ShareDialog, componentProps: { library: props.library } })
}
function link() {
  $q.dialog({ component: ShareLinkDialog, componentProps: { library: props.library } })
}
function remove() {
  $q.dialog({
    title: 'Delete library',
    message: `Delete "${props.library.name}" and all of its files? This cannot be undone.`,
    cancel: true,
    ok: { label: 'Delete', color: 'negative', unelevated: true },
  }).onOk(async () => {
    try {
      await api.delete(`/libraries/${props.library.id}`)
      $q.notify({ type: 'positive', message: 'Library deleted' })
      emit('changed')
    } catch (e) {
      $q.notify({ type: 'negative', message: errorMessage(e) })
    }
  })
}
</script>
