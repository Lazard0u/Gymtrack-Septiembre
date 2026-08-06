<script setup>
import { computed, onMounted, ref } from 'vue'
import { useRouter } from 'vue-router'
import { IconBuilding, IconCalendarEvent, IconFlask, IconLogout, IconShieldCheck, IconUsersGroup } from '@tabler/icons-vue'
import { api } from '../services/api'
import { useAuthStore } from '../stores/auth'
import { useSystemStore } from '../stores/system'
import PublicFooter from '../components/public/PublicFooter.vue'
import AppAlert from '../components/ui/AppAlert.vue'
import AppBadge from '../components/ui/AppBadge.vue'
import AppButton from '../components/ui/AppButton.vue'
import AppCard from '../components/ui/AppCard.vue'
import AppEmptyState from '../components/ui/AppEmptyState.vue'
import AppErrorState from '../components/ui/AppErrorState.vue'
import AppLinkButton from '../components/ui/AppLinkButton.vue'
import AppSkeleton from '../components/ui/AppSkeleton.vue'
import brandMark from '../assets/gymtrack-mark.svg'

const router = useRouter()
const auth = useAuthStore()
const system = useSystemStore()
const status = ref('loading')
const gyms = ref([])

const role = computed(() => ({
  1: { name: 'Socio', description: 'Explorá gimnasios y consultá los contextos asociados a tu cuenta.' },
  2: { name: 'Administrador general', description: 'Supervisá la plataforma y accedé a la administración existente.' },
  3: { name: 'Empleado', description: 'Consultá los gimnasios donde tenés una asociación operativa.' },
  4: { name: 'Dueño', description: 'Consultá los gimnasios vinculados a tu cuenta de propietario.' },
}[auth.user?.rol_id] || { name: 'Usuario', description: 'Consultá el contexto disponible para tu cuenta.' }))

const contexts = computed(() => auth.user?.gimnasios || [])
const visibleGyms = computed(() => {
  if (auth.user?.rol_id === 2) return gyms.value
  const ids = new Set(contexts.value.map((context) => context.gimnasio_id))
  return gyms.value.filter((gym) => ids.has(gym.id))
})
const activeClasses = computed(() => visibleGyms.value.reduce((total, gym) => total + gym.clases_activas, 0))
const metrics = computed(() => [
  { label: auth.user?.rol_id === 2 ? 'Gimnasios publicados' : 'Gimnasios asociados', value: visibleGyms.value.length, icon: IconBuilding },
  { label: 'Clases visibles', value: activeClasses.value, icon: IconCalendarEvent },
  { label: 'Rol activo', value: role.value.name, icon: auth.user?.rol_id === 2 ? IconShieldCheck : IconUsersGroup },
])
const betaDefinitions = {
  whatsapp: { name: 'WhatsApp — Beta', works: 'La interfaz puede identificar el canal configurado.', pending: 'Todavía no envía mensajes ni registra entregas.' },
  google_calendar_sync: { name: 'Sincronización automática con Google Calendar — Beta', works: 'La función puede presentarse como capacidad opcional.', pending: 'OAuth y sincronización bidireccional todavía no están conectados.' },
  advanced_analytics: { name: 'Analítica avanzada — Beta', works: 'La sección puede comunicar su alcance futuro.', pending: 'No calcula indicadores ni muestra gráficas simuladas.' },
  personal_recommendations: { name: 'Recomendaciones personalizadas — Beta', works: 'La preferencia puede habilitarse mediante feature flag.', pending: 'No genera recomendaciones automáticas todavía.' },
  promotions_beta: { name: 'Promociones — Beta', works: 'El dataset puede mostrar el escenario promocional informativo.', pending: 'No envía campañas ni aplica descuentos automáticamente.' },
}
const enabledBetaFeatures = computed(() => system.betaFeatures.map((key) => betaDefinitions[key]).filter(Boolean))

