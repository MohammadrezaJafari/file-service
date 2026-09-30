import { api } from '@/boot/axios'

/**
 * Ask the API for a short-lived signed URL, so the browser can fetch the file
 * natively without needing the bearer header.
 */
export async function signedUrl(node, { inline = false } = {}) {
  const { data } = await api.get(`/nodes/${node.id}/download-url`, { params: { inline: inline ? 1 : 0 } })
  return data.url
}

export function triggerDownload(url, name) {
  const a = document.createElement('a')
  a.href = url
  a.download = name || ''
  a.rel = 'noopener'
  document.body.appendChild(a)
  a.click()
  a.remove()
}

export async function downloadNode(node) {
  triggerDownload(await signedUrl(node), node.type === 'folder' ? `${node.name}.zip` : node.name)
}
