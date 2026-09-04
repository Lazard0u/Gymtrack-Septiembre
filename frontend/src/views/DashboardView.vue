<!--
  Vista de ruta DashboardView. Coordina componentes, estado reactivo y llamadas a la API para completar este flujo de usuario.
  En <script> se declaran imports, estado y funciones; <template> describe la interfaz y <style> limita su presentación.
-->
<script setup>
import { computed, onMounted, ref } from 'vue'
import { useRouter } from 'vue-router'
import { IconBuilding, IconCalendarEvent, IconFlask, IconLogout, IconShieldCheck, IconUsersGroup } from '@tabler/icons-vue'
import { api } from '../services/api'
import { useAuthStore } from '../stores/auth'
import { useMemberStore } from '../stores/member'
import { useNotificationsStore } from '../stores/notifications'
import { useSystemStore } from '../stores/system'
import MemberBottomNav from '../components/member/MemberBottomNav.vue'
import MemberDashboardPanel from '../components/member/MemberDashboardPanel.vue'
import MemberTopNav from '../components/member/MemberTopNav.vue'
import PublicBottomNav from '../components/public/PublicBottomNav.vue'
import PublicFooter from '../components/public/PublicFooter.vue'
import AppBadge from '../components/ui/AppBadge.vue'
import AppButton from '../components/ui/AppButton.vue'
import AppCard from '../components/ui/AppCard.vue'
import AppEmptyState from '../components/ui/AppEmptyState.vue'
import AppErrorState from '../components/ui/AppErrorState.vue'
import AppLinkButton from '../components/ui/AppLinkButton.vue'
import AppSkeleton from '../components/ui/AppSkeleton.vue'
import AppSelect from '../components/ui/AppSelect.vue'
import brandMark from '../assets/gymtrack-mark.svg'

const router = useRouter()
const auth = useAuthStore()
const member = useMemberStore()
const notifications = useNotificationsStore()
const system = useSystemStore()
const status = ref('loading')
const gyms = ref([])
const switchingGym = ref(false)
const canAccessAdministration = computed(() => ['empleado', 'dueño', 'admin_general'].includes(auth.user?.role))

const role = computed(() => ({
  socio: { name: 'Socio', description: 'Consultá tu próxima clase, progreso, membresía, pagos y promociones del gimnasio activo.' },
  admin_general: { name: 'Administrador general', description: 'Supervisá la plataforma y accedé a la administración existente.' },
  empleado: { name: 'Empleado', description: 'Consultá los gimnasios donde tenés una asociación operativa.' },
  'dueño': { name: 'Dueño', description: 'Consultá los gimnasios vinculados a tu cuenta de propietario.' },
}[auth.user?.role] || { name: 'Usuario', description: 'Consultá el contexto disponible para tu cuenta.' }))

const contexts = computed(() => auth.user?.gimnasios || [])
const visibleGyms = computed(() => {
  if (auth.esAdmin) return gyms.value
  const ids = new Set(contexts.value.map((context) => context.gimnasio_id))
  return gyms.value.filter((gym) => ids.has(gym.id))
})
const activeClasses = computed(() => visibleGyms.value.reduce((total, gym) => total + gym.clases_activas, 0))
const summaryGyms = computed(() => visibleGyms.value.slice(0, 3))
const metrics = computed(() => [
  { label: auth.esAdmin ? 'Gimnasios publicados' : 'Gimnasios asociados', value: visibleGyms.value.length, icon: IconBuilding },
  { label: 'Clases visibles', value: activeClasses.value, icon: IconCalendarEvent },
  { label: 'Rol activo', value: role.value.name, icon: auth.esAdmin ? IconShieldCheck : IconUsersGroup },
])
const contextOptions = computed(() => auth.esAdmin ? [] : contexts.value.map((context) => ({ value: String(context.gimnasio_id), label: `${context.nombre} (${context.rol_nombre})` })))
const betaDefinitions = {
  whatsapp: { name: 'WhatsApp - Beta', works: 'Las preferencias y el consentimiento se guardan, y el canal falla cerrado si no hay proveedor.', pending: 'El adaptador de envío todavía no está configurado.' },
  google_calendar_sync: { name: 'Sincronización automática con Google Calendar - Beta', works: 'Las reservas permiten abrir Google Calendar y descargar un archivo ICS con datos reales.', pending: 'OAuth y la sincronización automática de cambios todavía no están conectados.' },
  advanced_analytics: { name: 'Analítica avanzada - Beta', works: 'Finanzas y reportes muestran métricas operativas reales.', pending: 'La segmentación predictiva y los indicadores avanzados continúan desactivados.' },
  personal_recommendations: { name: 'Recomendaciones personalizadas - Beta', works: 'El socio puede guardar gimnasios, actividades y horarios preferidos.', pending: 'No se generan recomendaciones automáticas.' },
}
const enabledBetaFeatures = computed(() => system.betaFeatures.map((key) => betaDefinitions[key]).filter(Boolean))

