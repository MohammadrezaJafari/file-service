<template>
  <q-dialog ref="dialogRef" @hide="onDialogHide">
    <q-card style="min-width: 520px; max-width: 95vw">
      <q-card-section>
        <div class="text-h6">{{ $t('share.title', { target: node ? $t('share.folderTarget', { name: node.name }) : $t('share.libraryTarget', { name: library.name }) }) }}</div>
        <div class="text-caption text-grey-7">{{ $t('share.hint', { kind: node ? $t('common.folder') : $t('picker.library') }) }}</div>
      </q-card-section>

      <q-card-section>
        <q-tabs v-model="tab" dense align="left" class="text-grey-7" active-color="primary" indicator-color="primary">
          <q-tab name="user" :label="$t('share.user')" icon="person" />
          <q-tab name="group" :label="$t('share.group')" icon="groups" />
        </q-tabs>
        <q-separator />
        <div class="row q-col-gutter-sm q-mt-sm items-start">
          <div class="col">
            <q-select
              v-if="tab === 'user'"
              v-model="selectedUser"
              outlined
              dense
              use-input
              :label="$t('share.searchUser')"
              :options="userOptions"
              option-label="name"
              option-value="id"
              input-debounce="300"
              @filter="searchUsers"
            >
              <template #option="{ itemProps, opt }">
                <q-item v-bind="itemProps">
                  <q-item-section>
                    <q-item-label>{{ opt.name }}</q-item-label>
                    <q-item-label caption>{{ opt.email }}</q-item-label>
                  </q-item-section>
                </q-item>
              </template>
              <template #no-option><q-item><q-item-section class="text-grey">{{ $t('share.typeMore') }}</q-item-section></q-item></template>
            </q-select>
            <q-select v-else v-model="selectedGroup" outlined dense :label="$t('share.group')" :options="groups" option-label="name" option-value="id" />
          </div>
          <div class="col-4">
            <q-select v-model="permission" outlined dense :options="permOptions" emit-value map-options />
          </div>
          <div class="col-auto">
            <q-btn color="primary" unelevated :label="$t('common.share')" :loading="saving" :disable="!(tab === 'user' ? selectedUser : selectedGroup)" @click="save" />
          </div>
        </div>
      </q-card-section>

      <q-separator />
      <q-card-section>
        <div class="text-subtitle2 q-mb-sm">{{ $t('share.currently') }}</div>
        <q-list v-if="filteredShares.length" dense separator>
          <q-item v-for="s in filteredShares" :key="s.id">
            <q-item-section avatar><q-icon :name="s.group ? 'groups' : 'person'" /></q-item-section>
            <q-item-section>
              <q-item-label>{{ s.user?.name || s.group?.name }}</q-item-label>
              <q-item-label caption>{{ s.user?.email || $t('share.group') }}<span v-if="!node && s.node"> · {{ $t('share.folderPrefix') }} /{{ s.node.name }}</span></q-item-label>
            </q-item-section>
            <q-item-section side>
              <q-select :model-value="s.permission" dense borderless :options="permOptions" emit-value map-options @update:model-value="(v) => updateShare(s, v)" />
            </q-item-section>
            <q-item-section side>
              <q-btn flat round dense icon="close" color="negative" @click="removeShare(s)" />
            </q-item-section>
          </q-item>
        </q-list>
        <div v-else class="text-grey-6 text-caption">{{ $t('share.none') }}</div>
      </q-card-section>

      <q-card-actions align="right">
        <q-btn flat :label="$t('common.close')" @click="onDialogCancel" />
      </q-card-actions>
    </q-card>
  </q-dialog>
</template>

<script setup>
import { useI18n } from 'vue-i18n'
import { ref, computed, onMounted } from 'vue'
import { useDialogPluginComponent, useQuasar } from 'quasar'
import { api } from '@/boot/axios'
import { errorMessage } from '@/utils/format'

const props = defineProps({ library: { type: Object, required: true }, node: { type: Object, default: null } })
defineEmits([...useDialogPluginComponent.emits])
const { dialogRef, onDialogHide, onDialogCancel } = useDialogPluginComponent()
const $q = useQuasar()
const { t } = useI18n()

const tab = ref('user')
const shares = ref([])
const groups = ref([])
const userOptions = ref([])
const selectedUser = ref(null)
const selectedGroup = ref(null)
const permission = ref('r')
const saving = ref(false)
const permOptions = computed(() => [
  { label: t('common.readOnly'), value: 'r' },
  { label: t('common.readWrite'), value: 'rw' },
])

const filteredShares = computed(() => (props.node ? shares.value.filter((s) => s.node_id === props.node.id) : shares.value))

async function load() {
  const [s, g] = await Promise.all([api.get(`/libraries/${props.library.id}/shares`), api.get('/groups')])
  shares.value = s.data
  groups.value = g.data
}

async function searchUsers(val, update) {
  if (val.length < 2) return update(() => (userOptions.value = []))
  const { data } = await api.get('/users/search', { params: { q: val } })
  update(() => (userOptions.value = data))
}

async function save() {
  saving.value = true
  try {
    await api.post(`/libraries/${props.library.id}/shares`, {
      node_id: props.node?.id || null,
      user_id: tab.value === 'user' ? selectedUser.value?.id : null,
      group_id: tab.value === 'group' ? selectedGroup.value?.id : null,
      permission: permission.value,
    })
    selectedUser.value = null
    selectedGroup.value = null
    await load()
    $q.notify({ type: 'positive', message: t('share.shared') })
  } catch (e) {
    $q.notify({ type: 'negative', message: errorMessage(e) })
  } finally {
    saving.value = false
  }
}

async function updateShare(share, perm) {
  try {
    await api.put(`/shares/${share.id}`, { permission: perm })
    share.permission = perm
  } catch (e) {
    $q.notify({ type: 'negative', message: errorMessage(e) })
  }
}

async function removeShare(share) {
  try {
    await api.delete(`/shares/${share.id}`)
    shares.value = shares.value.filter((s) => s.id !== share.id)
  } catch (e) {
    $q.notify({ type: 'negative', message: errorMessage(e) })
  }
}

onMounted(load)
</script>
