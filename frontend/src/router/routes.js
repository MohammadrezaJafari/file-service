const routes = [
  {
    path: '/',
    component: () => import('@/layouts/MainLayout.vue'),
    meta: { requiresAuth: true },
    children: [
      { path: '', redirect: { name: 'libraries' } },
      { path: 'libraries', name: 'libraries', component: () => import('@/pages/LibrariesPage.vue') },
      { path: 'lib/:id', name: 'library', component: () => import('@/pages/LibraryBrowserPage.vue') },
      { path: 'lib/:id/folder/:folderId', name: 'folder', component: () => import('@/pages/LibraryBrowserPage.vue') },
      { path: 'lib/:id/trash', name: 'trash', component: () => import('@/pages/TrashPage.vue') },
      { path: 'shared', name: 'shared', component: () => import('@/pages/SharedPage.vue') },
      { path: 'starred', name: 'starred', component: () => import('@/pages/StarredPage.vue') },
      { path: 'links', name: 'links', component: () => import('@/pages/ShareLinksPage.vue') },
      { path: 'groups', name: 'groups', component: () => import('@/pages/GroupsPage.vue') },
      { path: 'groups/:id', name: 'group', component: () => import('@/pages/GroupPage.vue') },
      { path: 'activities', name: 'activities', component: () => import('@/pages/ActivitiesPage.vue') },
      { path: 'profile', name: 'profile', component: () => import('@/pages/ProfilePage.vue') },
    ],
  },
  {
    path: '/auth',
    component: () => import('@/layouts/AuthLayout.vue'),
    meta: { guestOnly: true },
    children: [
      { path: 'login', name: 'login', component: () => import('@/pages/LoginPage.vue') },
      { path: 'register', name: 'register', component: () => import('@/pages/RegisterPage.vue') },
    ],
  },
  {
    path: '/s/:token',
    component: () => import('@/layouts/AuthLayout.vue'),
    children: [{ path: '', name: 'public-share', component: () => import('@/pages/PublicSharePage.vue') }],
  },
  {
    path: '/u/:token',
    component: () => import('@/layouts/AuthLayout.vue'),
    children: [{ path: '', name: 'public-upload', component: () => import('@/pages/PublicUploadPage.vue') }],
  },
  {
    path: '/:catchAll(.*)*',
    component: () => import('@/pages/ErrorNotFound.vue'),
  },
]

export default routes
