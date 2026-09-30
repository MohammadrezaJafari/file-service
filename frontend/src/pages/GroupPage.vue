<template>
  <q-page padding>
    <div class="row items-center q-mb-md q-gutter-sm">
      <q-btn flat round icon="arrow_back" :to="{ name: 'groups' }" />
      <div class="page-title">{{ group?.name || '…' }}</div>
      <q-space />
      <q-btn v-if="isAdmin" flat icon="edit" :label="$t('common.rename')" @click="rename" />
      <q-btn v-if="group?.my_role === 'owner'" flat color="negative" icon="delete" :label="$t('groups.deleteGroup')" @click="remove" />
      <q-btn v-else-if="group" flat color="negative" icon="logout" :label="$t('groups.leave')" @click="leave" />
    </div>

    <div class="row q-col-gutter-lg">
      <div class="col-12 col-md-6">
        <q-card flat class="fs-card">
          <q-card-section class="row items-center">
            <div class="text-subtitle1">{{ $t('groups.membersTitle') }}</div>
            <q-space />
            <q-btn v-if="isAdmin" dense flat color="primary" icon="person_add" :label="$t('groups.add')" @click="addMember" />
          </q-card-section>
          <q-list separator>
            <q-item v-for="m in group?.members || []" :key="m.id">
              <q-item-section avatar><q-avatar color="blue-1" text-color="primary">{{ m.name.charAt(0).toUpperCase() }}</q-avatar></q-item-section>
              <q-item-section>
                <q-item-label>{{ m.name }}</q-item-label>
                <q-item-label caption>{{ m.email }}</q-item-label>
              </q-item-section>
              <q-item-section side>
                <q-select v-if="isAdmin && m.role !== 'owner'" dense borderless :model-value="m.role" :options="roleOptions" emit-value map-options @update:model-value="(r) => setRole(m, r)" />
                <q-chip v-else dense :label="$t(`groups.roles.${m.role}`)" />
              </q-item-section>
              <q-item-section v-if="isAdmin && m.role !== 'owner'" side>
                <q-btn flat dense round icon="close" @click="removeMember(m)" />
              </q-item-section>
            </q-item>
          </q-list>
        </q-card>
      </div>
      <div class="col-12 col-md-6">
        <q-card flat class="fs-card">
          <q-card-section class="text-subtitle1">{{ $t('groups.libraries') }}</q-card-section>
          <q-list separator>
            <q-item v-for="s in shares" :key="s.id" clickable @click="openShare(s)">
              <q-item-section avatar><q-icon :name="s.node ? 'folder_shared' : 'inventory_2'" color="primary" /></q-item-section>
              <q-item-section>
                <q-item-label>{{ s.node?.name || s.library?.name }}</q-item-label>
                <q-item-label caption>{{ s.node ? $t('shared.in', { library: s.library?.name }) + ' · ' : '' }}{{ $t('groups.by', { name: s.sharer?.name }) }}</q-item-label>
              </q-item-section>
              <q-item-section side><q-chip dense :label="s.permission === 'rw' ? $t('common.readWrite') : $t('common.readOnly')" /></q-item-section>
            </q-item>
            <q-item v-if="!shares.length"><q-item-section class="text-grey-6">{{ $t('groups.noLibraries') }}</q-item-section></q-item>
          </q-list>
        </q-card>
      </div>
    </div>
  </q-page>
</template>

<script setup>
import { useI18n } from 'vue-i18n'
import { ref, computed, onMounted } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useQuasar } from 'quasar'
import { api } from '@/boot/axios'
import { useAuthStore } from '@/stores/auth'
import { errorMessage } from '@/utils/format'

const route = useRoute()
const router = useRouter()
const $q = useQuasar()
const { t } = useI18n()
const auth = useAuthStore()
const group = ref(null)
const shares = ref([])
const id = computed(() => Number(route.params.id))
const roleOptions = computed(() => [
  { label: t('groups.roles.member'), value: 'member' },
  { label: t('groups.roles.admin'), value: 'admin' },
])
const isAdmin = computed(() => ['owner', 'admin'].includes(group.value?.my_role))

async function load() {
  try {
    const [g, s] = await Promise.all([api.get(`/groups/${id.value}`), api.get(`/groups/${id.value}/libraries`)])
    group.value = g.data
    shares.value = s.data
  } catch (e) {
    $q.notify({ type: 'negative', message: errorMessage(e) })
    router.replace({ name: 'groups' })
  }
}
function rename() {
  $q.dialog({ title: t('groups.rename'), prompt: { model: group.value.name, isValid: (v) => !!v.trim() }, cancel: t('common.cancel'), ok: t('common.ok') }).onOk(async (name) => {
    await api.put(`/groups/${id.value}`, { name })
    load()
  })
}
function addMember() {
  $q.dialog({ title: t('groups.addMember'), message: t('groups.addMemberMsg'), prompt: { model: '', type: 'email', label: t('common.email') }, cancel: t('common.cancel'), ok: t('common.ok') }).onOk(async (email) => {
    try {
      await api.post(`/groups/${id.value}/members`, { email })
      load()
    } catch (e) {
      $q.notify({ type: 'negative', message: errorMessage(e) })
    }
  })
}
async function setRole(m, role) {
  try {
    await api.put(`/groups/${id.value}/members/${m.id}`, { role })
    m.role = role
  } catch (e) {
    $q.notify({ type: 'negative', message: errorMessage(e) })
  }
}
async function removeMember(m) {
  await api.delete(`/groups/${id.value}/members/${m.id}`)
  load()
}
function leave() {
  $q.dialog({ title: t('groups.leave'), message: t('groups.leaveMsg', { name: group.value.name }), cancel: t('common.cancel'), ok: t('common.ok') }).onOk(async () => {
    await api.delete(`/groups/${id.value}/members/${auth.user.id}`)
    router.push({ name: 'groups' })
  })
}
function remove() {
  $q.dialog({ title: t('groups.deleteGroup'), message: t('groups.deleteMsg', { name: group.value.name }), cancel: t('common.cancel'), ok: { label: t('common.delete'), color: 'negative', unelevated: true } }).onOk(async () => {
    await api.delete(`/groups/${id.value}`)
    router.push({ name: 'groups' })
  })
}
function openShare(s) {
  if (s.node) router.push({ name: 'folder', params: { id: s.library_id, folderId: s.node.id } })
  else router.push({ name: 'library', params: { id: s.library_id } })
}
onMounted(load)
</script>
