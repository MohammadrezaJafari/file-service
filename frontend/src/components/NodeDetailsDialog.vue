<template>
  <q-dialog ref="dialogRef" position="right" full-height @hide="onDialogHide">
    <q-card class="column" style="width: 420px; max-width: 95vw">
      <q-card-section class="row items-center no-wrap">
        <q-icon :name="fileIcon(node)" :color="fileColor(node)" size="32px" class="q-mr-sm" />
        <div class="ellipsis text-subtitle1 text-weight-medium">{{ node.name }}</div>
        <q-space />
        <q-btn flat round dense icon="close" @click="onDialogCancel" />
      </q-card-section>

      <div v-if="node.has_thumbnail && thumb" class="q-px-md"><img :src="thumb" style="width: 100%; border-radius: 12px; max-height: 220px; object-fit: cover" /></div>

      <q-tabs v-model="tab" dense align="left" active-color="primary" indicator-color="primary" class="q-mt-sm">
        <q-tab name="props" :label="$t('details.tagsAndProps')" />
        <q-tab name="info" :label="$t('details.info')" />
      </q-tabs>
      <q-separator />

      <q-tab-panels v-model="tab" animated class="col scroll">
        <q-tab-panel name="props">
          <div class="text-caption text-weight-medium q-mb-xs" style="color: var(--fs-text-muted)">{{ $t('tags.title') }}</div>
          <q-select v-model="tagIds" :options="tagOptions" multiple use-chips dense outlined emit-value map-options :disable="!canWrite">
            <template #selected-item="s">
              <q-chip dense removable :style="{ background: s.opt.color, color: '#fff' }" @remove="s.removeAtIndex(s.index)">{{ s.opt.label }}</q-chip>
            </template>
          </q-select>

          <div class="text-caption text-weight-medium q-mt-md q-mb-xs" style="color: var(--fs-text-muted)">{{ $t('props.title') }}</div>
          <div v-if="!definitions.length" class="text-caption" style="color: var(--fs-text-muted)">{{ $t('details.noProps') }}</div>
          <div v-for="d in definitions" :key="d.key" class="q-mb-sm">
            <q-checkbox v-if="d.type === 'checkbox'" v-model="meta[d.key]" :label="d.label" :disable="!canWrite" />
            <q-select v-else-if="d.type === 'select'" v-model="meta[d.key]" :options="d.options || []" :label="d.label" dense outlined clearable :disable="!canWrite" />
            <q-input v-else-if="d.type === 'date'" v-model="meta[d.key]" :label="d.label" dense outlined type="date" :disable="!canWrite" />
            <q-input v-else-if="d.type === 'number'" v-model.number="meta[d.key]" :label="d.label" dense outlined type="number" :disable="!canWrite" />
            <q-input v-else v-model="meta[d.key]" :label="d.label" dense outlined :disable="!canWrite" />
          </div>
          <q-btn v-if="canWrite" color="primary" unelevated class="q-mt-sm" :label="$t('common.save')" :loading="saving" @click="save" />
        </q-tab-panel>

        <q-tab-panel name="info">
          <q-list dense>
            <q-item><q-item-section><q-item-label caption>{{ $t('details.path') }}</q-item-label><q-item-label class="mono">{{ full?.path || node.path }}</q-item-label></q-item-section></q-item>
            <q-item v-if="node.type === 'file'"><q-item-section><q-item-label caption>{{ $t('common.size') }}</q-item-label><q-item-label>{{ formatBytes(node.size) }} · {{ node.mime_type }}</q-item-label></q-item-section></q-item>
            <q-item v-if="node.type === 'file'"><q-item-section><q-item-label caption>{{ $t('details.version') }}</q-item-label><q-item-label>{{ node.version_number }}</q-item-label></q-item-section></q-item>
            <q-item><q-item-section><q-item-label caption>{{ $t('details.created') }}</q-item-label><q-item-label>{{ formatDate(node.created_at) }} <span v-if="full?.creator">· {{ full.creator.name }}</span></q-item-label></q-item-section></q-item>
            <q-item><q-item-section><q-item-label caption>{{ $t('details.modified') }}</q-item-label><q-item-label>{{ formatDate(node.updated_at) }} <span v-if="full?.updater">· {{ full.updater.name }}</span></q-item-label></q-item-section></q-item>
            <q-item v-if="node.is_encrypted"><q-item-section><q-chip dense icon="lock" color="amber-2" text-color="amber-10" :label="$t('encrypted.badge')" /></q-item-section></q-item>
          </q-list>
        </q-tab-panel>
      </q-tab-panels>
    </q-card>
  </q-dialog>
</template>

<script setup>
import { ref, reactive, computed, onMounted } from 'vue'
import { useDialogPluginComponent, useQuasar } from 'quasar'
import { useI18n } from 'vue-i18n'
import { api } from '@/boot/axios'
import { fileIcon, fileColor, formatBytes, formatDate, errorMessage } from '@/utils/format'
import { signedUrl } from '@/utils/download'

const props = defineProps({
  node: { type: Object, required: true },
  tags: { type: Array, default: () => [] },
  definitions: { type: Array, default: () => [] },
  canWrite: { type: Boolean, default: false },
})
defineEmits([...useDialogPluginComponent.emits])
const { dialogRef, onDialogHide, onDialogOK, onDialogCancel } = useDialogPluginComponent()
const $q = useQuasar()
const { t } = useI18n()
const tab = ref('props')
const tagIds = ref((props.node.tags || []).map((x) => x.id))
const meta = reactive({ ...(props.node.metadata || {}) })
const full = ref(null)
const thumb = ref(null)
const saving = ref(false)
const tagOptions = computed(() => props.tags.map((x) => ({ label: x.name, value: x.id, color: x.color })))

async function save() {
  saving.value = true
  try {
    const { data } = await api.patch(`/nodes/${props.node.id}/metadata`, { metadata: meta, tag_ids: tagIds.value })
    $q.notify({ type: 'positive', message: t('details.saved') })
    onDialogOK(data)
  } catch (e) {
    $q.notify({ type: 'negative', message: errorMessage(e) })
  } finally {
    saving.value = false
  }
}
onMounted(async () => {
  try {
    const { data } = await api.get(`/nodes/${props.node.id}`)
    full.value = data
  } catch {
    /* ignore */
  }
  if (props.node.has_thumbnail) thumb.value = await signedUrl(props.node, { thumb: true }).catch(() => null)
})
</script>
