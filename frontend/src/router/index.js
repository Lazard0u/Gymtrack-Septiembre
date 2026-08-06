/**
 * GymTrack · router/index.js
 * -------------------------------------------------
 * Rutas de la aplicación con guards de navegación.
 *
 * La sesión se determina siempre con GET /api/me y una cookie HttpOnly.
 */

import { createRouter, createWebHistory } from 'vue-router'
import HomeView from '../views/HomeView.vue'
import { useAuthStore } from '../stores/auth'

const LoginView = () => import('../views/LoginView.vue')
const RegisterView = () => import('../views/RegisterView.vue')
const OwnerRegisterView = () => import('../views/OwnerRegisterView.vue')
const ForgotPasswordView = () => import('../views/ForgotPasswordView.vue')
const ResetPasswordView = () => import('../views/ResetPasswordView.vue')
const VerifyEmailView = () => import('../views/VerifyEmailView.vue')
const ChangePasswordView = () => import('../views/ChangePasswordView.vue')
const SessionsView = () => import('../views/SessionsView.vue')
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
    meta: { publicOnly: true, title: 'Ingresar | GymTrack' },
  },
  {
    path: '/registro',
    name: 'registro',
    component: RegisterView,
    meta: { publicOnly: true, title: 'Crear cuenta | GymTrack' },
  },
  {
    path: '/registro-gimnasio', name: 'owner-register', component: OwnerRegisterView,
    meta: { publicOnly: true, title: 'Solicitar cuenta de gimnasio | GymTrack' },
  },
  {
    path: '/recuperar', name: 'forgot-password', component: ForgotPasswordView,
    meta: { publicOnly: true, title: 'Recuperar contraseña | GymTrack' },
  },
  {
    path: '/restablecer/:token', name: 'reset-password', component: ResetPasswordView,
    meta: { publicOnly: true, title: 'Restablecer contraseña | GymTrack' },
  },
  {
    path: '/verificar-email', name: 'verify-email', component: VerifyEmailView,
    meta: { allowAuthenticated: true, title: 'Verificar correo | GymTrack' },
  },
  {
    path: '/cambiar-password', name: 'change-password', component: ChangePasswordView,
    meta: { requiresAuth: true, title: 'Cambiar contraseña | GymTrack' },
  },
  {
    path: '/sesiones', name: 'sessions', component: SessionsView,
    meta: { requiresAuth: true, requiresVerifiedEmail: true, title: 'Sesiones activas | GymTrack' },
  },
  {
    path: '/dashboard',
    name: 'dashboard',
    component: DashboardView,
    meta: { requiresAuth: true, requiresVerifiedEmail: true, title: 'Mi panel | GymTrack' },
  },
  {
    path: '/sesion-expirada', name: 'session-expired', component: StatusView,
    props: { code: '401', title: 'Tu sesión terminó', message: 'Iniciá sesión nuevamente para continuar de forma segura.' },
    meta: { title: 'Sesión expirada | GymTrack' },
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
    meta: { requiresAuth: true, requiresVerifiedEmail: true, allowedRoles: ['admin_general'], requiredPermission: 'members.read', title: 'Administración | GymTrack' },
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
  if (!authStore.initialized && (to.meta.requiresAuth || to.meta.publicOnly || to.meta.allowAuthenticated)) await authStore.bootstrap()
  if (to.meta.requiresAuth && !authStore.estaAutenticado) return { name: 'login', query: { redirect: safeRedirect(to.fullPath) } }
  if (authStore.estaAutenticado && authStore.user?.must_change_password && to.name !== 'change-password') return { name: 'change-password' }
  if (to.meta.requiresVerifiedEmail && !authStore.correoVerificado) return { name: 'verify-email', query: { email: authStore.user?.email } }
  if (to.meta.allowedRoles && !to.meta.allowedRoles.includes(authStore.user?.role)) return { name: 'forbidden' }
  if (to.meta.requiredPermission && !authStore.tienePermiso(to.meta.requiredPermission)) return { name: 'forbidden' }
  if (to.meta.publicOnly && authStore.estaAutenticado) return { name: 'dashboard' }
})

function safeRedirect(value) {
  return typeof value === 'string' && value.startsWith('/') && !value.startsWith('//') ? value : '/dashboard'
}

router.afterEach((to) => {
  document.title = to.meta.title || 'GymTrack'
})

export default router
