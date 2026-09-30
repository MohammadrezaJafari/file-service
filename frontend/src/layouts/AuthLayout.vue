<template>
  <q-layout view="lHh lpr lFf">
    <q-page-container>
      <q-page class="flex flex-center q-pa-md fs-auth-bg">
        <div class="full-width" style="max-width: 520px">
          <div class="text-center q-mb-lg">
            <span class="fs-brand-icon" style="width: 56px; height: 56px; border-radius: 16px"><q-icon name="cloud" size="30px" /></span>
            <div class="text-h5 text-weight-bold q-mt-sm">{{ auth.settings.site_name }}</div>
          </div>
          <router-view />
        </div>
      </q-page>
    </q-page-container>
  </q-layout>
</template>

<script setup>
import { useAuthStore } from '@/stores/auth'
const auth = useAuthStore()
</script>

<style lang="scss">
.fs-auth-bg {
  background: radial-gradient(1200px 600px at 10% -10%, #dbeafe, transparent), radial-gradient(900px 500px at 110% 110%, #ede9fe, transparent), var(--fs-bg);
}
</style>
