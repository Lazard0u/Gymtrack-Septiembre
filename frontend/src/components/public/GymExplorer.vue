<script setup>
import { computed, nextTick, onBeforeUnmount, onMounted, ref, watch } from 'vue'
import {
  IconAdjustmentsHorizontal,
  IconChevronDown,
  IconChevronLeft,
  IconChevronRight,
  IconCurrentLocation,
  IconLocationOff,
  IconMapPin,
  IconRefresh,
  IconRoute,
  IconSearch,
} from '@tabler/icons-vue'
import 'leaflet/dist/leaflet.css'
import { api } from '../../services/api'
import { useSystemStore } from '../../stores/system'
import { distanceInKm, formatDistance, groupByProjectedCell } from '../../utils/geo'
import AppBadge from '../ui/AppBadge.vue'
import AppButton from '../ui/AppButton.vue'
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
const service = ref('')
const availability = ref('')
const order = ref('name')
const selectedGymId = ref(null)
const panelCollapsed = ref(false)
const sheetPosition = ref('medium')
const filtersOpen = ref(false)
const locationStatus = ref('idle')
const userLocation = ref(null)
const failedImages = ref(new Set())
const cardElements = new Map()
let leaflet = null
let map = null
let markersLayer = null
let userLayer = null
let resizeObserver = null
let tileTimeout = null
let locationPermission = null
let locationPermissionHandler = null

const sheetLabel = computed(() => ({ closed: 'Abrir lista', medium: 'Expandir lista', full: 'Reducir lista' })[sheetPosition.value])
const locationCopy = computed(() => ({
  idle: { title: 'Ordená por distancia', text: 'GymTrack la procesa sólo en este navegador: no la guarda ni la envía a su API.' },
  requesting: { title: 'Buscando tu ubicación…', text: 'Esperá mientras el navegador obtiene una posición aproximada.' },
  ready: { title: 'Ubicación disponible', text: `Precisión aproximada: ${Math.round(userLocation.value?.accuracy || 0)} m.` },
  denied: { title: 'Permiso de ubicación rechazado', text: 'Podés habilitarlo desde la configuración del navegador o continuar con el mapa general.' },
  unavailable: { title: 'No pudimos obtener tu ubicación', text: 'Revisá la conexión o continuá con la ubicación predeterminada.' },
  unsupported: { title: 'Ubicación no compatible', text: 'Este navegador no ofrece geolocalización. El catálogo sigue disponible.' },
  fallback: { title: 'Vista general activa', text: 'El mapa está centrado en Uruguay y no utiliza tu ubicación.' },
})[locationStatus.value])
const recenterLabel = computed(() => locationStatus.value === 'ready' ? 'Volver a mi ubicación' : 'Volver a la vista general')
const cityOptions = computed(() => [
  { value: '', label: 'Todas las ciudades' },
  ...[...new Set(gyms.value.map((gym) => gym.ciudad))].sort().map((value) => ({ value, label: value })),
])
const categoryOptions = computed(() => [
  { value: '', label: 'Todas las categorías' },
  ...[...new Set(gyms.value.flatMap((gym) => gym.categorias))].sort().map((value) => ({ value, label: value })),
])
const serviceOptions = computed(() => [
  { value: '', label: 'Todos los servicios' },
  ...[...new Set(gyms.value.flatMap((gym) => gym.servicios))].sort().map((value) => ({ value, label: value })),
])
const availabilityOptions = [
  { value: '', label: 'Cualquier estado' },
  { value: 'open', label: 'Abiertos' },
  { value: 'closed', label: 'Cerrados temporalmente' },
]
const orderOptions = computed(() => [
  { value: 'name', label: 'Nombre' },
  { value: 'distance', label: 'Más cercanos', disabled: locationStatus.value !== 'ready' },
])
const filteredGyms = computed(() => {
  const term = query.value.trim().toLocaleLowerCase('es')
  return gyms.value.filter((gym) => {
    const matchesQuery = term === '' || [gym.nombre, gym.ciudad, gym.departamento, ...gym.categorias, ...gym.servicios]
      .some((value) => value.toLocaleLowerCase('es').includes(term))
    return matchesQuery
      && (city.value === '' || gym.ciudad === city.value)
      && (category.value === '' || gym.categorias.includes(category.value))
      && (service.value === '' || gym.servicios.includes(service.value))
      && (availability.value === '' || (availability.value === 'open' ? gym.estado === 'publicado' : gym.estado === 'temporalmente_cerrado'))
  })
})
const visibleGyms = computed(() => filteredGyms.value
  .map((gym) => ({
    ...gym,
    distance: userLocation.value ? distanceInKm(userLocation.value, { latitude: gym.latitud, longitude: gym.longitud }) : null,
  }))
  .sort((first, second) => order.value === 'distance' && userLocation.value
    ? first.distance - second.distance
    : first.nombre.localeCompare(second.nombre, 'es')))
