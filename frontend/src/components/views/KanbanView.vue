<template>
  <div>
    <div v-if="!selectProps.length" class="fs-empty"><q-icon name="view_kanban" size="56px" /><div class="q-mt-sm">{{ $t('kanban.noSelect') }}</div></div>
    <template v-else>
      <div class="row items-center q-mb-md q-gutter-sm">
        <q-select v-model="groupKey" dense outlined :label="$t('kanban.groupBy')" :options="selectProps.map((p) => ({ label: p.label, value: p.key }))" emit-value map-options style="min-width: 200px" />
        <div class="text-caption" style="color: var(--fs-text-muted)">{{ $t('kanban.dropHint') }}</div>
      </div>
      <div class="fs-kanban">
        <div v-for="col in columns" :key="col.value ?? '__none'" class="fs-kanban-col" :class="{ 'is-over': over === col.value }" @dragover.prevent="over = col.value" @dragleave="over = null" @drop.prevent="drop(col.value)">
          <div class="row items-center q-mb-sm q-px-xs">
            <div class="text-subtitle2">{{ col.label }}</div>
            <q-badge class="q-ml-sm" color="grey-4" text-color="grey-9">{{ col.items.length }}</q-badge>
          </div>
          <div v-for="n in col.items" :key="n.id" class="fs-kanban-card" :draggable="canWrite" @dragstart="dragging = n" @click="$emit('open', n)">
            <div class="row items-center no-wrap">
              <q-icon :name="fileIcon(n)" :color="fileColor(n)" size="20px" class="q-mr-xs" />
              <div class="ellipsis text-body2">{{ n.name }}</div>
            </div>
            <div v-if="n.tags?.length" class="q-mt-xs">
              <q-badge v-for="t in n.tags" :key="t.id" :style="{ background: t.color }" class="q-mr-xs">{{ t.name }}</q-badge>
            </div>
            <div class="text-caption q-mt-xs" style="color: var(--fs-text-muted)">{{ timeAgo(n.updated_at) }}</div>
          </div>
        </div>
      </div>
    </template>
  </div>
</template>

<script setup>
import { ref, computed, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { api } from '@/boot/axios'
import { fileIcon, fileColor, timeAgo } from '@/utils/format'

const props = defineProps({ items: { type: Array, default: () => [] }, definitions: { type: Array, default: () => [] }, canWrite: Boolean })
const emit = defineEmits(['open', 'changed'])
const { t } = useI18n()
const selectProps = computed(() => props.definitions.filter((d) => d.type === 'select'))
const groupKey = ref(selectProps.value[0]?.key || null)
watch(selectProps, (v) => { if (!v.find((p) => p.key === groupKey.value)) groupKey.value = v[0]?.key || null })
const dragging = ref(null)
const over = ref(null)

const columns = computed(() => {
  const def = selectProps.value.find((p) => p.key === groupKey.value)
  if (!def) return []
  const files = props.items.filter((n) => n.type === 'file')
  const cols = (def.options || []).map((o) => ({ value: o, label: o, items: files.filter((n) => n.metadata?.[def.key] === o) }))
  cols.unshift({ value: null, label: t('kanban.unassigned'), items: files.filter((n) => !n.metadata?.[def.key] || !(def.options || []).includes(n.metadata[def.key])) })
  return cols
})

async function drop(value) {
  over.value = null
  const n = dragging.value
  dragging.value = null
  if (!n || !props.canWrite) return
  const metadata = { ...(n.metadata || {}), [groupKey.value]: value }
  if (value === null) delete metadata[groupKey.value]
  await api.patch(`/nodes/${n.id}/metadata`, { metadata })
  n.metadata = metadata
  emit('changed')
}
</script>
