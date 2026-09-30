<template>
  <q-dialog ref="dialogRef" @hide="onDialogHide">
    <q-card style="min-width: 420px">
      <q-form @submit="submit">
        <q-card-section class="text-h6">{{ library ? 'Edit library' : 'New library' }}</q-card-section>
        <q-card-section class="q-gutter-md">
          <q-input v-model="form.name" label="Name" outlined autofocus :rules="[(v) => !!v || 'Required']" />
          <q-input v-model="form.description" label="Description" type="textarea" outlined autogrow />
        </q-card-section>
        <q-card-actions align="right">
          <q-btn flat label="Cancel" @click="onDialogCancel" />
          <q-btn type="submit" color="primary" unelevated :label="library ? 'Save' : 'Create'" :loading="loading" />
        </q-card-actions>
      </q-form>
    </q-card>
  </q-dialog>
</template>

<script setup>
import { ref, reactive } from 'vue'
import { useDialogPluginComponent, useQuasar } from 'quasar'
import { api } from '@/boot/axios'
import { errorMessage } from '@/utils/format'

const props = defineProps({ library: { type: Object, default: null } })
defineEmits([...useDialogPluginComponent.emits])

const { dialogRef, onDialogHide, onDialogOK, onDialogCancel } = useDialogPluginComponent()
const $q = useQuasar()
const form = reactive({ name: props.library?.name || '', description: props.library?.description || '' })
const loading = ref(false)

async function submit() {
  loading.value = true
  try {
    const { data } = props.library ? await api.put(`/libraries/${props.library.id}`, form) : await api.post('/libraries', form)
    onDialogOK(data)
  } catch (e) {
    $q.notify({ type: 'negative', message: errorMessage(e) })
  } finally {
    loading.value = false
  }
}
</script>
