<template>
  <q-dialog ref="dialogRef" maximized @hide="onDialogHide">
    <q-card class="bg-grey-10 text-white column">
      <q-bar class="bg-grey-9">
        <q-icon :name="fileIcon(node)" />
        <div class="ellipsis q-ml-sm">{{ node.name }}</div>
        <q-space />
        <q-btn v-if="url" flat dense icon="download" :label="$t('common.download')" @click="download" />
        <q-btn flat dense round icon="close" v-close-popup />
      </q-bar>
      <q-card-section class="col flex flex-center scroll">
        <q-spinner v-if="!url" size="48px" color="white" />
        <img v-else-if="kind === 'image'" :src="url" style="max-width: 100%; max-height: 85vh" />
        <video v-else-if="kind === 'video'" :src="url" controls autoplay style="max-width: 100%; max-height: 85vh" />
        <audio v-else-if="kind === 'audio'" :src="url" controls />
        <iframe v-else-if="kind === 'pdf'" :src="url" style="width: 100%; height: 88vh; border: 0; background: white" />
        <pre v-else-if="kind === 'text'" class="bg-grey-9 q-pa-md rounded-borders full-width" style="white-space: pre-wrap; max-height: 85vh; overflow: auto">{{ text }}</pre>
        <div v-else class="text-center">
          <q-icon :name="fileIcon(node)" size="96px" />
          <div class="q-mt-md">{{ $t('preview.none') }}</div>
        </div>
      </q-card-section>
    </q-card>
  </q-dialog>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'
import { useDialogPluginComponent } from 'quasar'
import { fileIcon } from '@/utils/format'
import { triggerDownload } from '@/utils/download'

const props = defineProps({
  node: { type: Object, required: true },
  // Resolver returning a URL that can be loaded directly by the browser.
  resolveUrl: { type: Function, required: true },
})
defineEmits([...useDialogPluginComponent.emits])
const { dialogRef, onDialogHide } = useDialogPluginComponent()

const url = ref(null)
const text = ref('')

const kind = computed(() => {
  const mime = props.node.mime_type || ''
  if (mime.startsWith('image/')) return 'image'
  if (mime.startsWith('video/')) return 'video'
  if (mime.startsWith('audio/')) return 'audio'
  if (mime === 'application/pdf') return 'pdf'
  if (mime.startsWith('text/') || /\.(md|txt|json|csv|log)$/i.test(props.node.name)) return 'text'
  return 'other'
})

function download() {
  triggerDownload(url.value.replace('inline=1', 'inline=0'), props.node.name)
}

onMounted(async () => {
  url.value = await props.resolveUrl(props.node)
  if (kind.value === 'text') {
    const res = await fetch(url.value)
    text.value = await res.text()
  }
})
</script>
