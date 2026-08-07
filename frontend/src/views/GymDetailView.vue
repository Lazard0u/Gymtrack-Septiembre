<script setup>
import { computed, onMounted, ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { IconArrowLeft, IconClock, IconMapPin, IconPhone, IconMail, IconPhotoOff, IconRosetteDiscountCheck } from '@tabler/icons-vue'
import PublicHeader from '../components/public/PublicHeader.vue'
import PublicFooter from '../components/public/PublicFooter.vue'
import AppBadge from '../components/ui/AppBadge.vue'
import AppButton from '../components/ui/AppButton.vue'
import AppCard from '../components/ui/AppCard.vue'
import AppEmptyState from '../components/ui/AppEmptyState.vue'
import AppErrorState from '../components/ui/AppErrorState.vue'
import AppLinkButton from '../components/ui/AppLinkButton.vue'
import AppSkeleton from '../components/ui/AppSkeleton.vue'
import { api } from '../services/api'

const route = useRoute(); const router = useRouter()
const gym = ref(null); const plans = ref([]); const status = ref('loading'); const plansStatus = ref('loading')
const isClosed = computed(() => gym.value?.estado === 'temporalmente_cerrado')
const title = computed(() => gym.value?.nombre ? `${gym.value.nombre} | GymTrack` : 'Gimnasio | GymTrack')

async function load() {
  status.value = 'loading'
  const detail = await api.get(`/public/gyms/${encodeURIComponent(route.params.slug)}`)
  if (!detail.ok || detail.data?.error) { status.value = 'error'; return }
  gym.value = detail.data.gimnasio
  document.title = title.value
  status.value = 'ready'
  await loadPlans()
}
async function loadPlans() {
  plansStatus.value = 'loading'
  const plansResponse = await api.get(`/public/gyms/${gym.value.id}/plans`)
  if (!plansResponse.ok || plansResponse.data?.error) { plans.value = []; plansStatus.value = 'error'; return }
  plans.value = plansResponse.data.planes || []
  plansStatus.value = 'ready'
}
function money(value, currency) { return new Intl.NumberFormat('es-UY', { style: 'currency', currency }).format(Number(value || 0)) }
function mapHref(location) { return `https://www.openstreetmap.org/?mlat=${encodeURIComponent(location.latitud)}&mlon=${encodeURIComponent(location.longitud)}#map=16/${encodeURIComponent(location.latitud)}/${encodeURIComponent(location.longitud)}` }
onMounted(load)
</script>

<template>
  <div class="public-page"><PublicHeader /><main class="container gym-detail">
    <button class="back-link" type="button" @click="router.push({ name: 'gyms' })"><IconArrowLeft :size="18" />Volver al mapa</button>
    <div v-if="status === 'loading'" class="detail-loading" role="status"><AppSkeleton height="28rem" /><AppSkeleton height="12rem" /></div>
    <AppErrorState v-else-if="status === 'error'" title="No encontramos este gimnasio" description="Puede que no esté publicado o que el enlace haya cambiado." @retry="load" />
    <template v-else>
      <header class="detail-hero">
        <img v-if="gym.imagen_path" :src="gym.imagen_path" :alt="`Vista de ${gym.nombre}`" width="1200" height="700" />
        <div v-else class="detail-hero__placeholder" role="img" :aria-label="`${gym.nombre} no tiene una imagen pública disponible`"><IconPhotoOff :size="42" /><span>Imagen no disponible</span></div>
        <div class="detail-hero__content"><div class="detail-badges"><AppBadge v-if="gym.is_demo" tone="info">Datos de demostración</AppBadge><AppBadge v-if="isClosed" tone="warning">Cerrado temporalmente</AppBadge></div><p class="detail-location">{{ gym.ciudad }} · {{ gym.departamento }}</p><h1>{{ gym.nombre }}</h1><p>{{ gym.descripcion }}</p><div class="hero-actions"><AppButton v-if="isClosed" variant="secondary" disabled>Inscripciones pausadas</AppButton><AppLinkButton v-else :to="{ name: 'login' }" variant="secondary">Iniciar sesión</AppLinkButton><a v-if="!isClosed && gym.email" :href="`mailto:${gym.email}?subject=${encodeURIComponent(`Consulta sobre membresías en ${gym.nombre}`)}`">Consultar membresía por correo</a></div></div>
      </header>

      <section class="detail-grid" aria-label="Información del gimnasio">
        <AppCard><h2>Información práctica</h2><ul class="facts"><li><IconMapPin :size="19" /><span><strong>{{ gym.direccion }}</strong><small>{{ gym.ciudad }}, {{ gym.departamento }}</small></span></li><li v-if="gym.telefono"><IconPhone :size="19" /><a :href="`tel:${gym.telefono}`">{{ gym.telefono }}</a></li><li v-if="gym.email"><IconMail :size="19" /><a :href="`mailto:${gym.email}`">{{ gym.email }}</a></li><li><IconClock :size="19" /><span><strong>Zona horaria</strong><small>{{ gym.zona_horaria }}</small></span></li></ul></AppCard>
        <AppCard><h2>Servicios</h2><div class="tag-list"><span v-for="service in gym.servicios" :key="service">{{ service }}</span></div><h3>Categorías</h3><div class="tag-list tag-list--soft"><span v-for="category in gym.categorias" :key="category">{{ category }}</span></div></AppCard>
      </section>

      <section class="locations" aria-labelledby="locations-title"><div class="section-heading"><h2 id="locations-title">Sedes publicadas</h2></div><div class="location-grid"><AppCard v-for="location in gym.sedes" :key="location.id"><div class="location-title"><IconMapPin :size="20" /><div><h3>{{ location.nombre }}</h3><AppBadge v-if="location.es_principal" tone="neutral">Principal</AppBadge></div></div><p>{{ location.direccion }}<br />{{ location.ciudad }}, {{ location.departamento }}</p><a :href="mapHref(location)" target="_blank" rel="noopener noreferrer">Abrir en OpenStreetMap</a></AppCard></div></section>

      <section class="plans" aria-labelledby="plans-title"><div class="section-heading"><h2 id="plans-title">Planes disponibles</h2><p>Precios y condiciones registrados por el gimnasio.</p></div><div v-if="plansStatus === 'loading'" class="plans-loading" role="status" aria-label="Cargando planes"><AppSkeleton v-for="index in 3" :key="index" height="12rem" /></div><AppErrorState v-else-if="plansStatus === 'error'" title="No pudimos cargar los planes" description="La información del gimnasio está disponible, pero sus planes no respondieron." @retry="loadPlans" /><AppEmptyState v-else-if="!plans.length" title="Este gimnasio aún no publica planes" description="No mostramos precios estimados ni acciones de compra que todavía no existen." /><div v-else class="plan-grid"><AppCard v-for="plan in plans" :key="plan.id"><div class="plan-title"><div><h3>{{ plan.nombre }}</h3><p>{{ plan.descripcion }}</p></div><strong>{{ money(plan.precio, plan.moneda) }}</strong></div><p class="duration">{{ plan.duracion_dias }} días · versión {{ plan.version }}</p><ul><li v-for="benefit in plan.beneficios" :key="benefit"><IconRosetteDiscountCheck :size="17" />{{ benefit }}</li></ul></AppCard></div></section>
    </template>
  </main><PublicFooter /></div>
</template>

<style scoped>
.gym-detail { padding-block: var(--space-8) var(--space-20); }.back-link { display: inline-flex; align-items: center; gap: var(--space-2); border: 0; padding: var(--space-2) 0; background: transparent; color: var(--text-secondary); cursor: pointer; font: inherit; font-size: .82rem; font-weight: 700; }.back-link:hover { color: var(--text-primary); }.detail-loading { display: grid; gap: var(--space-6); margin-top: var(--space-6); }.detail-hero { display: grid; grid-template-columns: minmax(0, 1.15fr) minmax(22rem, .85fr); min-height: 31rem; overflow: hidden; margin-top: var(--space-6); border: 1px solid var(--border-subtle); border-radius: var(--radius-dialog); background: var(--surface-1); box-shadow: var(--shadow-md); }.detail-hero > img { width: 100%; height: 100%; min-height: 31rem; object-fit: cover; }.detail-hero__placeholder { display: grid; min-height: 31rem; place-content: center; justify-items: center; gap: var(--space-3); background: var(--surface-2); color: var(--text-tertiary); font-size: .82rem; }.detail-hero__placeholder svg { color: var(--info); }.detail-hero__content { display: flex; flex-direction: column; justify-content: center; padding: clamp(var(--space-7), 5vw, var(--space-12)); }.detail-badges { display: flex; flex-wrap: wrap; gap: var(--space-2); margin-bottom: var(--space-6); }.detail-location { margin: 0; color: var(--text-secondary); font-size: .8rem; font-weight: 700; }.detail-hero h1 { max-width: 12ch; margin: var(--space-2) 0 var(--space-4); font-size: clamp(2.3rem, 5vw, 4rem); }.detail-hero__content > p:not(.detail-location) { margin: 0; color: var(--text-secondary); line-height: 1.7; }.hero-actions { display: flex; flex-wrap: wrap; align-items: center; gap: var(--space-4); margin-top: var(--space-8); }.hero-actions > a:not(.link-button) { color: var(--status-info-strong); font-size: .82rem; font-weight: 700; text-underline-offset: .2em; }.detail-grid { display: grid; grid-template-columns: 1fr 1fr; gap: var(--space-5); margin-top: var(--space-8); }.detail-grid h2,.locations h2,.plans h2 { margin: 0 0 var(--space-5); }.detail-grid h3 { margin: var(--space-7) 0 var(--space-3); font-size: .85rem; }.facts { display: grid; gap: var(--space-4); margin: 0; padding: 0; list-style: none; }.facts li { display: grid; grid-template-columns: 1.4rem 1fr; gap: var(--space-3); color: var(--text-secondary); }.facts svg { color: var(--info); }.facts span { display: grid; }.facts strong { color: var(--text-primary); }.facts small { color: var(--text-tertiary); }.facts a,.location-grid a { color: var(--status-info-strong); text-underline-offset: .2em; }.tag-list { display: flex; flex-wrap: wrap; gap: var(--space-2); }.tag-list span { border: 1px solid var(--border-strong); border-radius: var(--radius-pill); padding: var(--space-2) var(--space-3); font-size: .75rem; font-weight: 700; }.tag-list--soft span { border-color: var(--border-subtle); background: var(--surface-2); color: var(--text-secondary); }.locations,.plans { padding-top: var(--space-16); }.section-heading { margin-bottom: var(--space-6); }.section-heading h2 { margin-bottom: var(--space-2); }.section-heading > p:last-child { margin: 0; color: var(--text-secondary); }.location-grid,.plan-grid,.plans-loading { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: var(--space-4); }.location-title { display: flex; align-items: flex-start; gap: var(--space-3); }.location-title svg { color: var(--info); }.location-title > div { display: flex; flex-wrap: wrap; align-items: center; gap: var(--space-2); }.location-title h3,.plan-title h3 { margin: 0; font-size: 1rem; }.location-grid p { min-height: 3rem; color: var(--text-secondary); font-size: .82rem; }.plan-title { display: flex; justify-content: space-between; gap: var(--space-5); }.plan-title p { margin: var(--space-2) 0 0; color: var(--text-secondary); font-size: .8rem; }.plan-title > strong { white-space: nowrap; font-size: 1.2rem; }.duration { color: var(--text-tertiary); font-size: .75rem; }.plan-grid ul { display: grid; gap: var(--space-2); margin: var(--space-5) 0 0; padding: var(--space-5) 0 0; border-top: 1px solid var(--border-subtle); list-style: none; }.plan-grid li { display: flex; gap: var(--space-2); color: var(--text-secondary); font-size: .78rem; }.plan-grid li svg { flex: 0 0 auto; color: var(--success); }
@media (max-width: 63.99rem) { .detail-hero { grid-template-columns: 1fr; }.detail-hero > img,.detail-hero__placeholder { min-height: 22rem; max-height: 28rem; }.location-grid,.plan-grid,.plans-loading { grid-template-columns: repeat(2, minmax(0, 1fr)); } }
@media (max-width: 47.99rem) { .gym-detail { padding-top: var(--space-5); }.detail-hero { min-height: 0; }.detail-hero > img,.detail-hero__placeholder { min-height: 15rem; }.detail-grid,.location-grid,.plan-grid,.plans-loading { grid-template-columns: 1fr; }.detail-hero__content { padding: var(--space-6); }.detail-hero h1 { font-size: 2.25rem; }.locations,.plans { padding-top: var(--space-12); } }
</style>