async function load() {
  status.value = 'loading'
  if (!auth.user && !(await auth.cargarPerfil())) {
    await router.replace({ name: 'login' })
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
  await router.push({ name: 'home' })
}

onMounted(load)
</script>

<template>
  <div class="dashboard-shell">
    <header class="dashboard-nav">
      <div class="container dashboard-nav__inner">
        <RouterLink class="brand" :to="{ name: 'home' }" aria-label="GymTrack, ir al inicio"><img class="brand-mark" :src="brandMark" alt="" width="36" height="36" /><span class="brand-word">Gym<span>Track</span></span></RouterLink>
        <nav aria-label="Navegación del panel"><RouterLink :to="{ name: 'home' }">Inicio</RouterLink><RouterLink :to="{ name: 'gyms' }">Gimnasios</RouterLink><RouterLink v-if="auth.esAdmin" :to="{ name: 'admin' }">Administración</RouterLink></nav>
        <AppButton variant="ghost" size="sm" @click="logout"><template #icon><IconLogout :size="17" /></template>Salir</AppButton>
      </div>
    </header>

    <main class="container dashboard-main">
      <section class="dashboard-heading">
        <div><span class="eyebrow">Mi panel</span><h1>Hola, {{ auth.user?.nombre }}</h1><p>{{ role.description }}</p></div>
        <div class="dashboard-heading__meta"><AppBadge :tone="auth.user?.is_demo ? 'info' : 'neutral'">{{ role.name }}</AppBadge><AppBadge v-if="auth.user?.is_demo" tone="info">Cuenta demo</AppBadge></div>
      </section>

      <AppAlert v-if="auth.user?.is_demo" tone="info" title="Contexto de presentación"><p>Esta cuenta y sus asociaciones están identificadas como datos de demostración y pueden eliminarse sin afectar registros reales.</p></AppAlert>

      <div v-if="status === 'loading'" class="metric-grid" aria-label="Cargando panel"><AppSkeleton v-for="index in 3" :key="index" height="8rem" /></div>
      <AppErrorState v-else-if="status === 'error'" class="dashboard-state" @retry="load" />
      <template v-else>
        <section class="metric-grid" aria-label="Resumen real">
          <AppCard v-for="metric in metrics" :key="metric.label" class="metric"><component :is="metric.icon" :size="22" /><span>{{ metric.label }}</span><strong>{{ metric.value }}</strong></AppCard>
        </section>

        <section class="dashboard-section" aria-labelledby="gyms-heading">
          <div class="section-title"><div><span class="eyebrow">Contexto de gimnasio</span><h2 id="gyms-heading">{{ auth.esAdmin ? 'Catálogo publicado' : 'Tus asociaciones' }}</h2></div><AppLinkButton :to="{ name: 'gyms' }" variant="secondary" size="sm">Explorar mapa</AppLinkButton></div>
          <AppEmptyState v-if="visibleGyms.length === 0" title="No hay gimnasios asociados" description="La cuenta no tiene todavía una asociación de gimnasio. No mostramos tarjetas de ejemplo desde Vue.">
            <template #action><AppLinkButton :to="{ name: 'gyms' }" variant="secondary">Ver gimnasios publicados</AppLinkButton></template>
          </AppEmptyState>
          <div v-else class="gym-grid">
            <AppCard v-for="gym in visibleGyms" :key="gym.id" :padded="false" class="gym-card">
              <img :src="gym.imagen_path" :alt="`Placeholder de ${gym.nombre}`" width="800" height="500" />
              <div class="gym-card__body"><div class="gym-card__title"><h3>{{ gym.nombre }}</h3><AppBadge v-if="gym.estado === 'temporalmente_cerrado'" tone="warning">Cerrado temporalmente</AppBadge></div><p>{{ gym.ciudad }}, {{ gym.departamento }}</p><div class="gym-card__facts"><span>{{ gym.clases_activas }} clases</span><span>{{ gym.categorias.join(' · ') }}</span></div></div>
            </AppCard>
          </div>
        </section>

        <section v-if="auth.esAdmin" class="admin-cta"><div><span class="eyebrow">Administración</span><h2>La cuenta tiene permiso global.</h2><p>El panel administrativo actual continúa disponible. Su reorganización operativa completa pertenece a la Fase 4.</p></div><AppLinkButton :to="{ name: 'admin' }">Abrir administración</AppLinkButton></section>

        <section v-if="enabledBetaFeatures.length" class="beta-section" aria-labelledby="beta-heading"><div class="section-title"><div><span class="eyebrow">Funciones opcionales</span><h2 id="beta-heading">Beta, sin acciones falsas.</h2></div></div><div class="beta-grid"><AppCard v-for="feature in enabledBetaFeatures" :key="feature.name"><IconFlask :size="21" /><h3>{{ feature.name }}</h3><p><strong>Funciona:</strong> {{ feature.works }}</p><p><strong>Pendiente:</strong> {{ feature.pending }}</p></AppCard></div></section>
      </template>
    </main>
    <PublicFooter />
  </div>
</template>

<style scoped>
.dashboard-shell { min-height: 100vh; background: var(--bg-canvas); }.dashboard-nav { position: sticky; top: 0; z-index: var(--z-header); border-bottom: 1px solid var(--border-glass); background: var(--surface-glass); backdrop-filter: blur(16px); }.dashboard-nav__inner { display: flex; min-height: var(--header-height); align-items: center; gap: var(--space-8); }.brand { display: inline-flex; align-items: center; gap: var(--space-2); text-decoration: none; }.dashboard-nav nav { display: flex; gap: var(--space-5); margin-left: auto; }.dashboard-nav nav a { color: var(--text-secondary); font-size: .84rem; font-weight: 650; text-decoration: none; }.dashboard-nav nav a:hover, .dashboard-nav nav a.router-link-active { color: var(--text-primary); }
.dashboard-main { min-height: calc(100vh - var(--header-height)); padding-bottom: var(--space-20); }.dashboard-heading { display: flex; align-items: flex-end; justify-content: space-between; gap: var(--space-8); padding-block: clamp(var(--space-12), 7vw, var(--space-20)); }.dashboard-heading h1 { margin-bottom: var(--space-4); font-size: clamp(2.5rem, 6vw, 4.5rem); }.dashboard-heading p { max-width: 42rem; margin: 0; color: var(--text-secondary); }.dashboard-heading__meta { display: flex; flex-wrap: wrap; justify-content: flex-end; gap: var(--space-2); }
.metric-grid { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: var(--space-4); margin: var(--space-8) 0 var(--space-16); }.metric { display: grid; min-height: 9rem; align-content: space-between; gap: var(--space-3); }.metric svg { color: var(--info); }.metric span { color: var(--text-secondary); font-size: .78rem; }.metric strong { overflow-wrap: anywhere; font-size: clamp(1.35rem, 3vw, 2rem); letter-spacing: -.03em; }
.dashboard-section { padding-block: var(--space-12); border-top: 1px solid var(--border-subtle); }.section-title { display: flex; align-items: flex-end; justify-content: space-between; gap: var(--space-6); margin-bottom: var(--space-8); }.section-title h2 { margin: 0; }.gym-grid { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: var(--space-4); }.gym-card { overflow: hidden; }.gym-card > img { width: 100%; aspect-ratio: 16 / 10; object-fit: cover; }.gym-card__body { padding: var(--space-5); }.gym-card__title { display: flex; align-items: flex-start; justify-content: space-between; gap: var(--space-3); }.gym-card h3 { margin: 0; font-size: 1.05rem; }.gym-card p { margin: var(--space-2) 0; color: var(--text-secondary); font-size: .82rem; }.gym-card__facts { display: flex; flex-wrap: wrap; gap: var(--space-3); color: var(--text-tertiary); font-size: .72rem; }
.admin-cta { display: flex; align-items: flex-end; justify-content: space-between; gap: var(--space-8); margin-top: var(--space-12); border: 1px solid var(--border-subtle); border-radius: var(--radius-dialog); padding: clamp(var(--space-6), 5vw, var(--space-10)); background: var(--surface-1); }.admin-cta h2 { margin-bottom: var(--space-3); }.admin-cta p { max-width: 42rem; margin: 0; color: var(--text-secondary); }.dashboard-state { min-height: 20rem; }
.beta-section { margin-top: var(--space-16); padding-top: var(--space-12); border-top: 1px solid var(--border-subtle); }.beta-grid { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: var(--space-4); }.beta-grid svg { margin-bottom: var(--space-6); color: var(--warning); }.beta-grid h3 { margin-bottom: var(--space-4); }.beta-grid p { margin: var(--space-2) 0 0; color: var(--text-secondary); font-size: .82rem; }.beta-grid strong { color: var(--text-primary); }
@media (max-width: 63.99rem) { .gym-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); } }
@media (max-width: 47.99rem) { .dashboard-nav nav { display: none; }.dashboard-nav__inner { gap: var(--space-3); }.dashboard-nav__inner > :last-child { margin-left: auto; }.dashboard-heading { align-items: flex-start; flex-direction: column; }.dashboard-heading__meta { justify-content: flex-start; }.metric-grid, .gym-grid, .beta-grid { grid-template-columns: 1fr; }.section-title, .admin-cta { align-items: flex-start; flex-direction: column; }.section-title > :last-child, .admin-cta > :last-child { width: 100%; } }
</style>