const activeFilterCount = computed(() => [query.value, city.value, category.value, service.value, availability.value].filter(Boolean).length)

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
    userLayer = leaflet.layerGroup().addTo(map)
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
    map.on('zoomend', () => syncMarkers(false))
    syncMarkers(true)
    syncUserMarker()
  } catch (error) {
    console.error('No se pudo iniciar el mapa público.', error)
    mapStatus.value = 'error'
  }
}

function syncMarkers(fitBounds = false) {
  if (!map || !leaflet || !markersLayer) return
  markersLayer.clearLayers()
  const points = visibleGyms.value.map((gym) => [gym.latitud, gym.longitud])
  const groups = groupByProjectedCell(visibleGyms.value, (gym) => map.project([gym.latitud, gym.longitud], map.getZoom()), 72)

  groups.forEach((group) => {
    if (group.length === 1) {
      const gym = group[0]
      const selected = gym.id === selectedGymId.value
      const marker = leaflet.marker([gym.latitud, gym.longitud], {
        title: `Ver ${gym.nombre}`,
        alt: `Ubicación de ${gym.nombre}`,
        keyboard: true,
        icon: leaflet.divIcon({
          className: `gym-marker${selected ? ' gym-marker--selected' : ''}`,
          html: '<span></span>',
          iconSize: [44, 44],
          iconAnchor: [22, 22],
        }),
      })
      const tooltip = document.createElement('span')
      tooltip.textContent = gym.nombre
      marker.bindTooltip(tooltip)
      marker.on('click', () => selectGym(gym, false, true))
      marker.addTo(markersLayer)
      return
    }

    const latitude = group.reduce((sum, gym) => sum + gym.latitud, 0) / group.length
    const longitude = group.reduce((sum, gym) => sum + gym.longitud, 0) / group.length
    const cluster = leaflet.marker([latitude, longitude], {
      title: `Ver ${group.length} gimnasios agrupados`,
      alt: `${group.length} gimnasios agrupados`,
      keyboard: true,
      icon: leaflet.divIcon({ className: 'gym-cluster', html: `<span>${group.length}</span>`, iconSize: [44, 44], iconAnchor: [22, 22] }),
    })
    cluster.on('click', () => map.fitBounds(group.map((gym) => [gym.latitud, gym.longitud]), { padding: [48, 48], maxZoom: 14 }))
    cluster.addTo(markersLayer)
  })

  if (fitBounds && points.length > 0) {
    map.fitBounds(points, { padding: [34, 34], maxZoom: 12 })
  } else if (fitBounds) {
    map.setView([-32.8, -56.0], 7)
  }
}

function syncUserMarker() {
  if (!map || !leaflet || !userLayer) return
  userLayer.clearLayers()
  if (!userLocation.value) return
  const point = [userLocation.value.latitude, userLocation.value.longitude]
  leaflet.circle(point, { radius: Math.max(40, userLocation.value.accuracy || 40), className: 'user-accuracy', interactive: false }).addTo(userLayer)
  leaflet.circleMarker(point, { radius: 7, className: 'user-location', title: 'Tu ubicación aproximada' }).bindTooltip('Tu ubicación aproximada').addTo(userLayer)
}

function selectGym(gym, moveMap = true, revealCard = false) {
  selectedGymId.value = gym.id
  if (moveMap) map?.flyTo([gym.latitud, gym.longitud], Math.max(map.getZoom(), 11), { duration: 0.45 })
  syncMarkers(false)
  if (revealCard) {
    sheetPosition.value = 'medium'
    nextTick(() => cardElements.get(gym.id)?.scrollIntoView({ behavior: 'smooth', block: 'nearest' }))
  }
}

