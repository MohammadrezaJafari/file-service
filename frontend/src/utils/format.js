import { date } from 'quasar'

export function formatBytes(bytes, decimals = 1) {
  if (bytes === null || bytes === undefined) return '—'
  if (bytes === 0) return '0 B'
  const k = 1024
  const sizes = ['B', 'KB', 'MB', 'GB', 'TB']
  const i = Math.min(sizes.length - 1, Math.floor(Math.log(bytes) / Math.log(k)))
  return `${parseFloat((bytes / Math.pow(k, i)).toFixed(i === 0 ? 0 : decimals))} ${sizes[i]}`
}

export function formatDate(value) {
  if (!value) return '—'
  return date.formatDate(value, 'YYYY-MM-DD HH:mm')
}

export function timeAgo(value) {
  if (!value) return ''
  const diff = (Date.now() - new Date(value).getTime()) / 1000
  if (diff < 60) return 'just now'
  if (diff < 3600) return `${Math.floor(diff / 60)} min ago`
  if (diff < 86400) return `${Math.floor(diff / 3600)} h ago`
  if (diff < 86400 * 7) return `${Math.floor(diff / 86400)} d ago`
  return formatDate(value)
}

export function fileIcon(node) {
  if (!node) return 'insert_drive_file'
  if (node.type === 'folder') return 'folder'
  const mime = node.mime_type || ''
  const name = (node.name || '').toLowerCase()
  if (mime.startsWith('image/')) return 'image'
  if (mime.startsWith('video/')) return 'movie'
  if (mime.startsWith('audio/')) return 'audiotrack'
  if (mime === 'application/pdf' || name.endsWith('.pdf')) return 'picture_as_pdf'
  if (/\.(zip|rar|7z|tar|gz|bz2)$/.test(name)) return 'folder_zip'
  if (/\.(doc|docx|odt|rtf)$/.test(name)) return 'description'
  if (/\.(xls|xlsx|csv|ods)$/.test(name)) return 'table_chart'
  if (/\.(ppt|pptx|odp)$/.test(name)) return 'slideshow'
  if (/\.(js|ts|vue|php|py|rb|go|rs|java|c|cpp|h|sh|json|yml|yaml|xml|html|css|scss|sql)$/.test(name)) return 'code'
  if (mime.startsWith('text/') || /\.(md|txt|log)$/.test(name)) return 'article'
  return 'insert_drive_file'
}

export function fileColor(node) {
  if (node?.type === 'folder') return 'amber-7'
  const icon = fileIcon(node)
  return (
    {
      image: 'purple-5',
      movie: 'red-5',
      audiotrack: 'pink-5',
      picture_as_pdf: 'red-7',
      folder_zip: 'brown-5',
      description: 'blue-7',
      table_chart: 'green-7',
      slideshow: 'orange-7',
      code: 'teal-6',
      article: 'blue-grey-6',
    }[icon] || 'grey-6'
  )
}

export function errorMessage(error, fallback = 'Something went wrong') {
  const data = error?.response?.data
  if (data?.errors) {
    const first = Object.values(data.errors)[0]
    return Array.isArray(first) ? first[0] : String(first)
  }
  return data?.message || error?.message || fallback
}

export function isPreviewable(node) {
  if (!node || node.type !== 'file') return false
  const mime = node.mime_type || ''
  return (
    mime.startsWith('image/') ||
    mime.startsWith('video/') ||
    mime.startsWith('audio/') ||
    mime === 'application/pdf' ||
    mime.startsWith('text/') ||
    /\.(md|txt|json|csv|log)$/i.test(node.name)
  )
}
