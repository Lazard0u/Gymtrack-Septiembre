/**
 * GymTrack · router/index.js
 * -------------------------------------------------
 * Rutas de la aplicación con guards de navegación.
 *
 * meta.requiereAuth  = true  → redirige a /login si no hay token
 * meta.soloPublico   = true  → redirige a /dashboard si ya está logueado
 */

import { createRouter, createWebHistory } from 'vue-router'
import HomeView      from '../views/HomeView.vue'
import LoginView     from '../views/LoginView.vue'
import RegisterView  from '../views/RegisterView.vue'
import DashboardView from '../views/DashboardView.vue'
import AdminView     from '../views/AdminView.vue'
import StatusView    from '../views/StatusView.vue'
import { useAuthStore } from '../stores/auth'

const routes = [
  {
    path: '/',
    name: 'home',
    component: HomeView,
  },
  {
    path: '/login',
    name: 'login',
    component: LoginView,
    meta: { soloPublico: true },
  },
  {
    path: '/registro',
    name: 'registro',
    component: RegisterView,
    meta: { soloPublico: true },
  },
  {
    path: '/dashboard',
    name: 'dashboard',
    component: DashboardView,
    meta: { requiereAuth: true },
  },
  {
    path: '/403',
    name: 'forbidden',
    component: StatusView,
    props: {
      code: '403',
      title: 'No tenés permiso para entrar acá',
      message: 'Volvé a tu panel o iniciá sesión con una cuenta autorizada.',
    },
  },
  {
    path: '/admin',
    name: 'admin',
    component: AdminView,
    meta: { requiereAuth: true, roles: [2] },
  },
  {
    path: '/:pathMatch(.*)*',
    name: 'not-found',
    component: StatusView,
    props: {
      code: '404',
      title: 'Esta página no existe',
      message: 'Revisá la dirección o volvé al inicio de GymTrack.',
    },
  },
]

const router = createRouter({
  history: createWebHistory(),
  routes,
})

// ── Guard global ──────────────────────────────────────────────
router.beforeEach(async (to) => {
  const authStore = useAuthStore()
  const token = authStore.token

  // Ruta protegida sin sesión → mandamos al login
  if (to.meta.requiereAuth && !token) {
    return { name: 'login', query: { redirect: to.fullPath } }
  }

  if ((to.meta.requiereAuth || to.meta.soloPublico) && token && !authStore.user) {
    await authStore.cargarPerfil()
  }

  if (to.meta.requiereAuth && !authStore.estaAutenticado) {
    return { name: 'login', query: { redirect: to.fullPath } }
  }

  if (to.meta.roles && !to.meta.roles.includes(authStore.user?.rol_id)) {
    return { name: 'forbidden' }
  }

  // Ruta pública (login/registro) con sesión activa → mandamos al dashboard
  if (to.meta.soloPublico && authStore.estaAutenticado) {
    return { name: 'dashboard' }
  }
})

export default router
