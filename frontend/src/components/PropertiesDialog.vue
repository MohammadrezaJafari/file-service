<template>
  <q-dialog ref="dialogRef" @hide="onDialogHide">
    <q-card class="fs-card" style="min-width: 640px; max-width: 95vw">
      <q-card-section>
        <div class="text-h6">{{ $t('props.manage') }}</div>
        <div class="text-caption" style="color: var(--fs-text-muted)">{{ $t('props.hint') }}</div>
      </q-card-section>
      <q-card-section class="q-pt-none">
        <div v-for="(p, i) in rows" :key="i" class="row q-col-gutter-sm items-start q-mb-xs">
          <div class="col-3"><q-input v-model="p.label" dense outlined :label="$t('props.label')" /></div>
          <div class="col-2"><q-input v-model="p.key" dense outlined :label="$t('props.key')" :hint="$t('props.keyHint')" @update:model-value="(v) => (p.key = slug(v))" /></div>
          <div class="col-2"><q-select v-model="p.type" dense outlined :label="$t('props.type')" :options="typeOptions" emit-value map-options /></div>
          <div class="col-4"><q-input v-if="p.type === 'select'" v-model="p.optionsText" dense outlined :label="$t('props.options')" /></div>
          <div class="col-1"><q-btn flat round dense icon="delete" color="negative" @click="rows.splice(i, 1)" /></div>
        </div>
        <div v-if="!rows.length" class="text-caption q-py-md" style="color: var(--fs-text-muted)">{{ $t('props.none') }}</div>
        <q-btn flat color="primary" icon="add" :label="$t('props.add')" @click="rows.push({ key: '', label: '', type: 'text', optionsText: '' })" />
      </q-card-section>
      <q-card-actions align="right">
        <q-btn flat :label="$t('common.cancel')" @click="onDialogCancel" />
        <q-btn color="primary" unelevated :label="$t('common.save')" :loading="saving" @click="save" />
      </q-card-actions>
    </q-card>
  </q-dialog>
</template>

<script setup>
import { ref, computed } from 'vue'
import { useDialogPluginComponent, useQuasar } from 'quasar'
import { useI18n } from 'vue-i18n'
import { api } from '@/boot/axios'
import { errorMessage } from '@/utils/format'

const props = defineProps({ library: { type: Object, required: true } })
defineEmits([...useDialogPluginComponent.emits])
const { dialogRef, onDialogHide, onDialogOK, onDialogCancel } = useDialogPluginComponent()
const $q = useQuasar()
const { t } = useI18n()
const rows = ref((props.library.property_definitions || []).map((p) => ({ ...p, optionsText: (p.options || []).join(', ') })))
const saving = ref(false)
const typeOptions = computed(() => ['text', 'number', 'date', 'select', 'checkbox', 'user'].map((v) => ({ label: t(`props.types.${v}`), value: v })))
const slug = (v) => (v || '').toLowerCase().replace(/[^a-z0-9_]+/g, '_').replace(/^_+|_+$/g, '')

async function save() {
  saving.value = true
  try {
    const properties = rows.value
      .filter((p) => p.label && p.key)
      .map((p) => ({ key: p.key, label: p.label, type: p.type, options: p.type === 'select' ? p.optionsText.split(',').map((s) => s.trim()).filter(Boolean) : undefined }))
    const { data } = await api.put(`/libraries/${props.library.id}/properties`, { properties })
    $q.notify({ type: 'positive', message: t('props.saved') })
    onDialogOK(data)
  } catch (e) {
    $q.notify({ type: 'negative', message: errorMessage(e) })
  } finally {
    saving.value = false
  }
}
</script>
