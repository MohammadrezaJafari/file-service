import { defineBoot } from '#q-app'
import { useAuthStore } from '@/stores/auth'

export default defineBoot(async ({ router, store }) => {
  const auth = useAuthStore(store)
  await auth.init()

  router.beforeEach((to) => {
    if (to.meta.requiresAuth && !auth.isAuthenticated) {
      return { name: 'login', query: { redirect: to.fullPath } }
    }
    if (to.meta.guestOnly && auth.isAuthenticated) {
      return { name: 'libraries' }
    }
    return true
  })
})