async function load() {
  status.value = 'loading'
  if (!auth.user && !(await auth.cargarPerfil())) {
    await router.replace({ name: 'login' })
    return
  }
  if (auth.user?.role === 'socio') {
    status.value = 'ready'
    return
  }
  const response = await api.get('/public/gimnasios')
  if (!response.ok || response.data.error) {
    status.value = 'error'
    return
  }
  gyms.value = response.data.gimnasios || []
  status.value = 'ready'
}

async function logout() {
  await auth.logout()
  member.reset()
  notifications.reset()
  await router.push({ name: 'home' })
}

async function switchGym(value) {
  switchingGym.value = true
  try {
    if (value === '') await auth.limpiarGimnasio()
    else await auth.cambiarGimnasio(Number(value))
    member.reset()
    notifications.reset()
  } finally { switchingGym.value = false }
}

onMounted(load)
</script>

<template>
  <div class="dashboard-shell">
    <header v-if="auth.user?.role !== 'socio'" class="dashboard-nav">
      <div class="container dashboard-nav__inner">
        <RouterLink class="brand" :to="{ name: 'home' }" aria-label="GymTrack, ir al inicio"><img class="brand-mark" :src="brandMark" alt="" width="36" height="36" /><span class="brand-word">Gym<span>Track</span></span></RouterLink>
        <nav aria-label="Navegación del panel"><RouterLink :to="{ name: 'home' }">Inicio</RouterLink><RouterLink :to="{ name: 'gyms' }">Gimnasios</RouterLink><RouterLink v-if="auth.user?.role === 'socio' && auth.tienePermiso('classes.read')" :to="{ name: 'member-schedule' }">Agenda</RouterLink><RouterLink v-if="auth.user?.role === 'socio' && auth.tienePermiso('payments.read')" :to="{ name: 'member-payments' }">Pagos</RouterLink><RouterLink :to="{ name: 'sessions' }">Seguridad</RouterLink><RouterLink v-if="canAccessAdministration" :to="{ name: 'admin-summary' }">Administración</RouterLink></nav>
        <AppButton variant="ghost" size="sm" @click="logout"><template #icon><IconLogout :size="17" /></template>Salir</AppButton>
      </div>
    </header>
    <MemberTopNav v-else />

    <main class="container dashboard-main">
      <section class="dashboard-heading">
        <div><span class="eyebrow">Mi panel</span><h1>Hola, {{ auth.user?.nombre }}</h1><p>{{ role.description }}</p></div>
        <div class="dashboard-heading__meta"><AppBadge :tone="auth.user?.is_demo ? 'info' : 'neutral'">{{ role.name }}</AppBadge><AppBadge v-if="auth.user?.is_demo" tone="info">Cuenta demo</AppBadge></div>
      </section>

      <div v-if="contextOptions.length" class="context-switcher"><AppSelect :model-value="String(auth.user?.active_gym_id || '')" label="Contexto activo" :options="contextOptions" :disabled="switchingGym" hint="Los permisos y las consultas protegidas usan este gimnasio." @update:model-value="switchGym" /></div>

      <div v-if="status === 'loading'" class="metric-grid" aria-label="Cargando panel"><AppSkeleton v-for="index in 3" :key="index" height="8rem" /></div>
      <AppErrorState v-else-if="status === 'error'" class="dashboard-state" @retry="load" />
      <template v-else>
        <MemberDashboardPanel v-if="auth.user?.role === 'socio'" />
        <template v-else>
        <section class="metric-grid" aria-label="Resumen real">
          <AppCard v-for="metric in metrics" :key="metric.label" class="metric"><component :is="metric.icon" :size="22" /><span>{{ metric.label }}</span><strong>{{ metric.value }}</strong></AppCard>
        </section>

        <section v-if="canAccessAdministration" class="admin-cta"><div><span class="eyebrow">Acción principal</span><h2>Continuá con la operación del día.</h2><p>Socios, clases, reservas, pagos y reportes están disponibles en el contexto activo.</p></div><AppLinkButton :to="{ name: 'admin-summary' }">Abrir administración</AppLinkButton></section>

        <section class="dashboard-section" aria-labelledby="gyms-heading">
          <div class="section-title"><div><span class="eyebrow">Actividad reciente</span><h2 id="gyms-heading">{{ auth.esAdmin ? 'Gimnasios bajo supervisión' : 'Contextos disponibles' }}</h2></div><AppLinkButton :to="{ name: 'gyms' }" variant="secondary" size="sm">Ver todos</AppLinkButton></div>
          <AppEmptyState v-if="visibleGyms.length === 0" title="No hay gimnasios asociados" description="La cuenta no tiene todavía una asociación de gimnasio. No mostramos tarjetas de ejemplo desde Vue.">
            <template #action><AppLinkButton :to="{ name: 'gyms' }" variant="secondary">Ver gimnasios publicados</AppLinkButton></template>
          </AppEmptyState>
          <div v-else class="gym-grid">
            <article v-for="gym in summaryGyms" :key="gym.id" class="gym-card">
              <div class="gym-card__title"><h3>{{ gym.nombre }}</h3><AppBadge v-if="gym.estado === 'temporalmente_cerrado'" tone="warning">Cerrado temporalmente</AppBadge></div>
              <p>{{ gym.ciudad }}, {{ gym.departamento }}</p>
              <div class="gym-card__facts"><span>{{ gym.clases_activas }} clases activas</span><span>{{ gym.membresias_activas }} membresías</span></div>
            </article>
          </div>
        </section>
        </template>

        <details v-if="enabledBetaFeatures.length" class="beta-section"><summary><span><IconFlask :size="19" /><strong>Funciones beta habilitadas</strong><small>{{ enabledBetaFeatures.length }} módulos opcionales</small></span><span aria-hidden="true">+</span></summary><div class="beta-grid"><AppCard v-for="feature in enabledBetaFeatures" :key="feature.name"><h3>{{ feature.name }}</h3><p><strong>Funciona:</strong> {{ feature.works }}</p><p><strong>Pendiente:</strong> {{ feature.pending }}</p></AppCard></div></details>
      </template>
    </main>
    <PublicFooter />
    <MemberBottomNav v-if="auth.user?.role === 'socio'" />
    <PublicBottomNav v-else />
  </div>
