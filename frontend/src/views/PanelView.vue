<script setup>
import { computed, onMounted, ref } from 'vue'
import { useRouter } from 'vue-router'
import { IconFlask } from '@tabler/icons-vue'
import { useAuthStore } from '../stores/auth'
import { useSocioStore } from '../stores/socio'
import { useNotificacionesStore } from '../stores/notificaciones'
import { useSystemStore } from '../stores/system'
import SocioBottomNav from '../components/member/SocioBottomNav.vue'
import SocioPanel from '../components/member/SocioPanel.vue'
import SocioTopNav from '../components/member/SocioTopNav.vue'
import PublicFooter from '../components/public/PublicFooter.vue'
import AppBadge from '../components/ui/AppBadge.vue'
import AppCard from '../components/ui/AppCard.vue'
import AppSkeleton from '../components/ui/AppSkeleton.vue'
import AppSelect from '../components/ui/AppSelect.vue'

const router = useRouter()
const auth = useAuthStore()
const member = useSocioStore()
const notifications = useNotificacionesStore()
const system = useSystemStore()
const status = ref('loading')
const switchingGym = ref(false)

const contextOptions = computed(() => (auth.user?.gimnasios || []).map((context) => ({ value: String(context.gimnasio_id), label: `${context.nombre} (${context.rol_nombre})` })))
const betaDefinitions = {
  whatsapp: { name: 'WhatsApp - Beta', works: 'Las preferencias y el consentimiento se guardan, y el canal falla cerrado si no hay proveedor.', pending: 'El adaptador de envío todavía no está configurado.' },
  google_calendar_sync: { name: 'Sincronización automática con Google Calendar - Beta', works: 'Las reservas permiten abrir Google Calendar y descargar un archivo ICS con datos reales.', pending: 'OAuth y la sincronización automática de cambios todavía no están conectados.' },
  advanced_analytics: { name: 'Analítica avanzada - Beta', works: 'Finanzas y reportes muestran métricas operativas reales.', pending: 'La segmentación predictiva y los indicadores avanzados continúan desactivados.' },
  personal_recommendations: { name: 'Recomendaciones personalizadas - Beta', works: 'El socio puede guardar gimnasios, actividades y horarios preferidos.', pending: 'No se generan recomendaciones automáticas.' },
}
const enabledBetaFeatures = computed(() => system.betaFeatures.map((key) => betaDefinitions[key]).filter(Boolean))

// esta pantalla es exclusiva del socio; el resto de los roles va directo a su panel de administración
async function load() {
  status.value = 'loading'
  if (!auth.user && !(await auth.cargarPerfil())) {
    await router.replace({ name: 'login' })
    return
  }
  if (auth.user?.role !== 'socio') {
    await router.replace({ name: 'admin-summary' })
    return
  }
  status.value = 'ready'
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
  <div v-if="status === 'ready'" class="dashboard-shell">
    <SocioTopNav />

    <main class="container dashboard-main">
      <section class="dashboard-heading">
        <div><span class="eyebrow">Mi panel</span><h1>Hola, {{ auth.user?.nombre }}</h1><p>Consultá tu próxima clase, progreso, membresía, pagos y promociones del gimnasio activo.</p></div>
        <div class="dashboard-heading__meta"><AppBadge tone="neutral">Socio</AppBadge><AppBadge v-if="auth.user?.is_demo" tone="info">Cuenta demo</AppBadge></div>
      </section>

      <div v-if="contextOptions.length" class="context-switcher"><AppSelect :model-value="String(auth.user?.active_gym_id || '')" label="Contexto activo" :options="contextOptions" :disabled="switchingGym" hint="Los permisos y las consultas protegidas usan este gimnasio." @update:model-value="switchGym" /></div>

      <SocioPanel />

      <details v-if="enabledBetaFeatures.length" class="beta-section"><summary><span><IconFlask :size="19" /><strong>Funciones beta habilitadas</strong><small>{{ enabledBetaFeatures.length }} módulos opcionales</small></span><span aria-hidden="true">+</span></summary><div class="beta-grid"><AppCard v-for="feature in enabledBetaFeatures" :key="feature.name"><h3>{{ feature.name }}</h3><p><strong>Funciona:</strong> {{ feature.works }}</p><p><strong>Pendiente:</strong> {{ feature.pending }}</p></AppCard></div></details>
    </main>
    <PublicFooter />
    <SocioBottomNav />
  </div>
  <div v-else class="dashboard-loading" aria-label="Cargando panel"><AppSkeleton height="8rem" /></div>
</template>

<style scoped>
.dashboard-shell { min-height: 100vh; background: var(--bg-canvas); }
.dashboard-loading { min-height: 100vh; padding: var(--space-8); background: var(--bg-canvas); }
.dashboard-main { min-height: calc(100vh - var(--header-height)); padding-bottom: var(--space-16); }.dashboard-heading { display: flex; align-items: flex-end; justify-content: space-between; gap: var(--space-8); padding-block: clamp(var(--space-5), 3vw, var(--space-7)); }.dashboard-heading h1 { margin-bottom: var(--space-2); font-size: clamp(1.5rem, 3.5vw, 2.1rem); }.dashboard-heading p { max-width: 42rem; margin: 0; color: var(--text-secondary); font-size: .88rem; }.dashboard-heading__meta { display: flex; flex-wrap: wrap; justify-content: flex-end; gap: var(--space-2); }
.context-switcher { width: min(100%, 30rem); margin-top: var(--space-4); }
.beta-section { margin-top: var(--space-4); border-bottom: 1px solid var(--border-subtle); }.beta-section > summary { display: flex; min-height: 3.75rem; align-items: center; justify-content: space-between; cursor: pointer; list-style: none; }.beta-section > summary::-webkit-details-marker { display: none; }.beta-section > summary > span:first-child { display: grid; grid-template-columns: auto 1fr; align-items: center; gap: 0 var(--space-3); }.beta-section > summary svg { grid-row: 1/3; color: var(--warning); }.beta-section > summary small { color: var(--text-tertiary); font-size: .7rem; }.beta-section > summary > span:last-child { color: var(--text-tertiary); font-size: 1.25rem; transition: transform var(--duration-normal) var(--ease-out); }.beta-section[open] > summary > span:last-child { transform: rotate(45deg); }.beta-grid { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: var(--space-3); padding-bottom: var(--space-4); }.beta-grid h3 { margin-bottom: var(--space-3); font-size: 1rem; }.beta-grid p { margin: var(--space-2) 0 0; color: var(--text-secondary); font-size: .78rem; }.beta-grid strong { color: var(--text-primary); }
@media (max-width: 63.99rem) { .dashboard-shell { padding-bottom: calc(4.25rem + env(safe-area-inset-bottom)); } }
@media (max-width: 47.99rem) { .dashboard-heading { align-items: flex-start; flex-direction: column; padding-block: var(--space-6); }.dashboard-heading__meta { justify-content: flex-start; }.beta-grid { grid-template-columns: 1fr; } }
</style>
