import { defineBoot } from '#q-app'
import { createI18n } from 'vue-i18n'
import { Lang } from 'quasar'
import messages from '@/i18n'

export const LOCALES = [
  { value: 'fa-IR', label: 'فارسی', rtl: true },
  { value: 'en-US', label: 'English', rtl: false },
]

const saved = (() => {
  try {
    return localStorage.getItem('fs_locale')
  } catch {
    return null
  }
})()

export const i18n = createI18n({
  legacy: false,
  locale: saved && messages[saved] ? saved : 'fa-IR',
  fallbackLocale: 'en-US',
  messages,
})

const quasarLangs = import.meta.glob('../../node_modules/quasar/lang/(fa-IR|en-US).js')

export async function setLocale(locale) {
  i18n.global.locale.value = locale
  try {
    localStorage.setItem('fs_locale', locale)
  } catch {
    /* ignore */
  }
  const loader = quasarLangs[`../../node_modules/quasar/lang/${locale}.js`]
  if (loader) {
    const lang = await loader()
    Lang.set(lang.default)
  }
  const rtl = LOCALES.find((l) => l.value === locale)?.rtl === true
  document.documentElement.setAttribute('lang', locale)
  document.documentElement.setAttribute('dir', rtl ? 'rtl' : 'ltr')
}

export default defineBoot(async ({ app }) => {
  app.use(i18n)
  await setLocale(i18n.global.locale.value)
})
