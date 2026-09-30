<template>
  <q-layout view="lHh Lpr lFf">
    <q-header elevated class="bg-primary text-white">
      <q-toolbar>
        <q-btn flat dense round icon="menu" aria-label="Menu" @click="drawerOpen = !drawerOpen" />
        <q-toolbar-title class="cursor-pointer" @click="$router.push({ name: 'libraries' })">
          <q-icon name="cloud" size="sm" class="q-mr-sm" />{{ auth.settings.site_name }}
        </q-toolbar-title>

        <q-btn flat round dense icon="translate" :aria-label="$t('nav.language')">
          <q-menu>
            <q-list dense style="min-width: 140px">
              <q-item v-for="l in LOCALES" :key="l.value" clickable v-close-popup :active="locale === l.value" @click="setLocale(l.value)">
                <q-item-section>{{ l.label }}</q-item-section>
              </q-item>
            </q-list>
          </q-menu>
        </q-btn>
        <q-btn flat round dense icon="account_circle">
          <q-menu>
            <q-list style="min-width: 220px">
              <q-item>
                <q-item-section>
                  <q-item-label>{{ auth.user?.name }}</q-item-label>
                  <q-item-label caption>{{ auth.user?.email }}</q-item-label>
                </q-item-section>
              </q-item>
              <q-separator />
              <q-item clickable v-close-popup :to="{ name: 'profile' }">
                <q-item-section avatar><q-icon name="person" /></q-item-section>
                <q-item-section>{{ $t('nav.profile') }}</q-item-section>
              </q-item>
              <q-item v-if="auth.user?.is_admin" clickable v-close-popup :href="adminUrl" target="_blank">
                <q-item-section avatar><q-icon name="admin_panel_settings" /></q-item-section>
                <q-item-section>{{ $t('nav.admin') }}</q-item-section>
              </q-item>
              <q-item clickable v-close-popup @click="logout">
                <q-item-section avatar><q-icon name="logout" /></q-item-section>
                <q-item-section>{{ $t('nav.signOut') }}</q-item-section>
              </q-item>
            </q-list>
          </q-menu>
        </q-btn>
      </q-toolbar>
    </q-header>

    <q-drawer v-model="drawerOpen" show-if-above bordered :width="250">
      <q-list padding>
        <q-item-label header>{{ $t('nav.files') }}</q-item-label>
        <q-item v-for="link in fileLinks" :key="link.name" clickable :to="{ name: link.name }" active-class="text-primary bg-blue-1">
          <q-item-section avatar><q-icon :name="link.icon" /></q-item-section>
          <q-item-section>{{ $t(link.label) }}</q-item-section>
        </q-item>

        <q-separator spaced />
        <q-item-label header>{{ $t('nav.collaboration') }}</q-item-label>
        <q-item v-for="link in collabLinks" :key="link.name" clickable :to="{ name: link.name }" active-class="text-primary bg-blue-1">
          <q-item-section avatar><q-icon :name="link.icon" /></q-item-section>
          <q-item-section>{{ $t(link.label) }}</q-item-section>
        </q-item>
      </q-list>

      <div class="absolute-bottom q-pa-md">
        <div class="text-caption text-grey-7 q-mb-xs">
          <q-icon name="storage" size="xs" class="q-mr-xs" />
          {{ formatBytes(auth.user?.used_bytes) }}
          <span v-if="auth.user?.quota_bytes"> {{ $t('nav.of') }} {{ formatBytes(auth.user.quota_bytes) }}</span>
          <span v-else> {{ $t('nav.used') }}</span>
        </div>
        <q-linear-progress v-if="auth.user?.quota_bytes" :value="auth.usagePercent / 100" :color="auth.usagePercent > 90 ? 'negative' : 'primary'" rounded size="6px" />
      </div>
    </q-drawer>

    <q-page-container>
      <router-view />
    </q-page-container>
  </q-layout>
</template>

<script setup>
import { ref, computed } from 'vue'
import { useRouter } from 'vue-router'
import { useAuthStore } from '@/stores/auth'
import { formatBytes } from '@/utils/format'
import { LOCALES, setLocale } from '@/boot/i18n'
import { useI18n } from 'vue-i18n'

const { locale } = useI18n()

const auth = useAuthStore()
const router = useRouter()
const drawerOpen = ref(false)

const fileLinks = [
  { name: 'libraries', label: 'nav.libraries', icon: 'inventory_2' },
  { name: 'shared', label: 'nav.shared', icon: 'folder_shared' },
  { name: 'starred', label: 'nav.starred', icon: 'star' },
  { name: 'links', label: 'nav.links', icon: 'link' },
]
const collabLinks = [
  { name: 'groups', label: 'nav.groups', icon: 'groups' },
  { name: 'activities', label: 'nav.activity', icon: 'history' },
]

const adminUrl = computed(() => import.meta.env.API_URL.replace(/\/api\/v1\/?$/, '') + '/admin')

async function logout() {
  await auth.logout()
  router.push({ name: 'login' })
}
</script>
