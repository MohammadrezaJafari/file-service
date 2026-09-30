<template>
  <q-card flat bordered>
    <q-inner-loading :showing="loading" />
    <template v-if="info">
      <q-card-section>
        <div class="text-h6"><q-icon name="cloud_upload" class="q-mr-sm" />{{ $t('public.uploadTo', { name: info.name }) }}</div>
        <div class="text-caption text-grey-7">{{ $t('public.collecting', { name: info.owner }) }}</div>
      </q-card-section>
      <q-card-section v-if="info.locked">
        <q-form @submit="unlock" class="q-gutter-sm">
          <q-input v-model="password" type="password" outlined dense :label="$t('public.locked')" autofocus :error="!!pwError" :error-message="pwError" />
          <q-btn type="submit" color="primary" unelevated :label="$t('public.unlock')" />
        </q-form>
      </q-card-section>
      <q-card-section v-else>
        <div class="drop-zone rounded-borders q-pa-xl text-center cursor-pointer bg-grey-1" :class="{ 'is-dragging': dragging }" @click="input.click()" @dragover.prevent="dragging = true" @dragleave.prevent="dragging = false" @drop.prevent="onDrop">
          <q-icon name="upload_file" size="48px" color="primary" />
          <div class="q-mt-sm">{{ $t('public.drop') }}</div>
        </div>
        <input ref="input" type="file" multiple class="hidden" @change="onPicked" />
      </q-card-section>
    </template>
    <q-card-section v-else-if="error" class="text-center text-negative q-pa-xl">
      <q-icon name="link_off" size="48px" />
      <div class="q-mt-md">{{ error }}</div>
    </q-card-section>
    <UploadQueue ref="uploader" @uploaded="onUploaded" />
  </q-card>
</template>

<script setup>
import { useI18n } from 'vue-i18n'
import { ref, onMounted } from 'vue'
import { useRoute } from 'vue-router'
import { useQuasar } from 'quasar'
import { api } from '@/boot/axios'
import { errorMessage } from '@/utils/format'
import UploadQueue from '@/components/UploadQueue.vue'

const route = useRoute()
const $q = useQuasar()
const { t } = useI18n()
const token = route.params.token
const info = ref(null)
const error = ref('')
const loading = ref(false)
const password = ref('')
const pwError = ref('')
const dragging = ref(false)
const input = ref(null)
const uploader = ref(null)

async function load() {
  loading.value = true
  try {
    const { data } = await api.get(`/share/${token}`, { headers: password.value ? { 'X-Share-Password': password.value } : {} })
    if (data.kind !== 'upload') throw new Error(t('public.notUpload'))
    info.value = data
  } catch (e) {
    error.value = errorMessage(e, t('public.invalid'))
  } finally {
    loading.value = false
  }
}
async function unlock() {
  try {
    await api.post(`/share/${token}/verify`, { password: password.value })
    await load()
  } catch {
    pwError.value = t('public.wrongPassword')
  }
}
function send(files) {
  uploader.value.enqueue([...files], { url: `/share/${token}/upload`, headers: password.value ? { 'X-Share-Password': password.value } : {} })
}
function onPicked(e) {
  send(e.target.files)
  e.target.value = ''
}
function onDrop(e) {
  dragging.value = false
  send(e.dataTransfer.files)
}
function onUploaded(node) {
  $q.notify({ type: 'positive', message: t('public.uploaded', { name: node.name }) })
}
onMounted(load)
</script>
