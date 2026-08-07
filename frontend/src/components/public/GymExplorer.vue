<script setup>
import { computed, nextTick, onBeforeUnmount, onMounted, ref, watch } from 'vue'
import { IconChevronDown, IconChevronLeft, IconChevronRight, IconMapPin, IconSearch } from '@tabler/icons-vue'
import 'leaflet/dist/leaflet.css'
import { api } from '../../services/api'
import { useSystemStore } from '../../stores/system'
import AppBadge from '../ui/AppBadge.vue'
import AppEmptyState from '../ui/AppEmptyState.vue'
import AppErrorState from '../ui/AppErrorState.vue'
import AppIconButton from '../ui/AppIconButton.vue'
import AppInput from '../ui/AppInput.vue'
import AppSelect from '../ui/AppSelect.vue'
import AppSkeleton from '../ui/AppSkeleton.vue'
import AppSpinner from '../ui/AppSpinner.vue'

defineProps({ compactHeading: Boolean })

const system = useSystemStore()
const mapElement = ref(null)
const mapStatus = ref('loading')
const catalogStatus = ref('loading')
const gyms = ref([])
const query = ref('')
const city = ref('')
const category = ref('')
const selectedGymId = ref(null)
const panelCollapsed = ref(false)
const sheetPosition = ref('medium')
let leaflet = null
let map = null
let markersLayer = null
let resizeObserver = null
let tileTimeout = null

const sheetLabel = computed(() => ({ closed: 'Abrir lista', medium: 'Expandir lista', full: 'Reducir lista' })[sheetPosition.value])
const cityOptions = computed(() => [
  { value: '', label: 'Todas las ciudades' },
  ...[...new Set(gyms.value.map((gym) => gym.ciudad))].sort().map((value) => ({ value, label: value })),
])
const categoryOptions = computed(() => [
  { value: '', label: 'Todas las categorías' },
  ...[...new Set(gyms.value.flatMap((gym) => gym.categorias))].sort().map((value) => ({ value, label: value })),
])
const filteredGyms = computed(() => {
  const term = query.value.trim().toLocaleLowerCase('es')
  return gyms.value.filter((gym) => {
    const matchesQuery = term === '' || [gym.nombre, gym.ciudad, gym.departamento, ...gym.categorias]
      .some((value) => value.toLocaleLowerCase('es').includes(term))
    return matchesQuery
      && (city.value === '' || gym.ciudad === city.value)
      && (category.value === '' || gym.categorias.includes(category.value))
  })
})

async function loadGyms() {
  catalogStatus.value = 'loading'
  const { ok, data } = await api.get('/public/gimnasios')
  if (!ok || data.error) {
    catalogStatus.value = 'error'
    return
  }
  gyms.value = data.gimnasios || []
  catalogStatus.value = 'ready'
  await nextTick()
  syncMarkers(true)
}

async function initialiseMap() {
  mapStatus.value = 'loading'
  try {
    leaflet = await import('leaflet')
    await nextTick()
    if (!mapElement.value) return

    map?.remove()
    window.clearTimeout(tileTimeout)
    map = leaflet.map(mapElement.value, { center: [-32.8, -56.0], zoom: 7, zoomControl: true, attributionControl: true })
    markersLayer = leaflet.layerGroup().addTo(map)
    const tiles = leaflet.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
      maxZoom: 19,
      attribution: '© OpenStreetMap contributors',
    })
    let tileErrors = 0
    tiles.once('load', () => {
      if (mapStatus.value !== 'loading') return
      window.clearTimeout(tileTimeout)
      mapStatus.value = 'ready'
    })
    tiles.on('tileerror', () => {
      tileErrors += 1
      if (tileErrors >= 3) {
        window.clearTimeout(tileTimeout)
        mapStatus.value = 'error'
      }
    })
    tiles.addTo(map)
    tileTimeout = window.setTimeout(() => {
      if (mapStatus.value === 'loading') mapStatus.value = 'error'
    }, 8000)

    resizeObserver = new ResizeObserver(() => map?.invalidateSize({ pan: false }))
    resizeObserver.observe(mapElement.value)
    syncMarkers(true)
  } catch (error) {
    console.error('No se pudo iniciar el mapa público.', error)
    mapStatus.value = 'error'
  }
}

