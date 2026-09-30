<template>
  <div ref="host" class="excalidraw-host" :style="{ height }"></div>
</template>

<script setup>
import { ref, onMounted, onBeforeUnmount, watch } from 'vue'
import React from 'react'
import { createRoot } from 'react-dom/client'
import { Excalidraw } from '@excalidraw/excalidraw'
import '@excalidraw/excalidraw/index.css'

const props = defineProps({
  initialData: { type: Object, default: null },
  height: { type: String, default: '100%' },
  viewMode: { type: Boolean, default: false },
  langCode: { type: String, default: 'fa-IR' },
})
const emit = defineEmits(['change', 'ready'])

const host = ref(null)
let root = null
let api = null
let debounce = null
let mountedAt = Date.now()

function render() {
  root.render(
    React.createElement(Excalidraw, {
      initialData: props.initialData || undefined,
      viewModeEnabled: props.viewMode,
      langCode: props.langCode,
      excalidrawAPI: (a) => {
        api = a
        emit('ready', a)
      },
      onChange: (elements, appState, files) => {
        // Excalidraw fires onChange while mounting; only report real edits.
        if (Date.now() - mountedAt < 1500) return
        clearTimeout(debounce)
        debounce = setTimeout(() => emit('change', { elements, appState, files }), 400)
      },
    }),
  )
}

defineExpose({
  getScene: () => (api ? { elements: api.getSceneElements(), appState: api.getAppState(), files: api.getFiles() } : null),
})

// Excalidraw rewrites <html dir/lang> for its own language; keep the app's direction stable.
let observer = null
function guardDocumentDirection() {
  const html = document.documentElement
  const dir = html.getAttribute('dir')
  const lang = html.getAttribute('lang')
  observer = new MutationObserver(() => {
    if (html.getAttribute('dir') !== dir) html.setAttribute('dir', dir)
    if (html.getAttribute('lang') !== lang) html.setAttribute('lang', lang)
  })
  observer.observe(html, { attributes: true, attributeFilter: ['dir', 'lang'] })
}

onMounted(() => {
  mountedAt = Date.now()
  guardDocumentDirection()
  root = createRoot(host.value)
  render()
})
watch(() => props.viewMode, render)
onBeforeUnmount(() => {
  clearTimeout(debounce)
  root?.unmount()
  observer?.disconnect()
})
</script>

<style>
.excalidraw-host {
  width: 100%;
  min-height: 400px;
}
</style>
