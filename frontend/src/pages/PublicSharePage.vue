<template>
  <q-card flat bordered>
    <q-inner-loading :showing="loading" />
    <template v-if="info">
      <q-card-section>
        <div class="row items-center no-wrap">
          <q-icon :name="info.type === 'file' ? fileIcon(info.file) : 'folder_shared'" :color="info.type === 'file' ? fileColor(info.file) : 'primary'" size="40px" class="q-mr-md" />
          <div class="ellipsis">
            <div class="text-h6 ellipsis">{{ info.name }}</div>
            <div class="text-caption text-grey-7">{{ $t('public.sharedBy', { name: info.owner }) }}<span v-if="info.expires_at"> · {{ $t('public.expires', { date: formatDate(info.expires_at) }) }}</span></div>
          </div>
        </div>
      </q-card-section>

      <!-- password gate -->
      <q-card-section v-if="info.locked">
        <q-form @submit="unlock" class="q-gutter-sm">
          <q-input v-model="password" type="password" outlined dense :label="$t('public.locked')" autofocus :error="!!pwError" :error-message="pwError" />
          <q-btn type="submit" color="primary" unelevated :label="$t('public.unlock')" :loading="unlocking" />
        </q-form>
      </q-card-section>

      <!-- single file -->
      <q-card-section v-else-if="info.type === 'file'">
        <div class="text-grey-8 q-mb-md">{{ formatBytes(info.file.size) }} · {{ info.file.mime_type }}</div>
        <q-btn v-if="info.allow_download" color="primary" unelevated icon="download" :label="$t('common.download')" :href="downloadUrl()" />
        <div v-else class="text-grey-6">{{ $t('public.downloadDisabled') }}</div>
      </q-card-section>

      <!-- folder browse -->
      <template v-else>
        <q-card-section class="q-py-sm">
          <q-breadcrumbs class="text-caption">
            <q-breadcrumbs-el :label="info.name" icon="home" class="cursor-pointer" @click="browse(null)" />
            <q-breadcrumbs-el v-for="b in breadcrumbs" :key="b.id" :label="b.name" class="cursor-pointer" @click="browse(b.id)" />
          </q-breadcrumbs>
        </q-card-section>
        <q-list separator>
          <q-item v-for="n in items" :key="n.id" clickable @click="n.type === 'folder' ? browse(n.id) : null">
            <q-item-section avatar><q-icon :name="fileIcon(n)" :color="fileColor(n)" /></q-item-section>
            <q-item-section>
              <q-item-label>{{ n.name }}</q-item-label>
              <q-item-label caption>{{ n.type === 'file' ? formatBytes(n.size) : $t('common.folder') }} · {{ formatDate(n.updated_at) }}</q-item-label>
            </q-item-section>
            <q-item-section v-if="n.type === 'file' && info.allow_download" side>
              <q-btn flat round dense icon="download" :href="downloadUrl(n.id)" @click.stop />
            </q-item-section>
          </q-item>
          <q-item v-if="!items.length"><q-item-section class="text-grey-6">{{ $t('public.emptyFolder') }}</q-item-section></q-item>
        </q-list>
      </template>
    </template>
    <q-card-section v-else-if="error" class="text-center text-negative q-pa-xl">
      <q-icon name="link_off" size="48px" />
      <div class="q-mt-md">{{ error }}</div>
    </q-card-section>
  </q-card>
</template>

<script setup>
import { useI18n } from 'vue-i18n'
import { ref, onMounted } from 'vue'
import { useRoute } from 'vue-router'
import { api } from '@/boot/axios'
import { fileIcon, fileColor, formatBytes, formatDate, errorMessage } from '@/utils/format'

const { t } = useI18n()

const route = useRoute()
const token = route.params.token
const info = ref(null)
const items = ref([])
const breadcrumbs = ref([])
const password = ref('')
const pwError = ref('')
const loading = ref(false)
const unlocking = ref(false)
const error = ref('')

const headers = () => (password.value ? { 'X-Share-Password': password.value } : {})

async function load() {
  loading.value = true
  try {
    const { data } = await api.get(`/share/${token}`, { headers: headers() })
    info.value = data
    if (!data.locked && data.type === 'folder') await browse(null)
  } catch (e) {
    error.value = errorMessage(e, t('public.invalid'))
  } finally {
    loading.value = false
  }
}
async function unlock() {
  unlocking.value = true
  pwError.value = ''
  try {
    await api.post(`/share/${token}/verify`, { password: password.value })
    await load()
  } catch {
    pwError.value = t('public.wrongPassword')
  } finally {
    unlocking.value = false
  }
}
async function browse(folderId) {
  const { data } = await api.get(`/share/${token}/browse`, { params: { folder_id: folderId || undefined }, headers: headers() })
  items.value = data.items
  breadcrumbs.value = data.breadcrumbs
}
function downloadUrl(nodeId) {
  const params = new URLSearchParams()
  if (nodeId) params.set('node_id', nodeId)
  if (password.value) params.set('password', password.value)
  return `${import.meta.env.API_URL}/share/${token}/download?${params}`
}
onMounted(load)
</script>
