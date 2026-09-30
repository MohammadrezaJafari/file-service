<template>
  <component :is="inline ? 'div' : QMenu">
    <q-list dense style="min-width: 200px">
      <q-item clickable v-close-popup @click="$emit('open', node)"><q-item-section avatar><q-icon name="open_in_new" /></q-item-section><q-item-section>{{ $t('editor.edit') }}</q-item-section></q-item>
      <q-item clickable v-close-popup @click="$emit('details', node)"><q-item-section avatar><q-icon name="info" /></q-item-section><q-item-section>{{ $t('details.title') }}</q-item-section></q-item>
      <q-item clickable v-close-popup @click="$emit('download', node)"><q-item-section avatar><q-icon name="download" /></q-item-section><q-item-section>{{ $t('common.download') }}{{ node.type === 'folder' ? ' ' + $t('browser.zip') : '' }}</q-item-section></q-item>
      <q-item clickable v-close-popup @click="$emit('star', node)"><q-item-section avatar><q-icon :name="node.is_starred ? 'star' : 'star_border'" /></q-item-section><q-item-section>{{ node.is_starred ? $t('common.unstar') : $t('common.star') }}</q-item-section></q-item>
      <q-item v-if="!library?.is_encrypted" clickable v-close-popup @click="$emit('link', node)"><q-item-section avatar><q-icon name="link" /></q-item-section><q-item-section>{{ $t('common.getLink') }}</q-item-section></q-item>
      <q-item v-if="library?.is_owner && node.type === 'folder'" clickable v-close-popup @click="$emit('share', node)"><q-item-section avatar><q-icon name="share" /></q-item-section><q-item-section>{{ $t('browser.shareFolder') }}</q-item-section></q-item>
      <q-item v-if="node.type === 'file'" clickable v-close-popup @click="$emit('versions', node)"><q-item-section avatar><q-icon name="history" /></q-item-section><q-item-section>{{ $t('common.history') }}</q-item-section></q-item>
      <template v-if="canWrite">
        <q-separator />
        <q-item clickable v-close-popup @click="$emit('rename', node)"><q-item-section avatar><q-icon name="edit" /></q-item-section><q-item-section>{{ $t('common.rename') }}</q-item-section></q-item>
        <q-item clickable v-close-popup @click="$emit('move', node)"><q-item-section avatar><q-icon name="drive_file_move" /></q-item-section><q-item-section>{{ $t('common.move') }}</q-item-section></q-item>
      </template>
      <q-item clickable v-close-popup @click="$emit('copy', node)"><q-item-section avatar><q-icon name="file_copy" /></q-item-section><q-item-section>{{ $t('common.copy') }}</q-item-section></q-item>
      <q-item v-if="canWrite" clickable v-close-popup class="text-negative" @click="$emit('delete', node)"><q-item-section avatar><q-icon name="delete" /></q-item-section><q-item-section>{{ $t('common.delete') }}</q-item-section></q-item>
    </q-list>
  </component>
</template>

<script setup>
import { QMenu } from 'quasar'
defineProps({ node: { type: Object, required: true }, library: { type: Object, default: null }, canWrite: Boolean, inline: Boolean })
defineEmits(['open', 'details', 'download', 'star', 'link', 'share', 'versions', 'rename', 'move', 'copy', 'delete'])
</script>