function syncMarkers(fitBounds = false) {
  if (!map || !leaflet || !markersLayer) return
  markersLayer.clearLayers()
  const points = []
  filteredGyms.value.forEach((gym) => {
    const selected = gym.id === selectedGymId.value
    const marker = leaflet.marker([gym.latitud, gym.longitud], {
      title: `Ver ${gym.nombre}`,
      alt: `Ubicación de ${gym.nombre}`,
      icon: leaflet.divIcon({
        className: `gym-marker${selected ? ' gym-marker--selected' : ''}`,
        html: '<span></span>',
        iconSize: [28, 28],
        iconAnchor: [14, 14],
      }),
    })
    const tooltip = document.createElement('span')
    tooltip.textContent = gym.nombre
    marker.bindTooltip(tooltip)
    marker.on('click', () => selectGym(gym, false))
    marker.addTo(markersLayer)
    points.push([gym.latitud, gym.longitud])
  })

  if (fitBounds && points.length > 0) {
    map.fitBounds(points, { padding: [34, 34], maxZoom: 12 })
  }
}

function selectGym(gym, moveMap = true) {
  selectedGymId.value = gym.id
  if (moveMap) map?.flyTo([gym.latitud, gym.longitud], Math.max(map.getZoom(), 11), { duration: 0.45 })
  syncMarkers(false)
}

function togglePanel() {
  panelCollapsed.value = !panelCollapsed.value
  window.setTimeout(() => map?.invalidateSize({ pan: false }), 240)
}

function cycleSheet() {
  sheetPosition.value = sheetPosition.value === 'closed' ? 'medium' : sheetPosition.value === 'medium' ? 'full' : 'closed'
  window.setTimeout(() => map?.invalidateSize({ pan: false }), 240)
}

watch(filteredGyms, () => syncMarkers(true))
onMounted(() => Promise.all([initialiseMap(), loadGyms(), system.loaded ? Promise.resolve() : system.load()]))
onBeforeUnmount(() => {
  window.clearTimeout(tileTimeout)
  resizeObserver?.disconnect()
  map?.remove()
})
</script>

