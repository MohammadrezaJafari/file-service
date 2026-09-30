import { defineStore } from 'pinia'
import { api } from '@/boot/axios'

export const useAuthStore = defineStore('auth', {
  state: () => ({
    token: localStorage.getItem('fs_token'),
    user: null,
    settings: { site_name: 'File Service', registration_enabled: true, share_links_enabled: true, max_upload_bytes: 0 },
  }),

  getters: {
    isAuthenticated: (s) => !!s.token && !!s.user,
    usagePercent: (s) => (s.user?.quota_bytes ? Math.min(100, Math.round((s.user.used_bytes / s.user.quota_bytes) * 100)) : 0),
  },

  actions: {
    async init() {
      try {
        const { data } = await api.get('/settings')
        this.settings = data
      } catch {
        /* backend offline: keep defaults */
      }
      if (this.token) {
        try {
          await this.fetchMe()
        } catch {
          this.clear()
        }
      }
    },

    async fetchMe() {
      const { data } = await api.get('/auth/me')
      this.user = data
      return data
    },

    async login(payload) {
      const { data } = await api.post('/auth/login', { ...payload, device_name: 'web' })
      this.setSession(data)
    },

    async register(payload) {
      const { data } = await api.post('/auth/register', { ...payload, device_name: 'web' })
      this.setSession(data)
    },

    async logout() {
      try {
        await api.post('/auth/logout')
      } catch {
        /* ignore */
      }
      this.clear()
    },

    setSession({ token, user }) {
      this.token = token
      this.user = user
      localStorage.setItem('fs_token', token)
    },

    clear() {
      this.token = null
      this.user = null
      localStorage.removeItem('fs_token')
    },
  },
})
