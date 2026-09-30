import { defineConfig } from '#q-app'

export default defineConfig((/* ctx */) => {
  return {
    boot: ['i18n', 'axios', 'auth'],

    css: ['app.scss'],

    extras: ['roboto-font', 'material-icons', 'material-icons-outlined'],

    build: {
      target: {
        browser: ['es2022', 'firefox115', 'chrome115', 'safari14'],
        node: 'node20',
      },
      vueRouterMode: 'hash',
      // Exposed to the app as import.meta.env.API_URL
      defineEnv: {
        API_URL: process.env.API_URL || 'http://localhost:8000/api/v1',
      },
    },

    devServer: {
      open: false,
      port: 9000,
    },

    framework: {
      lang: 'fa-IR',
      config: {
        notify: { position: 'bottom-left', timeout: 2500 },
      },
      plugins: ['Notify', 'Dialog', 'Loading', 'LocalStorage'],
    },

    animations: [],
  }
})