<template>
  <section class="explorer" aria-labelledby="explorer-title">
    <div v-if="!compactHeading" class="explorer__intro">
      <div><span class="eyebrow">Explorar</span><h2 id="explorer-title">Tu próximo gimnasio, en contexto</h2></div>
      <p>Compará sedes publicadas, filtrá el catálogo y elegí una tarjeta para ubicarla en el mapa.</p>
    </div>
    <h2 v-else id="explorer-title" class="visually-hidden">Explorador de gimnasios</h2>

    <div :class="['explorer__workspace', { 'explorer__workspace--collapsed': panelCollapsed }]">
      <div class="explorer__map-wrap">
        <div ref="mapElement" class="explorer__map" aria-label="Mapa de gimnasios de GymTrack" />
        <div v-if="mapStatus === 'loading'" class="explorer__map-state" role="status" aria-label="Cargando mapa"><AppSkeleton width="100%" height="100%" /></div>
        <div v-else-if="mapStatus === 'error'" class="explorer__map-state"><AppErrorState title="El mapa no está disponible" description="No pudimos cargar OpenStreetMap en este momento." @retry="initialiseMap" /></div>
        <div class="map-context"><IconMapPin :size="17" aria-hidden="true" /><span>{{ filteredGyms.length }} ubicaciones visibles</span></div>
      </div>

      <aside :class="['explorer__panel', `explorer__panel--${sheetPosition}`, { 'explorer__panel--hidden': panelCollapsed }]" aria-label="Lista de gimnasios">
        <button class="sheet-handle" type="button" :aria-label="sheetLabel" @click="cycleSheet"><span /><IconChevronDown :size="18" aria-hidden="true" /></button>
        <header class="explorer__panel-header">
          <div><strong>Gimnasios</strong><span>{{ filteredGyms.length }} de {{ gyms.length }} sedes</span></div>
          <AppBadge v-if="system.demoDataActive" tone="info">Datos demo</AppBadge>
          <AppIconButton class="collapse-control" :label="panelCollapsed ? 'Mostrar lista' : 'Ocultar lista'" :pressed="!panelCollapsed" @click="togglePanel">
            <IconChevronRight v-if="!panelCollapsed" :size="20" /><IconChevronLeft v-else :size="20" />
          </AppIconButton>
        </header>

        <div class="explorer__filters">
          <AppInput v-model="query" label="Buscar" name="gym-search" placeholder="Nombre, ciudad o categoría" />
          <div class="explorer__filter-row">
            <AppSelect v-model="city" label="Ciudad" name="gym-city" :options="cityOptions" />
            <AppSelect v-model="category" label="Categoría" name="gym-category" :options="categoryOptions" />
          </div>
        </div>

        <div class="explorer__panel-body">
          <div v-if="catalogStatus === 'loading'" class="catalog-loading" role="status"><AppSpinner /><span>Cargando gimnasios…</span></div>
          <AppErrorState v-else-if="catalogStatus === 'error'" title="No pudimos cargar los gimnasios" description="La lista se obtiene desde MySQL. Intentá nuevamente." @retry="loadGyms" />
          <AppEmptyState v-else-if="gyms.length === 0" title="Todavía no hay gimnasios publicados" description="No mostramos ubicaciones ni precios inventados. Activá el dataset demo o publicá gimnasios reales para completar esta lista.">
            <template #icon><IconSearch :size="24" /></template>
          </AppEmptyState>
          <AppEmptyState v-else-if="filteredGyms.length === 0" title="No encontramos coincidencias" description="Probá con otra ciudad, categoría o término de búsqueda.">
            <template #icon><IconSearch :size="24" /></template>
          </AppEmptyState>
          <div v-else class="gym-list">
            <article v-for="gym in filteredGyms" :key="gym.id" :class="['gym-card', { 'gym-card--selected': selectedGymId === gym.id }]">
              <button class="gym-card__select" type="button" :aria-pressed="selectedGymId === gym.id" :aria-label="`Ubicar ${gym.nombre} en el mapa`" @click="selectGym(gym)">
                <img :src="gym.imagen_path" alt="" width="800" height="500" loading="lazy" />
                <span class="gym-card__content">
                  <span class="gym-card__title"><strong>{{ gym.nombre }}</strong><AppBadge v-if="gym.estado === 'temporalmente_cerrado'" tone="warning">Cerrado temporalmente</AppBadge></span>
                  <span class="gym-card__location">{{ gym.ciudad }}, {{ gym.departamento }}</span>
                  <span class="gym-card__categories">{{ gym.categorias.join(' · ') }}</span>
                  <span class="gym-card__facts"><span>{{ gym.clases_activas }} clases</span><span>{{ gym.membresias_activas }} membresías activas</span></span>
                  <span v-if="gym.demo_scenario && (gym.demo_scenario.key !== 'promotions_beta' || system.features.promotions_beta)" class="gym-card__scenario">{{ gym.demo_scenario.label }}</span>
                </span>
              </button>
              <RouterLink class="gym-card__detail" :to="{ name: 'gym-detail', params: { slug: gym.slug } }">Ver ficha</RouterLink>
            </article>
          </div>
        </div>
      </aside>

      <AppIconButton v-if="panelCollapsed" class="reopen-control" label="Mostrar lista de gimnasios" :pressed="false" @click="togglePanel"><IconChevronLeft :size="20" /></AppIconButton>
    </div>
  </section>
