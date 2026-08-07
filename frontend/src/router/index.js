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
const AdminShell = () => import('../layouts/AdminShell.vue')
const AdminSummaryView = () => import('../views/admin/AdminSummaryView.vue')
const AdminOperationsView = () => import('../views/admin/AdminOperationsView.vue')
const AdminResourceView = () => import('../views/admin/AdminResourceView.vue')
const AdminUnavailableView = () => import('../views/admin/AdminUnavailableView.vue')
const AdminPromotionsView = () => import('../views/admin/AdminPromotionsView.vue')
const AdminReportsView = () => import('../views/admin/AdminReportsView.vue')
const AdminSettingsView = () => import('../views/admin/AdminSettingsView.vue')
const AdminPeopleView = () => import('../views/admin/AdminPeopleView.vue')
const AdminTrainersView = () => import('../views/admin/AdminTrainersView.vue')
const AdminMembershipsView = () => import('../views/admin/AdminMembershipsView.vue')
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
      { path: 'empleados', name: 'admin-staff', component: AdminPeopleView, props: { kind: 'staff' }, meta: { adminLabel: 'Empleados', requiredPermission: 'staff.manage', title: 'Empleados | Administración GymTrack' } },
      { path: 'entrenadores', name: 'admin-trainers', component: AdminTrainersView, meta: { adminLabel: 'Entrenadores', requiredPermission: 'staff.manage', title: 'Entrenadores | Administración GymTrack' } },
      { path: 'clases', name: 'admin-classes', component: AdminResourceView, props: { resource: 'classes', title: 'Clases', description: 'Agenda recurrente del gimnasio activo con cupos reales de MySQL.', columns: [{ key: 'nombre', label: 'Clase', sortable: true }, { key: 'instructor_nombre', label: 'Instructor' }, { key: 'dia_semana', label: 'Día', sortable: true }, { key: 'hora_inicio', label: 'Inicio', format: 'time', sortable: true }, { key: 'hora_fin', label: 'Fin', format: 'time' }, { key: 'cupo_maximo', label: 'Cupo' }, { key: 'cupos_disponibles', label: 'Disponibles' }, { key: 'activa', label: 'Activa', format: 'boolean' }], filters: [{ key: 'status', label: 'Estado', options: [{ value: '', label: 'Todas' }, { value: 'activa', label: 'Activas' }, { value: 'inactiva', label: 'Inactivas' }] }, { key: 'day', label: 'Día', options: [{ value: '', label: 'Todos' }, { value: 'lunes', label: 'Lunes' }, { value: 'martes', label: 'Martes' }, { value: 'miercoles', label: 'Miércoles' }, { value: 'jueves', label: 'Jueves' }, { value: 'viernes', label: 'Viernes' }, { value: 'sabado', label: 'Sábado' }, { value: 'domingo', label: 'Domingo' }] }], emptyTitle: 'No hay clases', emptyDescription: 'Este gimnasio no tiene clases o los filtros no devuelven resultados.', statusLabel: 'Consulta estable' }, meta: { adminLabel: 'Clases', requiredPermission: 'classes.read', title: 'Clases | Administración GymTrack' } },
      { path: 'reservas', name: 'admin-reservations', component: AdminResourceView, props: { resource: 'reservations', title: 'Reservas', description: 'Reservas del gimnasio activo sin mezclar registros de otros contextos.', columns: [{ key: 'usuario_nombre', label: 'Socio', sortable: true }, { key: 'usuario_email', label: 'Correo' }, { key: 'clase_nombre', label: 'Clase', sortable: true }, { key: 'dia_semana', label: 'Día' }, { key: 'hora_inicio', label: 'Hora', format: 'time' }, { key: 'estado', label: 'Estado', format: 'status', sortable: true }, { key: 'fecha_reserva', label: 'Registrada', format: 'datetime', sortable: true }], filters: [{ key: 'status', label: 'Estado', options: [{ value: '', label: 'Todos' }, { value: 'confirmada', label: 'Confirmadas' }, { value: 'cancelada', label: 'Canceladas' }, { value: 'asistio', label: 'Asistió' }] }], emptyTitle: 'No hay reservas', emptyDescription: 'No se encontraron reservas para el contexto y los filtros actuales.', statusLabel: 'Consulta estable' }, meta: { adminLabel: 'Reservas', requiredPermission: 'reservations.read', title: 'Reservas | Administración GymTrack' } },
      { path: 'membresias', name: 'admin-memberships', component: AdminMembershipsView, meta: { adminLabel: 'Membresías', requiredPermission: 'memberships.read', title: 'Membresías | Administración GymTrack' } },
      { path: 'pagos', name: 'admin-payments', component: AdminResourceView, props: { resource: 'payments', title: 'Pagos', description: 'Transacciones confirmadas registradas en el esquema actual. Los estados avanzados todavía no están disponibles.', columns: [{ key: 'usuario_nombre', label: 'Socio', sortable: true }, { key: 'usuario_email', label: 'Correo' }, { key: 'plan', label: 'Plan' }, { key: 'monto', label: 'Importe', format: 'money', sortable: true }, { key: 'metodo', label: 'Método', sortable: true }, { key: 'estado', label: 'Estado', format: 'status' }, { key: 'fecha_pago', label: 'Fecha', format: 'datetime', sortable: true }], filters: [{ key: 'method', label: 'Método', options: [{ value: '', label: 'Todos' }, { value: 'efectivo', label: 'Efectivo' }, { value: 'transferencia', label: 'Transferencia' }, { value: 'tarjeta', label: 'Tarjeta' }] }], emptyTitle: 'No hay pagos registrados', emptyDescription: 'No existen transacciones confirmadas para este gimnasio y estos filtros.', statusLabel: 'Consulta estable' }, meta: { adminLabel: 'Pagos', requiredPermission: 'payments.read', title: 'Pagos | Administración GymTrack' } },
      { path: 'promociones', name: 'admin-promotions', component: AdminPromotionsView, meta: { adminLabel: 'Promociones', requiredPermission: 'gym.configure', title: 'Promociones | Administración GymTrack' } },
      { path: 'finanzas', name: 'admin-finance', component: AdminUnavailableView, props: { title: 'Finanzas', description: 'Acceso directo al futuro control financiero del gimnasio.', works: 'La ruta está protegida por finance.read y se encuentra a un clic desde la navegación administrativa.', pending: 'Indicadores, comparaciones, deuda, agrupaciones, filtros y gráficas con datos reales de pagos.', statusLabel: 'En desarrollo' }, meta: { adminLabel: 'Finanzas', requiredPermission: 'finance.read', title: 'Finanzas | Administración GymTrack' } },
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
