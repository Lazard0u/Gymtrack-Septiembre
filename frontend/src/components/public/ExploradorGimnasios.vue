<script setup>
import { computed, nextTick, onBeforeUnmount, onMounted, ref, watch } from 'vue'
import { useRoute } from 'vue-router'
import {
  IconAdjustmentsHorizontal,
  IconClock,
  IconChevronDown,
  IconChevronLeft,
  IconChevronRight,
  IconCurrentLocation,
  IconHeart,
  IconLocationOff,
  IconMapPin,
  IconRefresh,
  IconRoute,
  IconSearch,
  IconStar,
} from '@tabler/icons-vue'
import 'leaflet/dist/leaflet.css'
import { useAuthStore } from '../../stores/auth'
import { useSocioStore } from '../../stores/socio'
import { useGimnasiosPublicosStore } from '../../stores/gimnasiosPublicos'
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
import AppToast from '../ui/AppToast.vue'

const props = defineProps({
  compactHeading: Boolean,
  variant: { type: String, default: 'full' },
})

const route = useRoute()
const system = useSystemStore()
const auth = useAuthStore()
const member = useSocioStore()
const publicGyms = useGimnasiosPublicosStore()
const mapElement = ref(null)
const mapStatus = ref('loading')
const catalogStatus = computed(() => publicGyms.status)
const gyms = computed(() => publicGyms.items)
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
const favoriteWorkingId = ref(null)
const toast = ref({ open: false, title: '', message: '', tone: 'success' })
const cardElements = new Map()
const workspaceElement = ref(null)
const panelElement = ref(null)
const draggingSheet = ref(false)
const sheetDragOffset = ref(null)
const mobileViewport = ref(false)
let leaflet = null
let map = null
let markersLayer = null
let userLayer = null
let resizeObserver = null
let tileTimeout = null
let locationPermission = null
let locationPermissionHandler = null
let mobileMediaQuery = null
let mobileMediaHandler = null
let dragStartY = 0
let dragStartOffset = 0
let dragMoved = false
let lastSheetDragAt = 0

const PREFERENCES_KEY = 'gymtrack.explorer.preferences.v1'

const isHero = computed(() => props.variant === 'hero')
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
    const matchesQuery = term === '' || [gym.nombre, gym.direccion, gym.ciudad, gym.departamento, ...gym.categorias, ...gym.servicios]
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
const activeFilterCount = computed(() => [city.value, category.value, service.value, availability.value].filter(Boolean).length)
const totalFilterCount = computed(() => activeFilterCount.value + (query.value ? 1 : 0))
const selectedGym = computed(() => visibleGyms.value.find((gym) => gym.id === selectedGymId.value) || visibleGyms.value[0] || null)
const effectivePanelCollapsed = computed(() => !isHero.value && panelCollapsed.value && !mobileViewport.value)
const canPersistFavorites = computed(() => auth.user?.role === 'socio' && Boolean(auth.user?.active_gym_id))
const favoriteGymIds = computed(() => new Set((member.favorites.gyms || []).map((gym) => Number(gym.id))))
const panelStyle = computed(() => draggingSheet.value && sheetDragOffset.value !== null
  ? { transform: `translateY(${sheetDragOffset.value}px)`, transition: 'none' }
  : undefined)

async function loadGyms(force = false) {
  await publicGyms.load(force)
  if (publicGyms.status !== 'ready') return
  if (isHero.value && !selectedGymId.value) selectedGymId.value = gyms.value[0]?.id || null
  await nextTick()
  syncMarkers(true)
  applyRequestedFocus()
}

function applyRequestedFocus() {
  const routeSlug = typeof route.query.gimnasio === 'string' ? route.query.gimnasio : ''
  const requested = visibleGyms.value.find((gym) => Number(gym.id) === publicGyms.focus.id || (publicGyms.focus.slug && gym.slug === publicGyms.focus.slug) || (routeSlug && gym.slug === routeSlug))
  if (requested) selectGym(requested, true, true)
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
    applyRequestedFocus()
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
          className: `gym-marker${selected ? ' gym-marker--selected' : ''}${gym.is_demo ? ' gym-marker--demo' : ''}`,
          html: '<span></span>',
          iconSize: [44, 44],
          iconAnchor: [22, 22],
        }),
      })
      const tooltip = document.createElement('span')
      tooltip.textContent = `${gym.nombre}${gym.is_demo ? ' · Demostración' : ''}`
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

function primarySchedule(gym) {
  if (Array.isArray(gym?.horarios)) {
    const schedule = gym.horarios[0]
    if (typeof schedule === 'string') return schedule
    return schedule?.label || schedule?.texto || 'Horario publicado en la ficha'
  }
  if (gym?.horarios && typeof gym.horarios === 'object') {
    const entries = Object.entries(gym.horarios)
    const [period, hours] = entries.find(([key]) => key.startsWith('lunes')) || entries[0] || []
    if (!period || !hours) return 'Horario no publicado'
    const label = period.replace('lunes_viernes', 'Lun a vie').replace('lunes_sabado', 'Lun a sáb').replaceAll('_', ' ')
    return `${label} ${hours}`
  }
  return 'Horario no publicado'
}