</template>

<style scoped>
.explorer__intro { display: grid; grid-template-columns: minmax(0, 1.25fr) minmax(18rem, .75fr); align-items: end; gap: var(--space-12); margin-bottom: var(--space-8); }
.explorer__intro h2 { max-width: 14ch; margin: 0; }.explorer__intro p { margin: 0; color: var(--text-secondary); }
.explorer__workspace { position: relative; display: grid; grid-template-columns: minmax(0, 1fr) minmax(22rem, 27rem); min-height: 40rem; overflow: hidden; border: 1px solid var(--border-subtle); border-radius: var(--radius-dialog); background: var(--surface-1); box-shadow: var(--shadow-md); transition: grid-template-columns var(--duration-normal) var(--ease-out); }
.explorer__workspace--collapsed { grid-template-columns: minmax(0, 1fr) 0; }.explorer__map-wrap { position: relative; min-width: 0; min-height: 40rem; background: var(--surface-2); }.explorer__map { position: absolute; inset: 0; z-index: 0; }.explorer__map-state { position: absolute; inset: 0; z-index: 2; background: var(--surface-1); }.explorer__map-state :deep(.skeleton) { border-radius: 0; }
.map-context { position: absolute; left: var(--space-4); bottom: var(--space-4); z-index: 3; display: flex; align-items: center; gap: var(--space-2); border: 1px solid var(--border-subtle); border-radius: var(--radius-pill); padding: var(--space-2) var(--space-3); background: var(--surface-glass); color: var(--text-secondary); font-size: .72rem; backdrop-filter: blur(10px); }
.explorer__panel { position: relative; z-index: 4; display: flex; min-width: 0; flex-direction: column; border-left: 1px solid var(--border-subtle); background: var(--bg-subtle); transition: opacity var(--duration-fast), transform var(--duration-normal) var(--ease-out); }.explorer__panel--hidden { opacity: 0; pointer-events: none; transform: translateX(100%); }
.explorer__panel-header { display: flex; min-height: 4.75rem; align-items: center; gap: var(--space-3); padding: var(--space-4); border-bottom: 1px solid var(--border-subtle); }.explorer__panel-header > div:first-child { display: grid; margin-right: auto; }.explorer__panel-header strong { font-size: .95rem; }.explorer__panel-header > div:first-child > span { color: var(--text-tertiary); font-size: .72rem; }
.explorer__filters { display: grid; gap: var(--space-3); padding: var(--space-4); border-bottom: 1px solid var(--border-subtle); }.explorer__filter-row { display: grid; grid-template-columns: 1fr 1fr; gap: var(--space-2); }.explorer__filters :deep(.field__label), .explorer__filters :deep(.field > span:first-child) { font-size: .72rem; }.explorer__panel-body { flex: 1; min-height: 0; overflow: auto; }.catalog-loading { display: flex; min-height: 12rem; align-items: center; justify-content: center; gap: var(--space-3); color: var(--text-secondary); font-size: .85rem; }
.gym-list { display: grid; gap: var(--space-2); padding: var(--space-3); }.gym-card { position: relative; width: 100%; overflow: hidden; border: 1px solid var(--border-subtle); border-radius: var(--radius-card); background: var(--surface-1); color: var(--text-primary); transition: border-color var(--duration-fast), background-color var(--duration-fast), transform var(--duration-fast) var(--ease-out); }.gym-card:hover { border-color: var(--border-strong); background: var(--surface-2); }.gym-card--selected { border-color: var(--focus); background: var(--accent-soft); }.gym-card__select { display: grid; grid-template-columns: 6.5rem minmax(0, 1fr); gap: var(--space-3); width: 100%; border: 0; padding: 0; background: transparent; color: inherit; cursor: pointer; text-align: left; }.gym-card img { width: 100%; height: 100%; min-height: 9.5rem; object-fit: cover; }.gym-card__content { display: grid; align-content: center; gap: var(--space-1); min-width: 0; padding: var(--space-3) var(--space-3) 2.6rem 0; }.gym-card__title { display: flex; align-items: flex-start; justify-content: space-between; gap: var(--space-2); }.gym-card__title strong { overflow: hidden; font-size: .86rem; text-overflow: ellipsis; white-space: nowrap; }.gym-card__location, .gym-card__categories { color: var(--text-secondary); font-size: .7rem; }.gym-card__facts { display: flex; flex-wrap: wrap; gap: var(--space-2); margin-top: var(--space-1); color: var(--text-tertiary); font-size: .65rem; }.gym-card__scenario { color: var(--info); font-size: .68rem; font-weight: 700; }.gym-card__detail { position: absolute; right: var(--space-3); bottom: var(--space-3); z-index: 1; color: var(--status-info-strong); font-size: .72rem; font-weight: 750; text-underline-offset: .2em; }
.sheet-handle { display: none; }.reopen-control { position: absolute; right: var(--space-4); top: var(--space-4); z-index: 5; }
:deep(.leaflet-control-zoom a) { background: var(--surface-1); color: var(--text-primary); border-color: var(--border-subtle); }:deep(.leaflet-control-zoom a:hover) { background: var(--surface-raised); }:deep(.leaflet-control-attribution) { background: var(--surface-glass-soft); color: var(--text-secondary); }:deep(.leaflet-control-attribution a) { color: var(--info); }:deep(.gym-marker) { display: grid; place-items: center; border: 0; background: transparent; }:deep(.gym-marker span) { display: block; width: 1.25rem; height: 1.25rem; border: 3px solid var(--text-on-accent); border-radius: 50% 50% 50% 0; background: var(--accent); box-shadow: var(--shadow-md); transform: rotate(-45deg); }:deep(.gym-marker--selected span) { width: 1.65rem; height: 1.65rem; background: var(--warning); }

