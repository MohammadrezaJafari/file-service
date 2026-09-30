<template>
  <div>
    <div v-if="!items.length" class="fs-empty"><q-icon name="photo_library" size="56px" /><div class="q-mt-sm">{{ $t('gallery.empty') }}</div></div>
    <div v-else class="fs-gallery">
      <div v-for="n in items" :key="n.id" class="fs-tile" @click="$emit('open', n)">
        <img v-if="n.has_thumbnail && thumbs[n.id]" :src="thumbs[n.id]" loading="lazy" />
        <div v-else class="fs-tile-icon"><q-icon :name="fileIcon(n)" :color="fileColor(n)" size="56px" /></div>
        <div class="fs-tile-name">{{ n.name }}</div>
        <q-btn round dense flat icon="more_horiz" class="absolute-top-right q-ma-xs" style="background: rgba(255, 255, 255, 0.85)" @click.stop="$emit('menu', n, $event)" />
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, watch } from 'vue'
import { fileIcon, fileColor } from '@/utils/format'
import { signedUrl } from '@/utils/download'

const props = defineProps({ items: { type: Array, default: () => [] } })
defineEmits(['open', 'menu'])
const thumbs = ref({})

watch(
  () => props.items,
  (items) => {
    for (const n of items) {
      if (n.has_thumbnail && !thumbs.value[n.id]) {
        signedUrl(n, { thumb: true })
          .then((u) => (thumbs.value[n.id] = u))
          .catch(() => {})
      }
    }
  },
  { immediate: true },
)
</script>
