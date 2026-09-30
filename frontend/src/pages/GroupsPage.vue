<template>
  <q-page padding>
    <div class="row items-center q-mb-md">
      <div class="page-title">{{ $t('groups.title') }}</div>
      <q-space />
      <q-btn color="primary" icon="group_add" :label="$t('groups.new')" unelevated @click="create" />
    </div>
    <q-inner-loading :showing="loading" />
    <div v-if="!loading && !groups.length" class="fs-card fs-empty">
      <q-icon name="groups" size="64px" />
      <div class="q-mt-md">{{ $t('groups.empty') }}</div>
    </div>
    <div v-else class="row q-col-gutter-md">
      <div v-for="g in groups" :key="g.id" class="col-12 col-sm-6 col-md-4">
        <q-card flat class="fs-card is-clickable cursor-pointer" @click="$router.push({ name: 'group', params: { id: g.id } })">
          <q-card-section class="row items-center no-wrap">
            <q-avatar icon="groups" color="teal-1" text-color="teal-8" />
            <div class="q-ml-md ellipsis">
              <div class="text-subtitle1">{{ g.name }}</div>
              <div class="text-caption text-grey-7">{{ $t('groups.members', { n: g.members_count }) }} · {{ $t(`groups.roles.${g.my_role}`) }}</div>
            </div>
          </q-card-section>
        </q-card>
      </div>
    </div>
  </q-page>
</template>

<script setup>
import { useI18n } from 'vue-i18n'
import { ref, onMounted } from 'vue'
import { useQuasar } from 'quasar'
import { api } from '@/boot/axios'
import { errorMessage } from '@/utils/format'

const $q = useQuasar()
const { t } = useI18n()
const groups = ref([])
const loading = ref(false)

async function load() {
  loading.value = true
  try {
    const { data } = await api.get('/groups')
    groups.value = data
  } finally {
    loading.value = false
  }
}
function create() {
  $q.dialog({ title: t('groups.new'), prompt: { model: '', label: t('groups.groupName'), isValid: (v) => !!v.trim() }, cancel: t('common.cancel'), ok: t('common.ok') }).onOk(async (name) => {
    try {
      await api.post('/groups', { name })
      load()
    } catch (e) {
      $q.notify({ type: 'negative', message: errorMessage(e) })
    }
  })
}
onMounted(load)
</script>