</template>

<style scoped>
.dashboard-shell { min-height: 100vh; background: var(--bg-canvas); }.dashboard-nav { position: sticky; top: 0; z-index: var(--z-header); border-bottom: 1px solid var(--border-glass); background: var(--surface-glass); backdrop-filter: blur(16px); }.dashboard-nav__inner { display: flex; min-height: var(--header-height); align-items: center; gap: var(--space-8); }.brand { display: inline-flex; align-items: center; gap: var(--space-2); text-decoration: none; }.dashboard-nav nav { display: flex; gap: var(--space-5); margin-left: auto; }.dashboard-nav nav a { color: var(--text-secondary); font-size: .84rem; font-weight: 650; text-decoration: none; }.dashboard-nav nav a:hover, .dashboard-nav nav a.router-link-active { color: var(--text-primary); }
.dashboard-main { min-height: calc(100vh - var(--header-height)); padding-bottom: var(--space-16); }.dashboard-heading { display: flex; align-items: flex-end; justify-content: space-between; gap: var(--space-8); padding-block: clamp(var(--space-8), 4vw, var(--space-12)); }.dashboard-heading h1 { margin-bottom: var(--space-3); font-size: clamp(2.2rem, 5vw, 3.5rem); }.dashboard-heading p { max-width: 42rem; margin: 0; color: var(--text-secondary); }.dashboard-heading__meta { display: flex; flex-wrap: wrap; justify-content: flex-end; gap: var(--space-2); }
.context-switcher { width: min(100%, 30rem); margin-top: var(--space-4); }
.metric-grid { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: var(--space-3); margin: var(--space-5) 0; }.metric { display: grid; min-height: 6.75rem; grid-template-columns: auto minmax(0,1fr); align-content: center; gap: .1rem var(--space-3); padding: var(--space-5) !important; }.metric svg { grid-row: 1/3; color: var(--info); }.metric span { color: var(--text-secondary); font-size: .72rem; }.metric strong { overflow-wrap: anywhere; font-size: clamp(1.15rem, 2vw, 1.55rem); letter-spacing: -.03em; }
.dashboard-section { margin-top: var(--space-6); padding-block: var(--space-6); border-top: 1px solid var(--border-subtle); }.section-title { display: flex; align-items: flex-end; justify-content: space-between; gap: var(--space-6); margin-bottom: var(--space-4); }.section-title h2 { margin: 0; font-size: clamp(1.35rem,3vw,2rem); }.gym-grid { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); border-block: 1px solid var(--border-subtle); }.gym-card { min-width: 0; padding: var(--space-4); border-right: 1px solid var(--border-subtle); }.gym-card:last-child { border-right: 0; }.gym-card__title { display: flex; align-items: flex-start; justify-content: space-between; gap: var(--space-3); }.gym-card h3 { margin: 0; font-size: .95rem; }.gym-card p { margin: var(--space-2) 0; color: var(--text-secondary); font-size: .76rem; }.gym-card__facts { display: flex; flex-wrap: wrap; gap: var(--space-3); color: var(--text-tertiary); font-size: .68rem; }
.admin-cta { display: flex; align-items: center; justify-content: space-between; gap: var(--space-8); border: 1px solid var(--border-subtle); border-radius: var(--radius-card); padding: var(--space-5) var(--space-6); background: var(--surface-1); }.admin-cta .eyebrow { margin-bottom: var(--space-2); }.admin-cta h2 { margin-bottom: var(--space-2); font-size: clamp(1.25rem,3vw,1.75rem); }.admin-cta p { max-width: 42rem; margin: 0; color: var(--text-secondary); font-size: .82rem; }.dashboard-state { min-height: 20rem; }
.member-actions { display: flex; gap: var(--space-2); }
.beta-section { margin-top: var(--space-4); border-bottom: 1px solid var(--border-subtle); }.beta-section > summary { display: flex; min-height: 3.75rem; align-items: center; justify-content: space-between; cursor: pointer; list-style: none; }.beta-section > summary::-webkit-details-marker { display: none; }.beta-section > summary > span:first-child { display: grid; grid-template-columns: auto 1fr; align-items: center; gap: 0 var(--space-3); }.beta-section > summary svg { grid-row: 1/3; color: var(--warning); }.beta-section > summary small { color: var(--text-tertiary); font-size: .7rem; }.beta-section > summary > span:last-child { color: var(--text-tertiary); font-size: 1.25rem; transition: transform var(--duration-normal) var(--ease-out); }.beta-section[open] > summary > span:last-child { transform: rotate(45deg); }.beta-grid { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: var(--space-3); padding-bottom: var(--space-4); }.beta-grid h3 { margin-bottom: var(--space-3); font-size: 1rem; }.beta-grid p { margin: var(--space-2) 0 0; color: var(--text-secondary); font-size: .78rem; }.beta-grid strong { color: var(--text-primary); }
@media (max-width: 63.99rem) { .dashboard-shell { padding-bottom: calc(4.25rem + env(safe-area-inset-bottom)); }.gym-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); } }
@media (max-width: 47.99rem) { .dashboard-nav nav { display: none; }.dashboard-nav__inner { gap: var(--space-3); }.dashboard-nav__inner > :last-child { margin-left: auto; }.dashboard-heading { align-items: flex-start; flex-direction: column; padding-block: var(--space-6); }.dashboard-heading__meta { justify-content: flex-start; }.metric-grid { grid-template-columns: 1fr; }.gym-grid { display: block; }.gym-card { border-right: 0; border-bottom: 1px solid var(--border-subtle); }.gym-card:last-child { border-bottom: 0; }.beta-grid { grid-template-columns: 1fr; }.section-title, .admin-cta { align-items: flex-start; flex-direction: column; }.section-title > :last-child, .admin-cta > :last-child { width: 100%; }.member-actions { display: grid; width: 100%; grid-template-columns: 1fr; } }
</style>