function operationalState(gym) {
  if (gym?.estado === 'temporalmente_cerrado') return { label: 'Cerrado temporalmente', tone: 'warning' }
  const schedules = gym?.horarios
  if (!schedules || Array.isArray(schedules)) return { label: 'Consultar horario', tone: 'neutral' }
  try {
    const now = new Date()
    const weekday = new Intl.DateTimeFormat('en-US', { weekday: 'long', timeZone: gym.zona_horaria || 'America/Montevideo' }).format(now).toLowerCase()
    const dayNames = { monday: 'lunes', tuesday: 'martes', wednesday: 'miercoles', thursday: 'jueves', friday: 'viernes', saturday: 'sabado', sunday: 'domingo' }
    const day = dayNames[weekday]
    const workday = ['lunes', 'martes', 'miercoles', 'jueves', 'viernes'].includes(day)
    const entry = Object.entries(schedules).find(([key]) => key === day || (workday && key === 'lunes_viernes') || (day !== 'domingo' && key === 'lunes_sabado'))
    if (!entry) return { label: 'Horario no publicado', tone: 'neutral' }
    const hours = String(entry[1])
    if (hours.toLocaleLowerCase('es').includes('cerrado')) return { label: 'Cerrado ahora', tone: 'neutral' }
    const range = hours.match(/(\d{1,2}):(\d{2})\D+(\d{1,2}):(\d{2})/)
    if (!range) return { label: hours, tone: 'neutral' }
    const parts = new Intl.DateTimeFormat('en-US', { hour: '2-digit', minute: '2-digit', hourCycle: 'h23', timeZone: gym.zona_horaria || 'America/Montevideo' }).formatToParts(now)
    const minutes = Number(parts.find((part) => part.type === 'hour')?.value) * 60 + Number(parts.find((part) => part.type === 'minute')?.value)
    const opens = Number(range[1]) * 60 + Number(range[2])
    const closes = Number(range[3]) * 60 + Number(range[4])
    return minutes >= opens && minutes < closes ? { label: 'Abierto ahora', tone: 'success' } : { label: 'Cerrado ahora', tone: 'neutral' }
  } catch {
    return { label: 'Consultar horario', tone: 'neutral' }
  }
}

function imageFailed(path) {
  failedImages.value = new Set([...failedImages.value, path])
}

async function loadFavorites() {
  if (!canPersistFavorites.value) return
  await member.loadFavorites()
}

async function toggleFavorite(gym) {
  if (!canPersistFavorites.value || favoriteWorkingId.value !== null) return
  const wasFavorite = favoriteGymIds.value.has(Number(gym.id))
  favoriteWorkingId.value = Number(gym.id)
  try {
    await member.setFavorite('gym', gym.id, !wasFavorite)
    toast.value = {
      open: true,
      title: wasFavorite ? 'Gimnasio eliminado de favoritos' : 'Gimnasio guardado',
      message: wasFavorite ? `${gym.nombre} ya no aparece en tu perfil.` : `${gym.nombre} quedó disponible en tu perfil.`,
      tone: 'success',
    }
  } catch (error) {
    toast.value = { open: true, title: 'No se pudo actualizar el favorito', message: error.message, tone: 'danger' }
  } finally {
    favoriteWorkingId.value = null
  }
}

async function togglePanel() {
  const collapsing = !panelCollapsed.value
  panelCollapsed.value = collapsing
  await nextTick()
  workspaceElement.value?.querySelector(collapsing ? '.reopen-control' : '.collapse-control')?.focus()
  window.setTimeout(() => map?.invalidateSize({ pan: false }), 240)
}

function cycleSheet() {
  if (Date.now() - lastSheetDragAt < 300) return
  sheetPosition.value = sheetPosition.value === 'closed' ? 'medium' : sheetPosition.value === 'medium' ? 'full' : 'closed'
  window.setTimeout(() => map?.invalidateSize({ pan: false }), 240)
}

function sheetSnapOffsets() {
  const height = panelElement.value?.getBoundingClientRect().height || 0
  return {
    full: 0,
    medium: Math.max(0, height - 384),
    closed: Math.max(0, height - 72),
  }
}

function startSheetDrag(event) {
  if (!window.matchMedia('(max-width: 47.99rem)').matches || !panelElement.value) return
  const offsets = sheetSnapOffsets()
  dragStartY = event.clientY
  dragStartOffset = offsets[sheetPosition.value]
  dragMoved = false
  draggingSheet.value = true
  sheetDragOffset.value = dragStartOffset
  event.currentTarget.setPointerCapture?.(event.pointerId)
}

function moveSheetDrag(event) {
  if (!draggingSheet.value) return
  const offsets = sheetSnapOffsets()
  const delta = event.clientY - dragStartY
  if (Math.abs(delta) > 5) dragMoved = true
  sheetDragOffset.value = Math.min(offsets.closed, Math.max(0, dragStartOffset + delta))
}

function endSheetDrag(event) {
  if (!draggingSheet.value) return
  event.currentTarget.releasePointerCapture?.(event.pointerId)
  const offsets = sheetSnapOffsets()
  const current = sheetDragOffset.value ?? dragStartOffset
  sheetPosition.value = Object.entries(offsets).sort((first, second) => Math.abs(current - first[1]) - Math.abs(current - second[1]))[0][0]
  draggingSheet.value = false
  sheetDragOffset.value = null
  if (dragMoved) lastSheetDragAt = Date.now()
  window.setTimeout(() => map?.invalidateSize({ pan: false }), 240)
}

function restorePreferences() {
  try {
    const preferences = JSON.parse(window.localStorage.getItem(PREFERENCES_KEY) || '{}')
    filtersOpen.value = Boolean(preferences.filtersOpen)
    panelCollapsed.value = Boolean(preferences.panelCollapsed)
    if (['closed', 'medium', 'full'].includes(preferences.sheetPosition)) sheetPosition.value = preferences.sheetPosition
    city.value = typeof preferences.city === 'string' ? preferences.city : ''
    category.value = typeof preferences.category === 'string' ? preferences.category : ''
    service.value = typeof preferences.service === 'string' ? preferences.service : ''
    availability.value = typeof preferences.availability === 'string' ? preferences.availability : ''
  } catch { /* Las preferencias son opcionales; una entrada inválida vuelve a la vista inicial. */ }
}