function setCardElement(element, id) {
  if (element) cardElements.set(id, element)
  else cardElements.delete(id)
}

function requestLocation() {
  if (!navigator.geolocation) {
    locationStatus.value = 'unsupported'
    return
  }
  locationStatus.value = 'requesting'
  navigator.geolocation.getCurrentPosition((position) => {
    userLocation.value = {
      latitude: position.coords.latitude,
      longitude: position.coords.longitude,
      accuracy: position.coords.accuracy,
    }
    locationStatus.value = 'ready'
    order.value = 'distance'
    syncUserMarker()
    map?.flyTo([userLocation.value.latitude, userLocation.value.longitude], 12, { duration: 0.55 })
  }, (error) => {
    userLocation.value = null
    locationStatus.value = error.code === error.PERMISSION_DENIED ? 'denied' : 'unavailable'
    if (order.value === 'distance') order.value = 'name'
    syncUserMarker()
  }, { enableHighAccuracy: false, timeout: 10000, maximumAge: 300000 })
}

function useFallbackLocation() {
  userLocation.value = null
  locationStatus.value = 'fallback'
  order.value = 'name'
  syncUserMarker()
  syncMarkers(true)
}

function recenterMap() {
  if (userLocation.value) map?.flyTo([userLocation.value.latitude, userLocation.value.longitude], Math.max(map.getZoom(), 12), { duration: 0.45 })
  else syncMarkers(true)
}

function resetFilters() {
  query.value = ''
  city.value = ''
  category.value = ''
  service.value = ''
  availability.value = ''
}

