<template>
  <div>
    <q-inner-loading :showing="loading" />
    <template v-if="stats">
      <div class="row q-col-gutter-md q-mb-md">
        <div v-for="c in cards" :key="c.label" class="col-6 col-md-3">
          <div class="fs-card q-pa-md row items-center no-wrap">
            <q-avatar :icon="c.icon" :color="c.color" text-color="white" size="44px" class="q-mr-md" />
            <div>
              <div class="text-caption" style="color: var(--fs-text-muted)">{{ c.label }}</div>
              <div class="text-h6 text-weight-bold">{{ c.value }}</div>
            </div>
          </div>
        </div>
      </div>
      <div class="row q-col-gutter-md">
        <div class="col-12 col-md-6">
          <div class="fs-card q-pa-md">
            <div class="text-subtitle1 text-weight-medium q-mb-md">{{ $t('stats.byType') }}</div>
            <div v-for="r in stats.by_type" :key="r.type" class="q-mb-sm">
              <div class="row items-center text-body2">
                <span>{{ $t(`stats.types.${r.type}`) }}</span><q-space /><span style="color: var(--fs-text-muted)">{{ r.count }} · {{ formatBytes(r.size) }}</span>
              </div>
              <q-linear-progress :value="stats.size_bytes ? r.size / stats.size_bytes : 0" rounded size="10px" :color="typeColor(r.type)" track-color="grey-3" class="q-mt-xs" />
            </div>
          </div>
        </div>
        <div class="col-12 col-md-6">
          <div class="fs-card q-pa-md">
            <div class="text-subtitle1 text-weight-medium q-mb-md">{{ $t('stats.byMonth') }}</div>
            <div class="row items-end no-wrap" style="height: 160px; gap: 6px">
              <div v-for="m in stats.by_month" :key="m.month" class="col column items-center justify-end" style="height: 100%">
                <div class="text-caption">{{ m.count }}</div>
                <div class="full-width bg-primary" style="border-radius: 6px 6px 0 0; min-height: 4px" :style="{ height: (m.count / maxMonth) * 110 + 'px' }"><q-tooltip>{{ m.month }} · {{ formatBytes(m.size) }}</q-tooltip></div>
                <div class="text-caption mono q-mt-xs" style="font-size: 0.65rem">{{ m.month }}</div>
              </div>
            </div>
          </div>
          <div class="fs-card q-pa-md q-mt-md">
            <div class="text-subtitle1 text-weight-medium q-mb-sm">{{ $t('stats.largest') }}</div>
            <q-list dense>
              <q-item v-for="f in stats.largest" :key="f.name"><q-item-section class="ellipsis">{{ f.name }}</q-item-section><q-item-section side>{{ formatBytes(f.size) }}</q-item-section></q-item>
            </q-list>
          </div>
        </div>
      </div>
    </template>
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'
import { useI18n } from 'vue-i18n'
import { api } from '@/boot/axios'
import { formatBytes } from '@/utils/format'

const props = defineProps({ libraryId: { type: Number, required: true } })
const { t } = useI18n()
const stats = ref(null)
const loading = ref(false)
const maxMonth = computed(() => Math.max(1, ...(stats.value?.by_month || []).map((m) => m.count)))
const cards = computed(() => [
  { label: t('stats.files'), value: stats.value.file_count, icon: 'description', color: 'primary' },
  { label: t('stats.folders'), value: stats.value.folder_count, icon: 'folder', color: 'amber-7' },
  { label: t('stats.size'), value: formatBytes(stats.value.size_bytes), icon: 'storage', color: 'teal-6' },
  { label: t('stats.trash'), value: stats.value.trash_count, icon: 'delete', color: 'grey-6' },
])
const typeColor = (k) => ({ image: 'purple-5', video: 'red-5', audio: 'pink-5', document: 'blue-6', archive: 'brown-5', code: 'teal-6' }[k] || 'grey-6')
onMounted(async () => {
  loading.value = true
  try {
    const { data } = await api.get(`/libraries/${props.libraryId}/stats`)
    stats.value = data
  } finally {
    loading.value = false
  }
})
</script>