function savePreferences() {
  try {
    window.localStorage.setItem(PREFERENCES_KEY, JSON.stringify({
      filtersOpen: filtersOpen.value,
      panelCollapsed: panelCollapsed.value,
      sheetPosition: sheetPosition.value,
      city: city.value,
      category: category.value,
      service: service.value,
      availability: availability.value,
    }))
  } catch { /* El explorador funciona aunque el navegador bloquee el almacenamiento local. */ }
}

watch(filteredGyms, () => {
  if (isHero.value && !visibleGyms.value.some((gym) => gym.id === selectedGymId.value)) {
    selectedGymId.value = visibleGyms.value[0]?.id || null
  }
  syncMarkers(true)
})
watch(selectedGymId, () => syncMarkers(false))
watch(() => publicGyms.focus.version, () => nextTick(applyRequestedFocus))
watch([filtersOpen, panelCollapsed, sheetPosition, city, category, service, availability], savePreferences)
watch(() => [auth.user?.role, auth.user?.active_gym_id], loadFavorites, { immediate: true })
onMounted(async () => {
  restorePreferences()
  mobileMediaQuery = window.matchMedia('(max-width: 47.99rem)')
  mobileViewport.value = mobileMediaQuery.matches
  mobileMediaHandler = (event) => { mobileViewport.value = event.matches }
  mobileMediaQuery.addEventListener?.('change', mobileMediaHandler)
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
  if (route.query.estado === 'creado') {
    toast.value = { open: true, title: 'Gimnasio creado y publicado', message: 'La lista y el marcador ya utilizan los datos guardados.', tone: 'success' }
  }
})
onBeforeUnmount(() => {
  window.clearTimeout(tileTimeout)
  locationPermission?.removeEventListener?.('change', locationPermissionHandler)
  mobileMediaQuery?.removeEventListener?.('change', mobileMediaHandler)
  resizeObserver?.disconnect()
  map?.remove()
})
</script>