function imageFailed(path) {
  failedImages.value = new Set([...failedImages.value, path])
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
watch(selectedGymId, () => syncMarkers(false))
onMounted(async () => {
  if (navigator.permissions?.query) {
    try {
      locationPermission = await navigator.permissions.query({ name: 'geolocation' })
      if (locationPermission.state === 'denied') locationStatus.value = 'denied'
      locationPermissionHandler = () => {
        if (locationPermission.state === 'denied') {
          locationStatus.value = 'denied'
          userLocation.value = null
          if (order.value === 'distance') order.value = 'name'
          syncUserMarker()
        }
      }
      locationPermission.addEventListener?.('change', locationPermissionHandler)
    } catch { /* El botón sigue siendo la fuente de verdad si Permissions API no está disponible. */ }
  }
  await Promise.all([initialiseMap(), loadGyms(), system.loaded ? Promise.resolve() : system.load()])
})
onBeforeUnmount(() => {
  window.clearTimeout(tileTimeout)
  locationPermission?.removeEventListener?.('change', locationPermissionHandler)
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
        <div class="map-tools" aria-label="Controles de ubicación">
          <AppButton v-if="locationStatus !== 'ready'" variant="secondary" size="sm" :loading="locationStatus === 'requesting'" @click="requestLocation"><template #icon><IconCurrentLocation :size="18" /></template>Usar mi ubicación</AppButton>
          <AppIconButton v-else label="Actualizar mi ubicación" @click="requestLocation"><IconRefresh :size="19" /></AppIconButton>
          <AppIconButton :label="recenterLabel" @click="recenterMap"><IconCurrentLocation :size="19" /></AppIconButton>
        </div>
        <div class="map-context"><IconMapPin :size="17" aria-hidden="true" /><span>{{ visibleGyms.length }} ubicaciones visibles</span><span v-if="userLocation && order === 'distance'">· ordenadas por cercanía</span></div>
      </div>

      <aside :class="['explorer__panel', `explorer__panel--${sheetPosition}`, { 'explorer__panel--hidden': panelCollapsed }]" aria-label="Lista de gimnasios" :aria-hidden="panelCollapsed || undefined" :inert="panelCollapsed">
        <a class="mobile-map-attribution" href="https://www.openstreetmap.org/copyright" target="_blank" rel="noopener noreferrer">© OpenStreetMap</a>
        <button class="sheet-handle" type="button" :aria-label="sheetLabel" @click="cycleSheet"><span /><IconChevronDown :size="18" aria-hidden="true" /></button>
        <header class="explorer__panel-header">
          <div><strong>Gimnasios</strong><span>{{ visibleGyms.length }} de {{ gyms.length }} sedes</span></div>
          <AppBadge v-if="system.demoDataActive" tone="info">Datos demo</AppBadge>
          <AppIconButton class="collapse-control" :label="panelCollapsed ? 'Mostrar lista' : 'Ocultar lista'" :pressed="!panelCollapsed" @click="togglePanel">
            <IconChevronRight v-if="!panelCollapsed" :size="20" /><IconChevronLeft v-else :size="20" />
          </AppIconButton>
        </header>

        <div class="location-status" role="status" aria-live="polite">
          <component :is="locationStatus === 'denied' || locationStatus === 'unsupported' ? IconLocationOff : IconRoute" :size="19" aria-hidden="true" />
          <div><strong>{{ locationCopy.title }}</strong><span>{{ locationCopy.text }}</span></div>
          <button v-if="['denied', 'unavailable'].includes(locationStatus)" type="button" @click="useFallbackLocation">Usar vista general</button>
        </div>

        <button class="filters-toggle" type="button" :aria-expanded="filtersOpen" aria-controls="gym-filters" @click="filtersOpen = !filtersOpen">
          <IconAdjustmentsHorizontal :size="19" aria-hidden="true" /><span>Filtros</span><AppBadge v-if="activeFilterCount" tone="info">{{ activeFilterCount }}</AppBadge><IconChevronDown :class="{ 'filters-toggle__chevron--open': filtersOpen }" :size="18" aria-hidden="true" />
        </button>
        <div id="gym-filters" :class="['explorer__filters', { 'explorer__filters--open': filtersOpen }]">
          <AppInput v-model="query" label="Buscar" name="gym-search" placeholder="Nombre, ciudad o categoría" />
          <div class="explorer__filter-row">
            <AppSelect v-model="city" label="Ciudad" name="gym-city" :options="cityOptions" />
            <AppSelect v-model="category" label="Categoría" name="gym-category" :options="categoryOptions" />
            <AppSelect v-model="service" label="Servicio" name="gym-service" :options="serviceOptions" />
            <AppSelect v-model="availability" label="Estado" name="gym-availability" :options="availabilityOptions" />
          </div>
          <div class="explorer__sort-row"><AppSelect v-model="order" label="Ordenar por" name="gym-order" :options="orderOptions" /><button v-if="activeFilterCount" type="button" @click="resetFilters">Limpiar filtros</button></div>
        </div>

        <div class="explorer__panel-body">
          <div v-if="catalogStatus === 'loading'" class="catalog-loading" role="status"><AppSpinner /><span>Cargando gimnasios…</span></div>
          <AppErrorState v-else-if="catalogStatus === 'error'" title="No pudimos cargar los gimnasios" description="La lista se obtiene desde MySQL. Intentá nuevamente." @retry="loadGyms" />
          <AppEmptyState v-else-if="gyms.length === 0" title="Todavía no hay gimnasios publicados" description="No mostramos ubicaciones ni precios inventados. Activá el dataset demo o publicá gimnasios reales para completar esta lista.">
            <template #icon><IconSearch :size="24" /></template>
          </AppEmptyState>
          <AppEmptyState v-else-if="visibleGyms.length === 0" title="No encontramos coincidencias" description="Probá con otra ciudad, categoría, servicio o término de búsqueda.">
            <template #icon><IconSearch :size="24" /></template>
          </AppEmptyState>
          <div v-else class="gym-list">
            <article v-for="gym in visibleGyms" :key="gym.id" :ref="(element) => setCardElement(element, gym.id)" :class="['gym-card', { 'gym-card--selected': selectedGymId === gym.id }]">
              <button class="gym-card__select" type="button" :aria-pressed="selectedGymId === gym.id" :aria-label="`Ubicar ${gym.nombre} en el mapa`" @click="selectGym(gym)">
                <span class="gym-card__visual"><img v-if="gym.imagen_path && !failedImages.has(gym.imagen_path)" :src="gym.imagen_path" alt="" width="800" height="500" loading="lazy" @error="imageFailed(gym.imagen_path)" /><IconMapPin v-else :size="28" aria-hidden="true" /></span>
                <span class="gym-card__content">
                  <span class="gym-card__title"><strong>{{ gym.nombre }}</strong><AppBadge v-if="gym.estado === 'temporalmente_cerrado'" tone="warning">Cerrado temporalmente</AppBadge></span>
                  <span class="gym-card__location">{{ gym.ciudad }}, {{ gym.departamento }}</span>
                  <span v-if="gym.distance !== null" class="gym-card__distance"><IconRoute :size="15" aria-hidden="true" />A {{ formatDistance(gym.distance) }} de tu ubicación</span>
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
.explorer__workspace { position: relative; display: grid; height: clamp(40rem, 70vh, 47rem); grid-template-columns: minmax(0, 1fr) minmax(22rem, 27rem); overflow: hidden; border: 1px solid var(--border-subtle); border-radius: var(--radius-dialog); background: var(--surface-1); box-shadow: var(--shadow-md); transition: grid-template-columns var(--duration-normal) var(--ease-out); }
.explorer__workspace--collapsed { grid-template-columns: minmax(0, 1fr) 0; }.explorer__map-wrap { position: relative; min-width: 0; min-height: 40rem; background: var(--surface-2); }.explorer__map { position: absolute; inset: 0; z-index: 0; }.explorer__map-state { position: absolute; inset: 0; z-index: 2; background: var(--surface-1); }.explorer__map-state :deep(.skeleton) { border-radius: 0; }
.map-tools { position: absolute; top: var(--space-4); right: var(--space-4); z-index: 3; display: flex; align-items: center; gap: var(--space-2); }.map-tools :deep(.app-button), .map-tools :deep(.icon-button) { background: var(--surface-glass); backdrop-filter: blur(10px); }.map-context { position: absolute; left: var(--space-4); bottom: var(--space-4); z-index: 3; display: flex; align-items: center; gap: var(--space-2); border: 1px solid var(--border-subtle); border-radius: var(--radius-pill); padding: var(--space-2) var(--space-3); background: var(--surface-glass); color: var(--text-secondary); font-size: .72rem; backdrop-filter: blur(10px); }
.explorer__panel { position: relative; z-index: 4; display: flex; min-width: 0; flex-direction: column; border-left: 1px solid var(--border-subtle); background: var(--bg-subtle); transition: opacity var(--duration-fast), transform var(--duration-normal) var(--ease-out); }.explorer__panel--hidden { opacity: 0; pointer-events: none; transform: translateX(100%); }
.explorer__panel-header { display: flex; min-height: 4.75rem; align-items: center; gap: var(--space-3); padding: var(--space-4); border-bottom: 1px solid var(--border-subtle); }.explorer__panel-header > div:first-child { display: grid; margin-right: auto; }.explorer__panel-header strong { font-size: .95rem; }.explorer__panel-header > div:first-child > span { color: var(--text-tertiary); font-size: .72rem; }
.location-status { display: grid; grid-template-columns: 1.5rem minmax(0, 1fr) auto; align-items: start; gap: var(--space-3); padding: var(--space-3) var(--space-4); border-bottom: 1px solid var(--border-subtle); background: var(--surface-1); }.location-status > svg { margin-top: .1rem; color: var(--info); }.location-status div { display: grid; gap: .1rem; }.location-status strong { font-size: .76rem; }.location-status span { color: var(--text-tertiary); font-size: .66rem; line-height: 1.4; }.location-status button, .explorer__sort-row button { display: inline-flex; min-height: 2.75rem; align-items: center; align-self: center; border: 0; padding: var(--space-1) 0; background: transparent; color: var(--status-info-strong); cursor: pointer; font: inherit; font-size: .69rem; font-weight: 720; text-decoration: underline; text-underline-offset: .2em; }
.filters-toggle { display: none; }.explorer__filters { display: grid; gap: var(--space-3); padding: var(--space-4); border-bottom: 1px solid var(--border-subtle); }.explorer__filter-row { display: grid; grid-template-columns: 1fr 1fr; gap: var(--space-2); }.explorer__sort-row { display: grid; grid-template-columns: minmax(0, 1fr) auto; align-items: end; gap: var(--space-3); }.explorer__filters :deep(.field__label), .explorer__filters :deep(.field > span:first-child) { font-size: .72rem; }.explorer__panel-body { flex: 1; min-height: 0; overflow: auto; scrollbar-color: var(--border-strong) var(--bg-subtle); }.catalog-loading { display: flex; min-height: 12rem; align-items: center; justify-content: center; gap: var(--space-3); color: var(--text-secondary); font-size: .85rem; }
.gym-list { display: grid; gap: var(--space-2); padding: var(--space-3); }.gym-card { position: relative; width: 100%; overflow: hidden; border: 1px solid var(--border-subtle); border-radius: var(--radius-card); background: var(--surface-1); color: var(--text-primary); transition: border-color var(--duration-fast), background-color var(--duration-fast), transform var(--duration-fast) var(--ease-out); }.gym-card:hover { border-color: var(--border-strong); background: var(--surface-2); }.gym-card--selected { border-color: var(--focus); background: var(--accent-soft); }.gym-card__select { display: grid; grid-template-columns: 6.5rem minmax(0, 1fr); gap: var(--space-3); width: 100%; border: 0; padding: 0; background: transparent; color: inherit; cursor: pointer; text-align: left; }.gym-card img { width: 100%; height: 100%; min-height: 9.5rem; object-fit: cover; }.gym-card__content { display: grid; align-content: center; gap: var(--space-1); min-width: 0; padding: var(--space-3) var(--space-3) 2.75rem 0; }.gym-card__title { display: flex; align-items: flex-start; justify-content: space-between; gap: var(--space-2); }.gym-card__title strong { overflow: hidden; font-size: .86rem; text-overflow: ellipsis; white-space: nowrap; }.gym-card__location, .gym-card__categories { color: var(--text-secondary); font-size: .7rem; }.gym-card__facts { display: flex; flex-wrap: wrap; gap: var(--space-2); margin-top: var(--space-1); color: var(--text-tertiary); font-size: .65rem; }.gym-card__scenario { color: var(--info); font-size: .68rem; font-weight: 700; }.gym-card__detail { position: absolute; right: 0; bottom: 0; z-index: 1; display: inline-flex; min-height: 2.75rem; align-items: center; padding-inline: var(--space-3); color: var(--status-info-strong); font-size: .72rem; font-weight: 750; text-underline-offset: .2em; }
.gym-card__visual { display: grid; min-height: 9.5rem; place-items: center; overflow: hidden; background: var(--surface-2); color: var(--text-tertiary); }.gym-card__visual img { transform: scale(1.5); }.gym-card__distance { display: flex; align-items: center; gap: var(--space-1); color: var(--status-info-text); font-size: .68rem; font-weight: 680; }.gym-card__distance svg { flex: 0 0 auto; }
.sheet-handle, .mobile-map-attribution { display: none; }.reopen-control { position: absolute; right: var(--space-4); top: var(--space-4); z-index: 5; }
:deep(.leaflet-control-zoom a) { width: 2.75rem; height: 2.75rem; line-height: 2.75rem; background: var(--surface-1); color: var(--text-primary); border-color: var(--border-subtle); }:deep(.leaflet-control-zoom a:hover) { background: var(--surface-raised); }:deep(.leaflet-control-attribution) { background: var(--surface-glass-soft); color: var(--text-secondary); }:deep(.leaflet-control-attribution a) { color: var(--info); }:deep(.gym-marker) { display: grid; place-items: center; border: 0; background: transparent; }:deep(.gym-marker span) { display: block; width: 1.25rem; height: 1.25rem; border: 3px solid var(--text-on-accent); border-radius: 50% 50% 50% 0; background: var(--accent); box-shadow: var(--shadow-md); transform: rotate(-45deg); }:deep(.gym-marker--selected span) { width: 1.65rem; height: 1.65rem; background: var(--warning); }
:deep(.gym-cluster) { display: grid; place-items: center; border: 0; background: transparent; }:deep(.gym-cluster span) { display: grid; width: 2.5rem; height: 2.5rem; place-items: center; border: 3px solid var(--text-on-accent); border-radius: 50%; background: var(--accent); box-shadow: var(--shadow-md); color: var(--text-on-accent); font-size: .78rem; font-weight: 800; }:deep(.user-location) { stroke: var(--text-on-accent); stroke-width: 4; fill: var(--info); filter: drop-shadow(0 4px 8px rgba(0,0,0,.35)); }:deep(.user-accuracy) { stroke: var(--info); stroke-width: 1; fill: var(--info-soft); fill-opacity: .45; }

@media (max-width: 47.99rem) {
  .explorer__intro { grid-template-columns: 1fr; gap: var(--space-4); }.explorer__intro h2 { max-width: 17ch; }.explorer__workspace, .explorer__workspace--collapsed { display: block; height: 38rem; }.explorer__map-wrap { min-height: 38rem; }
  .explorer__panel { position: absolute; inset-inline: 0; bottom: 0; height: calc(100% - 4.5rem); border: 1px solid var(--border-strong); border-bottom: 0; border-radius: var(--radius-dialog) var(--radius-dialog) 0 0; box-shadow: var(--shadow-lg); transform: translateY(calc(100% - 17rem)); }.explorer__panel--hidden { opacity: 1; pointer-events: auto; transform: translateY(calc(100% - 4.5rem)); }.explorer__panel--closed { transform: translateY(calc(100% - 4.5rem)); }.explorer__panel--medium { transform: translateY(calc(100% - 17rem)); }.explorer__panel--full { transform: translateY(0); }
  .sheet-handle { display: flex; width: 100%; min-height: 2.75rem; align-items: center; justify-content: center; gap: var(--space-2); border: 0; border-bottom: 1px solid var(--border-subtle); background: transparent; color: var(--text-tertiary); cursor: pointer; }.sheet-handle span { width: 2.5rem; height: 3px; border-radius: var(--radius-pill); background: var(--border-strong); }.mobile-map-attribution { position: absolute; top: -1.5rem; right: var(--space-2); z-index: 5; display: inline-flex; min-height: 1.5rem; align-items: center; padding-inline: .4rem; border-radius: var(--radius-control) var(--radius-control) 0 0; background: var(--surface-glass-soft); color: var(--info); font-size: .62rem; line-height: 1.2; text-underline-offset: .16em; }.collapse-control, .reopen-control { display: none; }.explorer__panel-header { min-height: 3.75rem; padding-block: var(--space-2); }.explorer__filters { padding-block: var(--space-3); }.explorer__filter-row { grid-template-columns: 1fr; }.map-context { bottom: 18rem; }.gym-card__select { grid-template-columns: 5.5rem minmax(0, 1fr); }.gym-card img { min-height: 8.5rem; }
  .map-tools { top: var(--space-3); right: var(--space-3); max-width: calc(100% - 5.5rem); }.map-tools :deep(.app-button) { min-width: 0; min-height: 2.75rem; padding-inline: var(--space-3); }.map-tools :deep(.app-button span) { overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }.map-context { left: var(--space-3); bottom: 18rem; max-width: calc(100% - var(--space-6)); }.location-status { grid-template-columns: 1.4rem minmax(0, 1fr); }.location-status button { grid-column: 2; justify-self: start; }.filters-toggle { display: grid; min-height: 2.75rem; grid-template-columns: auto 1fr auto auto; align-items: center; gap: var(--space-2); width: 100%; border: 0; border-bottom: 1px solid var(--border-subtle); padding-inline: var(--space-4); background: var(--bg-subtle); color: var(--text-primary); cursor: pointer; font: inherit; font-size: .78rem; font-weight: 700; text-align: left; }.filters-toggle__chevron--open { transform: rotate(180deg); }.explorer__filters { display: none; max-height: 52vh; overflow: auto; }.explorer__filters--open { display: grid; }.explorer__filter-row { grid-template-columns: 1fr; }.explorer__sort-row { grid-template-columns: 1fr; }.explorer__sort-row button { justify-self: start; }.gym-card__select { grid-template-columns: 5rem minmax(0, 1fr); }.gym-card img, .gym-card__visual { min-height: 8.5rem; }:deep(.leaflet-control-attribution) { display: none; }
}
</style>
