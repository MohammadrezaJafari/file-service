<template>
  <q-layout view="lHh lpr lFf">
    <q-page-container>
      <q-page class="flex flex-center bg-grey-2 q-pa-md">
        <div class="full-width" style="max-width: 560px">
          <div class="text-center q-mb-lg">
            <q-icon name="cloud" color="primary" size="48px" />
            <div class="text-h5 text-weight-medium">{{ auth.settings.site_name }}</div>
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