<template>
  <section :class="['explorer', { 'explorer--hero': isHero }]" aria-labelledby="explorer-title">
    <div v-if="!compactHeading && !isHero" class="explorer__intro">
      <div><span class="eyebrow">Explorar</span><h2 id="explorer-title">Tu próximo gimnasio, en contexto</h2></div>
      <p>Compará sedes publicadas, filtrá el catálogo y elegí una tarjeta para ubicarla en el mapa.</p>
    </div>
    <h2 v-else id="explorer-title" class="visually-hidden">Mapa para descubrir gimnasios cercanos</h2>

    <div ref="workspaceElement" :class="['explorer__workspace', { 'explorer__workspace--collapsed': effectivePanelCollapsed, 'explorer__workspace--hero': isHero }]">
      <div class="explorer__map-wrap">
        <div ref="mapElement" class="explorer__map" role="region" aria-label="Mapa interactivo de gimnasios de GymTrack" />
        <div v-if="mapStatus === 'loading'" class="explorer__map-state" role="status" aria-label="Cargando mapa"><AppSkeleton width="100%" height="100%" /></div>
        <div v-else-if="mapStatus === 'error'" class="explorer__map-state"><AppErrorState title="El mapa no está disponible" description="No pudimos cargar OpenStreetMap en este momento." @retry="initialiseMap" /></div>
        <div v-if="isHero" class="hero-map-search">
          <AppInput v-model="query" label="Buscar barrio, ciudad o gimnasio" name="hero-gym-search" placeholder="Barrio, ciudad o gimnasio" />
        </div>
        <div class="map-tools" aria-label="Controles de ubicación">
          <AppButton v-if="locationStatus !== 'ready'" variant="secondary" size="sm" :loading="locationStatus === 'requesting'" @click="requestLocation"><template #icon><IconCurrentLocation :size="18" /></template>Usar mi ubicación</AppButton>
          <AppIconButton v-else label="Actualizar mi ubicación" @click="requestLocation"><IconRefresh :size="19" /></AppIconButton>
          <AppIconButton :label="recenterLabel" @click="recenterMap"><IconCurrentLocation :size="19" /></AppIconButton>
        </div>
        <div class="map-context"><IconMapPin :size="17" aria-hidden="true" /><span>{{ visibleGyms.length }} ubicaciones visibles</span><span v-if="userLocation && order === 'distance'">· ordenadas por cercanía</span></div>
        <div v-if="isHero && !['idle', 'ready'].includes(locationStatus)" class="hero-location-status" role="status" aria-live="polite">
          <component :is="locationStatus === 'denied' || locationStatus === 'unsupported' ? IconLocationOff : IconRoute" :size="19" aria-hidden="true" />
          <div><strong>{{ locationCopy.title }}</strong><span>{{ locationCopy.text }}</span></div>
          <button v-if="['denied', 'unavailable'].includes(locationStatus)" type="button" @click="useFallbackLocation">Continuar con vista general</button>
        </div>
        <article v-if="isHero && catalogStatus === 'ready' && selectedGym" class="hero-gym-card" aria-live="polite">
          <span class="hero-gym-card__visual"><img v-if="selectedGym.imagen_path && !failedImages.has(selectedGym.imagen_path)" :src="selectedGym.imagen_path" alt="" width="160" height="120" @error="imageFailed(selectedGym.imagen_path)" /><IconMapPin v-else :size="24" aria-hidden="true" /></span>
          <div class="hero-gym-card__title">
            <div><strong>{{ selectedGym.nombre }}</strong><span>{{ selectedGym.direccion }} · {{ selectedGym.ciudad }}</span></div>
            <span class="hero-gym-card__badges"><AppBadge v-if="selectedGym.is_demo" tone="info">Demostración</AppBadge><AppBadge :tone="operationalState(selectedGym).tone">{{ operationalState(selectedGym).label }}</AppBadge></span>
          </div>
          <div class="hero-gym-card__facts">
            <span><IconRoute :size="15" aria-hidden="true" />{{ selectedGym.distance !== null ? formatDistance(selectedGym.distance) : 'Distancia al usar tu ubicación' }}</span>
            <span><IconStar :size="15" aria-hidden="true" />{{ selectedGym.valoracion ? `${selectedGym.valoracion} de 5` : 'Sin valoraciones aún' }}</span>
            <span><IconClock :size="15" aria-hidden="true" />{{ primarySchedule(selectedGym) }}</span>
          </div>
          <RouterLink :to="{ name: 'gym-detail', params: { slug: selectedGym.slug } }">Ver gimnasio<IconChevronRight :size="17" aria-hidden="true" /></RouterLink>
        </article>
        <div v-else-if="isHero && catalogStatus === 'ready' && !selectedGym" class="hero-empty-state" role="status">
          <strong>Sin resultados</strong><span>Probá con otro barrio, ciudad o gimnasio.</span>
        </div>
        <div v-else-if="isHero && catalogStatus === 'error'" class="hero-empty-state" role="alert">
          <strong>No pudimos cargar los gimnasios</strong><button type="button" @click="loadGyms">Intentar nuevamente</button>
        </div>
        <div v-else-if="isHero && catalogStatus === 'loading'" class="hero-empty-state" role="status">
          <AppSpinner /><span>Cargando gimnasios cercanos…</span>
        </div>
      </div>

      <aside v-if="!isHero" ref="panelElement" :style="panelStyle" :class="['explorer__panel', `explorer__panel--${sheetPosition}`, { 'explorer__panel--hidden': effectivePanelCollapsed, 'explorer__panel--dragging': draggingSheet, 'explorer__panel--filters-open': filtersOpen }]" aria-label="Lista de gimnasios" :aria-hidden="effectivePanelCollapsed || undefined" :inert="effectivePanelCollapsed">
        <a class="mobile-map-attribution" href="https://www.openstreetmap.org/copyright" target="_blank" rel="noopener noreferrer">© OpenStreetMap</a>
        <button class="sheet-handle" type="button" :aria-label="sheetLabel" @pointerdown="startSheetDrag" @pointermove="moveSheetDrag" @pointerup="endSheetDrag" @pointercancel="endSheetDrag" @click="cycleSheet"><span /><IconChevronDown :size="18" aria-hidden="true" /></button>
        <div class="explorer__panel-content" :aria-hidden="mobileViewport && sheetPosition === 'closed' || undefined" :inert="mobileViewport && sheetPosition === 'closed'">
          <header class="explorer__panel-header">
          <div><strong>Gimnasios</strong><span>{{ visibleGyms.length }} de {{ gyms.length }} sedes</span></div>
          <AppBadge v-if="system.demoDataActive" tone="info">Datos demo</AppBadge>
          <AppIconButton class="collapse-control" :label="panelCollapsed ? 'Mostrar lista' : 'Ocultar lista'" :pressed="!panelCollapsed" @click="togglePanel">
            <IconChevronRight v-if="!panelCollapsed" :size="20" /><IconChevronLeft v-else :size="20" />
          </AppIconButton>
          </header>

          <div class="explorer__search">
            <AppInput v-model="query" label="Buscar gimnasios" name="gym-search" placeholder="Nombre, ciudad o categoría" />
          </div>

          <div v-if="locationStatus !== 'idle'" class="location-status location-status--compact" role="status" aria-live="polite">
          <component :is="locationStatus === 'denied' || locationStatus === 'unsupported' ? IconLocationOff : IconRoute" :size="19" aria-hidden="true" />
          <div><strong>{{ locationCopy.title }}</strong><span>{{ locationCopy.text }}</span></div>
          <button v-if="['denied', 'unavailable'].includes(locationStatus)" type="button" @click="useFallbackLocation">Usar vista general</button>
          </div>

          <button class="filters-toggle" type="button" :aria-expanded="filtersOpen" aria-controls="gym-filters" @click="filtersOpen = !filtersOpen">
          <IconAdjustmentsHorizontal :size="19" aria-hidden="true" /><span>{{ filtersOpen ? 'Ocultar filtros' : 'Filtros y orden' }}</span><AppBadge v-if="activeFilterCount" tone="info">{{ activeFilterCount }}</AppBadge><span class="filters-toggle__order">{{ order === 'distance' ? 'Más cercanos' : 'Por nombre' }}</span><IconChevronDown :class="{ 'filters-toggle__chevron--open': filtersOpen }" :size="18" aria-hidden="true" />
          </button>
          <div id="gym-filters" :class="['explorer__filters', { 'explorer__filters--open': filtersOpen }]">
          <div v-if="locationStatus === 'idle'" class="location-status" role="status">
            <component :is="locationStatus === 'denied' || locationStatus === 'unsupported' ? IconLocationOff : IconRoute" :size="19" aria-hidden="true" />
            <div><strong>{{ locationCopy.title }}</strong><span>{{ locationCopy.text }}</span></div>
            <button v-if="['denied', 'unavailable'].includes(locationStatus)" type="button" @click="useFallbackLocation">Usar vista general</button>
          </div>
          <div class="explorer__filter-row">
            <AppSelect v-model="city" label="Ciudad" name="gym-city" :options="cityOptions" />
            <AppSelect v-model="category" label="Categoría" name="gym-category" :options="categoryOptions" />
            <AppSelect v-model="service" label="Servicio" name="gym-service" :options="serviceOptions" />
            <AppSelect v-model="availability" label="Estado" name="gym-availability" :options="availabilityOptions" />
          </div>
          <div class="explorer__sort-row"><AppSelect v-model="order" label="Ordenar por" name="gym-order" :options="orderOptions" /><button v-if="totalFilterCount" type="button" @click="resetFilters">Limpiar filtros</button></div>
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
                  <span class="gym-card__title"><strong>{{ gym.nombre }}</strong><span class="gym-card__badges"><AppBadge v-if="gym.is_demo" tone="info">Demostración</AppBadge><AppBadge :tone="operationalState(gym).tone">{{ operationalState(gym).label }}</AppBadge></span></span>
                  <span class="gym-card__location">{{ gym.direccion }} · {{ gym.ciudad }}, {{ gym.departamento }}</span>
                  <span v-if="gym.distance !== null" class="gym-card__distance"><IconRoute :size="15" aria-hidden="true" />A {{ formatDistance(gym.distance) }} de tu ubicación</span>
                  <span class="gym-card__categories">{{ gym.categorias.join(' · ') }}</span>
                  <span class="gym-card__facts"><span>{{ gym.clases_activas }} clases</span><span>{{ gym.membresias_activas }} membresías activas</span></span>
                  <span v-if="gym.demo_scenario" class="gym-card__scenario">{{ gym.demo_scenario.label }}</span>
                </span>
              </button>
              <button v-if="canPersistFavorites" class="gym-card__favorite" type="button" :aria-pressed="favoriteGymIds.has(Number(gym.id))" :disabled="favoriteWorkingId !== null" @click="toggleFavorite(gym)"><IconHeart :size="16" :fill="favoriteGymIds.has(Number(gym.id)) ? 'currentColor' : 'none'" aria-hidden="true" /><span>{{ favoriteGymIds.has(Number(gym.id)) ? 'Guardado' : 'Guardar' }}</span></button>
              <RouterLink class="gym-card__detail" :to="{ name: 'gym-detail', params: { slug: gym.slug } }">Ver ficha</RouterLink>
            </article>
          </div>
          </div>
        </div>
      </aside>

      <AppIconButton v-if="effectivePanelCollapsed" class="reopen-control" label="Mostrar lista de gimnasios" :pressed="false" @click="togglePanel"><IconChevronLeft :size="20" /></AppIconButton>
    </div>
    <AppToast v-bind="toast" @close="toast.open = false" />
  </section>
