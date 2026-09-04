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
const MemberScheduleView = () => import('../views/MemberScheduleView.vue')
const MemberPaymentsView = () => import('../views/MemberPaymentsView.vue')
const MemberProfileView = () => import('../views/MemberProfileView.vue')
const MemberPreferencesView = () => import('../views/MemberPreferencesView.vue')
const MemberActivityView = () => import('../views/MemberActivityView.vue')
const MemberCardView = () => import('../views/MemberCardView.vue')
const AdminShell = () => import('../layouts/AdminShell.vue')
const AdminSummaryView = () => import('../views/admin/AdminSummaryView.vue')
const AdminOperationsView = () => import('../views/admin/AdminOperationsView.vue')
const AdminPromotionsView = () => import('../views/admin/AdminPromotionsView.vue')
const AdminReportsView = () => import('../views/admin/AdminReportsView.vue')
const AdminPaymentsView = () => import('../views/admin/AdminPaymentsView.vue')
const AdminFinanceView = () => import('../views/admin/AdminFinanceView.vue')
const AdminSettingsView = () => import('../views/admin/AdminSettingsView.vue')
const AdminPeopleView = () => import('../views/admin/AdminPeopleView.vue')
const AdminTrainersView = () => import('../views/admin/AdminTrainersView.vue')
const AdminMembershipsView = () => import('../views/admin/AdminMembershipsView.vue')
const AdminMemberCardVerifyView = () => import('../views/admin/AdminMemberCardVerifyView.vue')
const AdminScheduleView = () => import('../views/admin/AdminScheduleView.vue')
const StatusView = () => import('../views/StatusView.vue')
const GymsView = () => import('../views/GymsView.vue')
const GymDetailView = () => import('../views/GymDetailView.vue')
const InvitationAcceptView = () => import('../views/InvitationAcceptView.vue')
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
    path: '/gimnasios/:slug',
    name: 'gym-detail',
    component: GymDetailView,
    meta: { title: 'Gimnasio | GymTrack' },
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
    path: '/invitacion/:token', name: 'invitation-accept', component: InvitationAcceptView,
    meta: { allowAuthenticated: true, title: 'Aceptar invitación | GymTrack' },
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
    path: '/agenda',
    name: 'member-schedule',
    component: MemberScheduleView,
    meta: { requiresAuth: true, requiresVerifiedEmail: true, allowedRoles: ['socio'], requiredPermission: 'classes.read', title: 'Agenda de clases | GymTrack' },
  },
  {
    path: '/pagos',
    name: 'member-payments',
    component: MemberPaymentsView,
    meta: { requiresAuth: true, requiresVerifiedEmail: true, allowedRoles: ['socio'], requiredPermission: 'payments.read', title: 'Mis pagos | GymTrack' },
  },
  {
    path: '/perfil',
    name: 'member-profile',
    component: MemberProfileView,
    meta: { requiresAuth: true, requiresVerifiedEmail: true, allowedRoles: ['socio'], title: 'Mi perfil | GymTrack' },
  },
  {
    path: '/preferencias',
    name: 'member-preferences',
    component: MemberPreferencesView,
    meta: { requiresAuth: true, requiresVerifiedEmail: true, allowedRoles: ['socio'], title: 'Preferencias | GymTrack' },
  },
  {
    path: '/progreso',
    name: 'member-activity',
    component: MemberActivityView,
    meta: { requiresAuth: true, requiresVerifiedEmail: true, allowedRoles: ['socio'], title: 'Mi progreso | GymTrack' },
  },
  {
    path: '/carne',
    name: 'member-card',
    component: MemberCardView,
    meta: { requiresAuth: true, requiresVerifiedEmail: true, allowedRoles: ['socio'], title: 'Carné digital | GymTrack' },
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
    redirect: '/administracion/resumen',
  },
  {
    path: '/administracion',
    component: AdminShell,
    meta: { requiresAuth: true, requiresVerifiedEmail: true, allowedRoles: ['empleado', 'dueño', 'admin_general'], title: 'Administración | GymTrack' },
    children: [
      { path: '', redirect: { name: 'admin-summary' } },
      { path: 'resumen', name: 'admin-summary', component: AdminSummaryView, meta: { adminLabel: 'Resumen', title: 'Resumen administrativo | GymTrack' } },
      { path: 'gestion-operativa', name: 'admin-operations', component: AdminOperationsView, meta: { adminLabel: 'Gestión operativa', title: 'Gestión operativa | GymTrack' } },
      { path: 'socios', name: 'admin-members', component: AdminPeopleView, props: { kind: 'members' }, meta: { adminLabel: 'Socios', requiredPermission: 'members.read', title: 'Socios | Administración GymTrack' } },
      { path: 'verificar-carne', name: 'admin-member-card-verify', component: AdminMemberCardVerifyView, meta: { adminLabel: 'Verificar carné', requiredPermission: 'members.read', title: 'Verificar carné | Administración GymTrack' } },
      { path: 'empleados', name: 'admin-staff', component: AdminPeopleView, props: { kind: 'staff' }, meta: { adminLabel: 'Empleados', requiredPermission: 'staff.manage', title: 'Empleados | Administración GymTrack' } },
      { path: 'entrenadores', name: 'admin-trainers', component: AdminTrainersView, meta: { adminLabel: 'Entrenadores', requiredPermission: 'staff.manage', title: 'Entrenadores | Administración GymTrack' } },
      { path: 'clases', name: 'admin-classes', component: AdminScheduleView, props: { mode: 'classes' }, meta: { adminLabel: 'Clases', requiredPermission: 'classes.read', title: 'Clases | Administración GymTrack' } },
      { path: 'reservas', name: 'admin-reservations', component: AdminScheduleView, props: { mode: 'reservations' }, meta: { adminLabel: 'Reservas', requiredPermission: 'reservations.read', title: 'Reservas | Administración GymTrack' } },
      { path: 'membresias', name: 'admin-memberships', component: AdminMembershipsView, meta: { adminLabel: 'Membresías', requiredPermission: 'memberships.read', title: 'Membresías | Administración GymTrack' } },
      { path: 'pagos', name: 'admin-payments', component: AdminPaymentsView, meta: { adminLabel: 'Pagos', requiredPermission: 'payments.read', title: 'Pagos | Administración GymTrack' } },
      { path: 'promociones', name: 'admin-promotions', component: AdminPromotionsView, meta: { adminLabel: 'Promociones', requiredPermission: 'promotions.read', title: 'Promociones | Administración GymTrack' } },
      { path: 'finanzas', name: 'admin-finance', component: AdminFinanceView, meta: { adminLabel: 'Finanzas', requiredPermission: 'finance.read', title: 'Finanzas | Administración GymTrack' } },
      { path: 'reportes', name: 'admin-reports', component: AdminReportsView, meta: { adminLabel: 'Reportes', requiredPermission: 'reports.export', title: 'Reportes | Administración GymTrack' } },
      { path: 'configuracion', name: 'admin-settings', component: AdminSettingsView, meta: { adminLabel: 'Configuración', requiredPermission: 'gym.configure', title: 'Configuración | Administración GymTrack' } },
      { path: 'configuracion/nuevo-gimnasio', name: 'admin-gym-create', component: AdminSettingsView, props: { create: true }, meta: { adminLabel: 'Nuevo gimnasio', requiredPermission: 'gym.configure', title: 'Nuevo gimnasio | Administración GymTrack' } },
    ],
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
  if (!authStore.initialized && to.meta.requiresAuth) await authStore.bootstrap()
  else if (!authStore.initialized && (to.meta.publicOnly || to.meta.allowAuthenticated)) await authStore.probe()
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