@media (max-width: 47.99rem) {
  .explorer__intro { grid-template-columns: 1fr; gap: var(--space-4); }.explorer__intro h2 { max-width: 17ch; }.explorer__workspace, .explorer__workspace--collapsed { display: block; min-height: 38rem; }.explorer__map-wrap { min-height: 38rem; }
  .explorer__panel { position: absolute; inset-inline: 0; bottom: 0; height: calc(100% - 1.5rem); border: 1px solid var(--border-strong); border-bottom: 0; border-radius: var(--radius-dialog) var(--radius-dialog) 0 0; box-shadow: var(--shadow-lg); transform: translateY(calc(100% - 17rem)); }.explorer__panel--hidden { opacity: 1; pointer-events: auto; transform: translateY(calc(100% - 4.5rem)); }.explorer__panel--closed { transform: translateY(calc(100% - 4.5rem)); }.explorer__panel--medium { transform: translateY(calc(100% - 17rem)); }.explorer__panel--full { transform: translateY(0); }
  .sheet-handle { display: flex; width: 100%; min-height: 2.25rem; align-items: center; justify-content: center; gap: var(--space-2); border: 0; border-bottom: 1px solid var(--border-subtle); background: transparent; color: var(--text-tertiary); cursor: pointer; }.sheet-handle span { width: 2.5rem; height: 3px; border-radius: var(--radius-pill); background: var(--border-strong); }.collapse-control, .reopen-control { display: none; }.explorer__panel-header { min-height: 3.75rem; padding-block: var(--space-2); }.explorer__filters { padding-block: var(--space-3); }.explorer__filter-row { grid-template-columns: 1fr; }.map-context { bottom: 18rem; }.gym-card__select { grid-template-columns: 5.5rem minmax(0, 1fr); }.gym-card img { min-height: 8.5rem; }
}
</style>