</template>

<style scoped>
.explorer__intro { display: grid; grid-template-columns: minmax(0, 1.25fr) minmax(18rem, .75fr); align-items: end; gap: var(--space-12); margin-bottom: var(--space-8); }
.explorer__intro h2 { max-width: 14ch; margin: 0; }.explorer__intro p { margin: 0; color: var(--text-secondary); }
.explorer__workspace { position: relative; display: grid; height: clamp(40rem, 70vh, 47rem); grid-template-columns: minmax(0, 1fr) minmax(22rem, 27rem); overflow: hidden; border: 1px solid var(--border-subtle); border-radius: var(--radius-dialog); background: var(--surface-1); box-shadow: var(--shadow-md); transition: grid-template-columns var(--duration-normal) var(--ease-out); }
.explorer__workspace--collapsed { grid-template-columns: minmax(0, 1fr) 0; }.explorer__map-wrap { position: relative; min-width: 0; min-height: 40rem; background: var(--surface-2); }.explorer__map { position: absolute; inset: 0; z-index: 0; }.explorer__map-state { position: absolute; inset: 0; z-index: 2; background: var(--surface-1); }.explorer__map-state :deep(.skeleton) { border-radius: 0; }
.explorer__workspace--hero { height: clamp(31rem, 67vh, 38rem); grid-template-columns: minmax(0, 1fr); }
.explorer--hero .explorer__map-wrap { min-height: 31rem; }
.hero-map-search { position: absolute; top: var(--space-4); left: var(--space-4); z-index: 3; width: min(22rem, calc(100% - var(--space-8))); }
.hero-map-search :deep(.field) { gap: 0; }
.hero-map-search :deep(.field__label) { position: absolute; width: 1px; height: 1px; overflow: hidden; clip: rect(0 0 0 0); white-space: nowrap; }
.hero-map-search :deep(.field__control) { min-height: 3rem; padding-inline: var(--space-4); background: var(--surface-glass); box-shadow: var(--shadow-md); backdrop-filter: blur(12px); }
.explorer--hero .map-tools { top: 4.5rem; }
.explorer--hero :deep(.leaflet-top.leaflet-left) { top: 3.75rem; }
.hero-location-status { position: absolute; top: 8rem; left: var(--space-4); z-index: 3; display: grid; width: min(24rem, calc(100% - var(--space-8))); grid-template-columns: auto minmax(0, 1fr); gap: var(--space-2) var(--space-3); border: 1px solid var(--border-strong); border-radius: var(--radius-card); padding: var(--space-3); background: var(--surface-glass); box-shadow: var(--shadow-md); backdrop-filter: blur(12px); }
.hero-location-status > svg { color: var(--warning); }
.hero-location-status div { display: grid; gap: .15rem; }
.hero-location-status strong { font-size: .78rem; }
.hero-location-status span { color: var(--text-secondary); font-size: .68rem; line-height: 1.4; }
.hero-location-status button, .hero-empty-state button { grid-column: 2; justify-self: start; min-height: 2.75rem; border: 0; padding: 0; background: transparent; color: var(--status-info-strong); cursor: pointer; font: inherit; font-size: .72rem; font-weight: 720; text-decoration: underline; text-underline-offset: .2em; }
.hero-gym-card { position: absolute; right: var(--space-4); bottom: var(--space-4); left: var(--space-4); z-index: 3; display: grid; grid-template-columns: 4.5rem minmax(0, 1fr) auto; grid-template-rows: auto auto; align-items: center; gap: var(--space-2) var(--space-4); border: 1px solid var(--border-strong); border-radius: var(--radius-card); padding: var(--space-4); background: var(--surface-glass); box-shadow: var(--shadow-lg); backdrop-filter: blur(16px); }
.hero-gym-card__visual { display: grid; width: 4.5rem; height: 4.5rem; place-items: center; overflow: hidden; border-radius: var(--radius-control); background: var(--surface-2); color: var(--text-tertiary); }
.hero-gym-card__visual img { width: 100%; height: 100%; object-fit: cover; }
.hero-gym-card__visual { grid-row: 1 / 3; }
.hero-gym-card__title { display: flex; min-width: 0; align-items: flex-start; gap: var(--space-2); }
.hero-gym-card__title > div { display: grid; min-width: 0; gap: .15rem; }
.hero-gym-card__title strong { overflow: hidden; font-size: .9rem; text-overflow: ellipsis; white-space: nowrap; }
.hero-gym-card__title span { color: var(--text-secondary); font-size: .69rem; }
.hero-gym-card__badges,.gym-card__badges { display: flex; flex: 0 0 auto; flex-wrap: wrap; justify-content: flex-end; gap: var(--space-1); }
.hero-gym-card__facts { display: grid; min-width: 0; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: .35rem var(--space-3); color: var(--text-secondary); font-size: .68rem; }
.hero-gym-card__facts span { display: flex; min-width: 0; align-items: center; gap: var(--space-1); }
.hero-gym-card__facts span:last-child { grid-column: 1 / -1; }
.hero-gym-card__facts svg { flex: 0 0 auto; color: var(--info); }
.hero-gym-card > a { display: inline-flex; min-height: 2.75rem; grid-column: 3; grid-row: 1 / 3; align-items: center; gap: var(--space-1); color: var(--status-info-strong); font-size: .75rem; font-weight: 750; text-decoration: none; white-space: nowrap; }
.hero-empty-state { position: absolute; right: var(--space-4); bottom: var(--space-4); left: var(--space-4); z-index: 3; display: flex; min-height: 4.5rem; align-items: center; gap: var(--space-3); border: 1px solid var(--border-strong); border-radius: var(--radius-card); padding: var(--space-4); background: var(--surface-glass); box-shadow: var(--shadow-md); color: var(--text-secondary); font-size: .8rem; backdrop-filter: blur(12px); }
.hero-empty-state strong { color: var(--text-primary); }
.explorer--hero .map-context { bottom: 7.75rem; }
.map-tools { position: absolute; top: var(--space-4); right: var(--space-4); z-index: 3; display: flex; align-items: center; gap: var(--space-2); }.map-tools :deep(.app-button), .map-tools :deep(.icon-button) { background: var(--surface-glass); backdrop-filter: blur(10px); }.map-context { position: absolute; left: var(--space-4); bottom: var(--space-4); z-index: 3; display: flex; align-items: center; gap: var(--space-2); border: 1px solid var(--border-subtle); border-radius: var(--radius-pill); padding: var(--space-2) var(--space-3); background: var(--surface-glass); color: var(--text-secondary); font-size: .72rem; backdrop-filter: blur(10px); }
.explorer__panel { position: relative; z-index: 4; display: flex; min-width: 0; min-height: 0; flex-direction: column; border-left: 1px solid var(--border-subtle); background: var(--bg-subtle); transition: opacity var(--duration-fast), transform var(--duration-normal) var(--ease-out); }.explorer__panel--hidden { opacity: 0; pointer-events: none; transform: translateX(100%); }
.explorer__panel-content { display: flex; min-height: 0; flex: 1; flex-direction: column; }
.explorer__panel-header { display: flex; min-height: 4.75rem; align-items: center; gap: var(--space-3); padding: var(--space-4); border-bottom: 1px solid var(--border-subtle); }.explorer__panel-header > div:first-child { display: grid; margin-right: auto; }.explorer__panel-header strong { font-size: .95rem; }.explorer__panel-header > div:first-child > span { color: var(--text-tertiary); font-size: .72rem; }
.explorer__search { padding: var(--space-3) var(--space-4); border-bottom: 1px solid var(--border-subtle); }.explorer__search :deep(.field) { gap: var(--space-1); }.explorer__search :deep(.field > span:first-child) { font-size: .72rem; }
.location-status { display: grid; grid-template-columns: 1.5rem minmax(0, 1fr) auto; align-items: start; gap: var(--space-3); border-radius: var(--radius-control); padding: var(--space-3); background: var(--surface-1); }.location-status--compact { margin: var(--space-2) var(--space-4); padding-block: var(--space-2); }.location-status > svg { margin-top: .1rem; color: var(--info); }.location-status div { display: grid; gap: .1rem; }.location-status strong { font-size: .76rem; }.location-status span { color: var(--text-tertiary); font-size: .66rem; line-height: 1.4; }.location-status button, .explorer__sort-row button { display: inline-flex; min-height: 2.75rem; align-items: center; align-self: center; border: 0; padding: var(--space-1) 0; background: transparent; color: var(--status-info-strong); cursor: pointer; font: inherit; font-size: .69rem; font-weight: 720; text-decoration: underline; text-underline-offset: .2em; }
.filters-toggle { display: grid; min-height: 2.75rem; grid-template-columns: auto minmax(0, 1fr) auto auto auto; align-items: center; gap: var(--space-2); width: 100%; border: 0; border-bottom: 1px solid var(--border-subtle); padding-inline: var(--space-4); background: var(--bg-subtle); color: var(--text-primary); cursor: pointer; font: inherit; font-size: .76rem; font-weight: 700; text-align: left; }.filters-toggle__order { color: var(--text-tertiary); font-size: .66rem; font-weight: 560; }.filters-toggle__chevron--open { transform: rotate(180deg); }.explorer__filters { display: none; gap: var(--space-3); max-height: 21rem; overflow: auto; padding: var(--space-3) var(--space-4); border-bottom: 1px solid var(--border-subtle); background: var(--bg-subtle); scrollbar-color: var(--border-strong) var(--bg-subtle); }.explorer__filters--open { display: grid; }.explorer__filter-row { display: grid; grid-template-columns: 1fr 1fr; gap: var(--space-2); }.explorer__sort-row { display: grid; grid-template-columns: minmax(0, 1fr) auto; align-items: end; gap: var(--space-3); }.explorer__filters :deep(.field__label), .explorer__filters :deep(.field > span:first-child) { font-size: .72rem; }.explorer__panel-body { flex: 1; min-height: 13rem; overflow: auto; scrollbar-color: var(--border-strong) var(--bg-subtle); }.catalog-loading { display: flex; min-height: 12rem; align-items: center; justify-content: center; gap: var(--space-3); color: var(--text-secondary); font-size: .85rem; }
.gym-list { display: grid; gap: var(--space-2); padding: var(--space-3); }.gym-card { position: relative; width: 100%; overflow: hidden; border: 1px solid var(--border-subtle); border-radius: var(--radius-card); background: var(--surface-1); color: var(--text-primary); transition: border-color var(--duration-fast), background-color var(--duration-fast), transform var(--duration-fast) var(--ease-out); }.gym-card:hover { border-color: var(--border-strong); background: var(--surface-2); }.gym-card--selected { border-color: var(--focus); background: var(--accent-soft); }.gym-card__select { display: grid; grid-template-columns: 6.5rem minmax(0, 1fr); gap: var(--space-3); width: 100%; border: 0; padding: 0; background: transparent; color: inherit; cursor: pointer; text-align: left; }.gym-card img { width: 100%; height: 100%; min-height: 9.5rem; object-fit: cover; }.gym-card__content { display: grid; align-content: center; gap: var(--space-1); min-width: 0; padding: var(--space-3) var(--space-3) 2.75rem 0; }.gym-card__title { display: flex; align-items: flex-start; justify-content: space-between; gap: var(--space-2); }.gym-card__title strong { overflow: hidden; font-size: .86rem; text-overflow: ellipsis; white-space: nowrap; }.gym-card__location, .gym-card__categories { color: var(--text-secondary); font-size: .7rem; }.gym-card__facts { display: flex; flex-wrap: wrap; gap: var(--space-2); margin-top: var(--space-1); color: var(--text-tertiary); font-size: .65rem; }.gym-card__scenario { color: var(--info); font-size: .68rem; font-weight: 700; }.gym-card__detail { position: absolute; right: 0; bottom: 0; z-index: 1; display: inline-flex; min-height: 2.75rem; align-items: center; padding-inline: var(--space-3); color: var(--status-info-strong); font-size: .72rem; font-weight: 750; text-underline-offset: .2em; }
.gym-card__visual { display: grid; min-height: 9.5rem; place-items: center; overflow: hidden; background: var(--surface-2); color: var(--text-tertiary); }.gym-card__visual img { transform: scale(1.5); }.gym-card__distance { display: flex; align-items: center; gap: var(--space-1); color: var(--status-info-text); font-size: .68rem; font-weight: 680; }.gym-card__distance svg { flex: 0 0 auto; }
.gym-card__favorite { position: absolute; left: calc(6.5rem + var(--space-3)); bottom: 0; z-index: 1; display: inline-flex; min-height: 2.75rem; align-items: center; gap: var(--space-1); border: 0; padding: 0 var(--space-2); background: transparent; color: var(--text-secondary); cursor: pointer; font: inherit; font-size: .7rem; font-weight: 720; }.gym-card__favorite:hover,.gym-card__favorite[aria-pressed="true"] { color: var(--status-info-strong); }.gym-card__favorite:disabled { cursor: wait; opacity: .65; }
.sheet-handle, .mobile-map-attribution { display: none; }.reopen-control { position: absolute; right: var(--space-4); top: var(--space-4); z-index: 5; }
:deep(.leaflet-control-zoom a) { width: 2.75rem; height: 2.75rem; line-height: 2.75rem; background: var(--surface-1); color: var(--text-primary); border-color: var(--border-subtle); }:deep(.leaflet-control-zoom a:hover) { background: var(--surface-raised); }:deep(.leaflet-control-attribution) { background: var(--surface-glass-soft); color: var(--text-secondary); }:deep(.leaflet-control-attribution a) { color: var(--info); }:deep(.gym-marker) { display: grid; place-items: center; border: 0; background: transparent; }:deep(.gym-marker span) { display: block; width: 1.25rem; height: 1.25rem; border: 3px solid var(--text-on-accent); border-radius: 50% 50% 50% 0; background: var(--accent); box-shadow: var(--shadow-md); transform: rotate(-45deg); }:deep(.gym-marker--selected span) { width: 1.65rem; height: 1.65rem; background: var(--warning); }:deep(.gym-marker--demo span) { outline: 2px solid var(--info); outline-offset: 2px; }
:deep(.gym-cluster) { display: grid; place-items: center; border: 0; background: transparent; }:deep(.gym-cluster span) { display: grid; width: 2.5rem; height: 2.5rem; place-items: center; border: 3px solid var(--text-on-accent); border-radius: 50%; background: var(--accent); box-shadow: var(--shadow-md); color: var(--text-on-accent); font-size: .78rem; font-weight: 800; }:deep(.user-location) { stroke: var(--text-on-accent); stroke-width: 4; fill: var(--info); filter: drop-shadow(0 4px 8px rgba(0,0,0,.35)); }:deep(.user-accuracy) { stroke: var(--info); stroke-width: 1; fill: var(--info-soft); fill-opacity: .45; }

