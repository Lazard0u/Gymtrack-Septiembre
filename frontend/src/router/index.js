/**
 * GymTrack · router/index.js
 * -------------------------------------------------
 * Rutas de la aplicación con guards de navegación.
 *
 * meta.requiereAuth  = true  → redirige a /login si no hay token
 * meta.soloPublico   = true  → redirige a /dashboard si ya está logueado
 */

import { createRouter, createWebHistory } from 'vue-router'
import HomeView from '../views/HomeView.vue'
import { useAuthStore } from '../stores/auth'

const LoginView = () => import('../views/LoginView.vue')
const RegisterView = () => import('../views/RegisterView.vue')
const DashboardView = () => import('../views/DashboardView.vue')
const AdminView = () => import('../views/AdminView.vue')
const StatusView = () => import('../views/StatusView.vue')
const GymsView = () => import('../views/GymsView.vue')
const PlansView = () => import('../views/PlansView.vue')
const ForGymsView = () => import('../views/ForGymsView.vue')
const LegalView = () => import('../views/LegalView.vue')

const routes = [
  {
    path: '/',
    name: 'home',
    component: HomeView,
    meta: { title: 'GymTrack | Entrená y gestioná en un solo lugar' },
  },
  {
    path: '/gimnasios',
    name: 'gyms',
    component: GymsView,
    meta: { title: 'Explorar gimnasios | GymTrack' },
  },
  {
    path: '/planes',
    name: 'plans',
    component: PlansView,
    meta: { title: 'Planes | GymTrack' },
  },
  {
    path: '/para-gimnasios',
    name: 'for-gyms',
    component: ForGymsView,
    meta: { title: 'Para gimnasios | GymTrack' },
  },
  {
    path: '/privacidad',
    name: 'privacy',
    component: LegalView,
    props: { kind: 'privacy' },
    meta: { title: 'Privacidad | GymTrack' },
  },
  {
    path: '/terminos',
    name: 'terms',
    component: LegalView,
    props: { kind: 'terms' },
    meta: { title: 'Términos | GymTrack' },
  },
  {
    path: '/accesibilidad',
    name: 'accessibility',
    component: LegalView,
    props: { kind: 'accessibility' },
    meta: { title: 'Accesibilidad | GymTrack' },
  },
  {
    path: '/contacto',
    name: 'contact',
    component: LegalView,
    props: { kind: 'contact' },
    meta: { title: 'Contacto | GymTrack' },
  },
  {
    path: '/login',
    name: 'login',
    component: LoginView,
    meta: { soloPublico: true, title: 'Ingresar | GymTrack' },
  },
  {
    path: '/registro',
    name: 'registro',
    component: RegisterView,
    meta: { soloPublico: true, title: 'Crear cuenta | GymTrack' },
  },
  {
    path: '/dashboard',
    name: 'dashboard',
    component: DashboardView,
    meta: { requiereAuth: true, title: 'Mi panel | GymTrack' },
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
    meta: { title: 'Acceso denegado | GymTrack' },
  },
  {
    path: '/admin',
    name: 'admin',
    component: AdminView,
    meta: { requiereAuth: true, roles: [2], title: 'Administración | GymTrack' },
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
    meta: { title: 'Página no encontrada | GymTrack' },
  },
]

const router = createRouter({
  history: createWebHistory(),
  routes,
  scrollBehavior(to, from, savedPosition) {
    if (savedPosition) return savedPosition
    if (to.hash) return { el: to.hash, behavior: 'smooth' }
    return { top: 0 }
  },
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

router.afterEach((to) => {
  document.title = to.meta.title || 'GymTrack'
})

export default router
