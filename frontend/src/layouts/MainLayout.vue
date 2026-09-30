<template>
  <q-layout view="lHh Lpr lFf">
    <q-header class="fs-header" height-hint="64">
      <q-toolbar style="height: 64px">
        <q-btn flat dense round icon="menu" :aria-label="$t('nav.files')" @click="drawerOpen = !drawerOpen" />
        <div class="row items-center no-wrap cursor-pointer q-mx-sm" @click="$router.push({ name: 'libraries' })">
          <span class="fs-brand-icon"><q-icon name="cloud" size="20px" /></span>
          <span class="fs-brand q-mx-sm">{{ auth.settings.site_name }}</span>
        </div>
        <q-space />
        <q-btn flat round dense :icon="$q.dark.isActive ? 'light_mode' : 'dark_mode'" @click="toggleDark" />
        <q-btn flat round dense icon="translate" :aria-label="$t('nav.language')">
          <q-menu>
            <q-list dense style="min-width: 140px">
              <q-item v-for="l in LOCALES" :key="l.value" clickable v-close-popup :active="locale === l.value" @click="setLocale(l.value)">
                <q-item-section>{{ l.label }}</q-item-section>
              </q-item>
            </q-list>
          </q-menu>
        </q-btn>
        <q-btn flat round dense class="q-ml-xs">
          <q-avatar size="32px" color="primary" text-color="white">{{ initials }}</q-avatar>
          <q-menu>
            <q-list style="min-width: 230px">
              <q-item>
                <q-item-section avatar><q-avatar color="primary" text-color="white">{{ initials }}</q-avatar></q-item-section>
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

    <q-drawer v-model="drawerOpen" show-if-above :width="260" class="fs-drawer">
      <q-scroll-area class="fit">
        <q-list class="q-pt-sm">
          <div class="fs-nav-header">{{ $t('nav.files') }}</div>
          <q-item v-for="link in fileLinks" :key="link.name" clickable :to="{ name: link.name }" class="fs-nav-item">
            <q-item-section avatar><q-icon :name="link.icon" /></q-item-section>
            <q-item-section>{{ $t(link.label) }}</q-item-section>
          </q-item>

          <div class="fs-nav-header">{{ $t('nav.collaboration') }}</div>
          <q-item v-for="link in collabLinks" :key="link.name" clickable :to="{ name: link.name }" class="fs-nav-item">
            <q-item-section avatar><q-icon :name="link.icon" /></q-item-section>
            <q-item-section>{{ $t(link.label) }}</q-item-section>
          </q-item>
        </q-list>
      </q-scroll-area>

      <div class="absolute-bottom">
        <div class="fs-usage">
          <div class="row items-center text-caption" style="color: var(--fs-text-muted)">
            <q-icon name="cloud_done" size="18px" class="q-mr-xs" />
            <span>{{ formatBytes(auth.user?.used_bytes) }}</span>
            <span v-if="auth.user?.quota_bytes">&nbsp;{{ $t('nav.of') }} {{ formatBytes(auth.user.quota_bytes) }}</span>
            <span v-else>&nbsp;{{ $t('nav.used') }}</span>
          </div>
          <q-linear-progress v-if="auth.user?.quota_bytes" :value="auth.usagePercent / 100" :color="auth.usagePercent > 90 ? 'negative' : 'primary'" rounded size="6px" class="q-mt-sm" track-color="grey-3" />
        </div>
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
import { useQuasar } from 'quasar'
import { useI18n } from 'vue-i18n'
import { useAuthStore } from '@/stores/auth'
import { formatBytes } from '@/utils/format'
import { LOCALES, setLocale } from '@/boot/i18n'

const { locale } = useI18n()
const $q = useQuasar()
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

const initials = computed(() => (auth.user?.name || '?').trim().charAt(0).toUpperCase())
const adminUrl = computed(() => import.meta.env.API_URL.replace(/\/api\/v1\/?$/, '') + '/admin')

function toggleDark() {
  $q.dark.toggle()
  try {
    localStorage.setItem('fs_dark', $q.dark.isActive ? '1' : '0')
  } catch {
    /* ignore */
  }
}
try {
  if (localStorage.getItem('fs_dark') === '1') $q.dark.set(true)
} catch {
  /* ignore */
}

async function logout() {
  await auth.logout()
  router.push({ name: 'login' })
}
</script>