@media (max-width: 47.99rem) {
  .explorer__intro { grid-template-columns: 1fr; gap: var(--space-4); }.explorer__intro h2 { max-width: 17ch; }.explorer__workspace, .explorer__workspace--collapsed { display: block; height: 38rem; }.explorer__map-wrap { min-height: 38rem; }
  .explorer__panel { position: absolute; inset-inline: 0; bottom: 0; height: calc(100% - 4.5rem); border: 1px solid var(--border-strong); border-bottom: 0; border-radius: var(--radius-dialog) var(--radius-dialog) 0 0; box-shadow: var(--shadow-lg); transform: translateY(calc(100% - 24rem)); }.explorer__panel--hidden { opacity: 1; pointer-events: auto; transform: translateY(calc(100% - 4.5rem)); }.explorer__panel--closed { transform: translateY(calc(100% - 4.5rem)); }.explorer__panel--medium { transform: translateY(calc(100% - 24rem)); }.explorer__panel--full { transform: translateY(0); }
  .sheet-handle { display: flex; width: 100%; min-height: 2.75rem; touch-action: none; user-select: none; align-items: center; justify-content: center; gap: var(--space-2); border: 0; border-bottom: 1px solid var(--border-subtle); background: transparent; color: var(--text-tertiary); cursor: grab; }.sheet-handle:active { cursor: grabbing; }.sheet-handle span { width: 2.5rem; height: 3px; border-radius: var(--radius-pill); background: var(--border-strong); }.mobile-map-attribution { position: absolute; top: -1.5rem; right: var(--space-2); z-index: 5; display: inline-flex; min-height: 1.5rem; align-items: center; padding-inline: .4rem; border-radius: var(--radius-control) var(--radius-control) 0 0; background: var(--surface-glass-soft); color: var(--info); font-size: .62rem; line-height: 1.2; text-underline-offset: .16em; }.collapse-control, .reopen-control { display: none; }.explorer__panel-header { min-height: 3.75rem; padding-block: var(--space-2); }.explorer__search { padding-block: var(--space-2); }.explorer__search :deep(.field) { gap: 0; }.explorer__search :deep(.field > span:first-child) { position: absolute; width: 1px; height: 1px; overflow: hidden; clip: rect(0 0 0 0); white-space: nowrap; }.explorer__filters { max-height: min(48vh, 23rem); padding-block: var(--space-3); }.explorer__filter-row { grid-template-columns: 1fr; }.map-context { bottom: 25rem; }.gym-card__select { grid-template-columns: 5.5rem minmax(0, 1fr); }.gym-card img { min-height: 8.5rem; }
  .map-tools { top: var(--space-3); right: var(--space-3); max-width: calc(100% - 5.5rem); }.map-tools :deep(.app-button) { min-width: 0; min-height: 2.75rem; padding-inline: var(--space-3); }.map-tools :deep(.app-button span) { overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }.map-context { left: var(--space-3); bottom: 25rem; max-width: calc(100% - var(--space-6)); }.location-status { grid-template-columns: 1.4rem minmax(0, 1fr); }.location-status button { grid-column: 2; justify-self: start; }.filters-toggle { grid-template-columns: auto minmax(0, 1fr) auto auto; }.filters-toggle__order { display: none; }.explorer__sort-row { grid-template-columns: 1fr; }.explorer__sort-row button { justify-self: start; }.gym-card__select { grid-template-columns: 5rem minmax(0, 1fr); }.gym-card__favorite { left: calc(5rem + var(--space-3)); }.gym-card img, .gym-card__visual { min-height: 8.5rem; }:deep(.leaflet-control-attribution) { display: none; }
  .explorer__panel--filters-open .explorer__filters { flex: 1; max-height: none; min-height: 0; }.explorer__panel--filters-open .explorer__panel-body { display: none; }
  .explorer__workspace--hero { height: 27rem; border-radius: var(--radius-card); }
  .explorer--hero .explorer__map-wrap { min-height: 27rem; }
  .hero-map-search { top: var(--space-3); left: var(--space-3); width: calc(100% - var(--space-6)); }
  .explorer--hero .map-tools { top: 4.25rem; right: var(--space-3); }
  .explorer--hero :deep(.leaflet-top.leaflet-left) { top: 3.5rem; }
  .explorer--hero .map-tools :deep(.app-button) { min-height: 2.75rem; }
  .explorer--hero .map-tools :deep(.icon-button:last-child) { display: none; }
  .hero-location-status { top: 7.5rem; left: var(--space-3); width: calc(100% - var(--space-6)); }
  .hero-location-status span { display: none; }
  .hero-gym-card { right: var(--space-3); bottom: var(--space-3); left: var(--space-3); grid-template-columns: minmax(0, 1fr); grid-template-rows: auto auto auto; gap: var(--space-2); padding: var(--space-3); }
  .hero-gym-card__visual { display: none; }
  .hero-gym-card__title { display: grid; grid-column: 1; grid-row: 1; gap: var(--space-2); }
  .hero-gym-card__title strong { white-space: normal; }
  .hero-gym-card__badges { justify-content: flex-start; }
  .hero-gym-card__facts { grid-column: 1 / -1; grid-row: 2; grid-template-columns: 1fr 1fr; }
  .hero-gym-card__facts span:last-child { display: none; }
  .hero-gym-card > a { min-height: 2.75rem; grid-column: 1; grid-row: 3; justify-self: start; }
  .hero-gym-card > a svg { display: none; }
  .hero-empty-state { right: var(--space-3); bottom: var(--space-3); left: var(--space-3); }
  .explorer--hero .map-context { bottom: 7.1rem; left: var(--space-3); }
}

@media (hover: hover) and (pointer: fine) {
  .hero-gym-card > a:hover { color: var(--text-primary); text-decoration: underline; text-underline-offset: .22em; }
}
</style>
