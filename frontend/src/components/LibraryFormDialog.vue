<template>
  <q-dialog ref="dialogRef" @hide="onDialogHide">
    <q-card class="fs-card" style="min-width: 440px">
      <q-form @submit="submit">
        <q-card-section class="text-h6">{{ library ? $t('libraries.edit') : $t('libraries.new') }}</q-card-section>
        <q-card-section class="q-gutter-md">
          <q-input v-model="form.name" :label="$t('common.name')" outlined autofocus :rules="[(v) => !!v || $t('common.required')]" />
          <q-input v-model="form.description" :label="$t('libraries.description')" type="textarea" outlined autogrow />
          <q-input v-if="!library" v-model="form.password" type="password" outlined :label="$t('encrypted.passwordLabel')" :hint="$t('encrypted.passwordHint')" :rules="[(v) => !v || v.length >= 6 || $t('auth.minChars')]">
            <template #prepend><q-icon name="lock" /></template>
          </q-input>
        </q-card-section>
        <q-card-actions align="right">
          <q-btn flat :label="$t('common.cancel')" @click="onDialogCancel" />
          <q-btn type="submit" color="primary" unelevated :label="library ? $t('common.save') : $t('common.create')" :loading="loading" />
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
const form = reactive({ name: props.library?.name || '', description: props.library?.description || '', password: '' })
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
