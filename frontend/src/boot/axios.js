import { defineBoot } from '#q-app'
import axios from 'axios'

const api = axios.create({
  baseURL: import.meta.env.API_URL,
  headers: { Accept: 'application/json' },
})

api.interceptors.request.use((config) => {
  const token = localStorage.getItem('fs_token')
  if (token) config.headers.Authorization = `Bearer ${token}`
  return config
})

export default defineBoot(({ app, router }) => {
  api.interceptors.response.use(
    (r) => r,
    (error) => {
      if (error.response?.status === 401 && localStorage.getItem('fs_token')) {
        localStorage.removeItem('fs_token')
        router.push({ name: 'login', query: { redirect: router.currentRoute.value.fullPath } })
      }
      return Promise.reject(error)
    },
  )
  app.config.globalProperties.$api = api
})

export { api }
