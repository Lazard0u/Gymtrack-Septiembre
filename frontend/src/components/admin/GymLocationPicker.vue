<!--
  Componente administrativo GymLocationPicker. Presenta controles operativos y delega persistencia a stores o a la vista contenedora.
  En <script> se declaran imports, estado y funciones; <template> describe la interfaz y <style> limita su presentación.
-->
<script setup>
import { computed, nextTick, onBeforeUnmount, onMounted, ref, watch } from 'vue'
import { IconAlertCircle, IconCheck, IconMapPin, IconSearch } from '@tabler/icons-vue'
import 'leaflet/dist/leaflet.css'
import { api } from '../../services/api'
import AppButton from '../ui/AppButton.vue'
import AppSpinner from '../ui/AppSpinner.vue'

const props = defineProps({
  direccion: { type: String, default: '' },
  ciudad: { type: String, default: '' },
  pais: { type: String, default: '' },
  latitud: { type: [String, Number], default: '' },
  longitud: { type: [String, Number], default: '' },
})
const emit = defineEmits(['update:latitud', 'update:longitud'])

const mapElement = ref(null)
const mapStatus = ref('loading')
const searchStatus = ref('idle')
const results = ref([])
const message = ref('')
let leaflet = null
let map = null
let marker = null
let resizeObserver = null

const canSearch = computed(() => props.direccion.trim().length >= 5 && props.ciudad.trim().length >= 2 && props.pais.trim().length >= 2)
const position = computed(() => {
  const latitude = Number(props.latitud)
  const longitude = Number(props.longitud)
  return Number.isFinite(latitude) && latitude >= -90 && latitude <= 90 && Number.isFinite(longitude) && longitude >= -180 && longitude <= 180
    ? [latitude, longitude]
    : null
})

function formatted(value) {
  return Number(value).toFixed(7)
}

function updatePosition(latitude, longitude, center = true) {
  emit('update:latitud', formatted(latitude))
  emit('update:longitud', formatted(longitude))
  drawMarker([Number(latitude), Number(longitude)], center)
}

function drawMarker(point, center = false) {
  if (!map || !leaflet || !point) return
  if (!marker) {
    marker = leaflet.marker(point, {
      draggable: true,
      keyboard: true,
      title: 'Ubicación del gimnasio. Arrastrá para corregirla.',
      alt: 'Marcador de ubicación del gimnasio',
    }).addTo(map)
    marker.on('dragend', () => {
      const coordinates = marker.getLatLng()
      updatePosition(coordinates.lat, coordinates.lng, false)
      message.value = 'Posición corregida manualmente.'
    })
  } else marker.setLatLng(point)
  if (center) map.setView(point, Math.max(map.getZoom(), 16))
}

async function initialiseMap() {
  try {
    leaflet = await import('leaflet')
    await nextTick()
    if (!mapElement.value) return
    map = leaflet.map(mapElement.value, { center: position.value || [-32.8, -56], zoom: position.value ? 15 : 6 })
    leaflet.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
      maxZoom: 19,
      attribution: '© OpenStreetMap contributors',
    }).addTo(map)
    map.on('click', (event) => {
      updatePosition(event.latlng.lat, event.latlng.lng, false)
      message.value = 'Punto seleccionado manualmente.'
    })
    if (position.value) drawMarker(position.value)
    resizeObserver = new ResizeObserver(() => map?.invalidateSize({ pan: false }))
    resizeObserver.observe(mapElement.value)
    mapStatus.value = 'ready'
  } catch {
    mapStatus.value = 'error'
  }
}

async function geocode() {
  if (!canSearch.value || searchStatus.value === 'loading') return
  searchStatus.value = 'loading'
  results.value = []
  message.value = ''
  const response = await api.get('/admin/geocoding', { params: { direccion: props.direccion, ciudad: props.ciudad, pais: props.pais } })
  if (!response.ok || response.data?.error) {
    searchStatus.value = 'error'
    message.value = response.data?.mensaje || 'No pudimos ubicar la dirección. Elegí el punto en el mapa o ingresá las coordenadas.'
    return
  }
  results.value = response.data?.data?.items || []
  if (!results.value.length) {
    searchStatus.value = 'empty'
    message.value = 'No encontramos esa dirección. Ajustá los datos o elegí el punto directamente en el mapa.'
    return
  }
  searchStatus.value = 'ready'
  chooseResult(results.value[0])
}

function chooseResult(result) {
  updatePosition(result.latitud, result.longitud)
  message.value = 'Ubicación encontrada. Revisá el marcador antes de guardar.'
}

watch(position, (value) => {
  if (value) drawMarker(value)
})
onMounted(initialiseMap)
onBeforeUnmount(() => {
  resizeObserver?.disconnect()
  map?.remove()
})
</script>

<template>
  <div class="location-picker">
    <div class="location-picker__toolbar">
      <div>
        <strong>Vista previa de la ubicación</strong>
        <span>Buscá la dirección una vez y corregí el punto arrastrando el marcador o tocando el mapa.</span>
      </div>
      <AppButton type="button" variant="secondary" :disabled="!canSearch" :loading="searchStatus === 'loading'" @click="geocode">
        <template #icon><IconSearch :size="18" /></template>Ubicar dirección
      </AppButton>
    </div>

    <div class="location-picker__canvas">
      <div ref="mapElement" class="location-picker__map" role="region" aria-label="Vista previa editable de la ubicación del gimnasio" />
      <div v-if="mapStatus === 'loading'" class="location-picker__state" role="status"><AppSpinner /><span>Cargando vista previa…</span></div>
      <div v-else-if="mapStatus === 'error'" class="location-picker__state" role="alert"><IconAlertCircle :size="22" /><span>El mapa no está disponible. Podés continuar ingresando latitud y longitud.</span></div>
      <div v-if="position" class="location-picker__coordinates"><IconMapPin :size="16" /><span>{{ formatted(position[0]) }}, {{ formatted(position[1]) }}</span></div>
    </div>

    <div v-if="message" :class="['location-picker__message', { 'location-picker__message--error': ['error', 'empty'].includes(searchStatus) }]" role="status" aria-live="polite">
      <component :is="['error', 'empty'].includes(searchStatus) ? IconAlertCircle : IconCheck" :size="18" />
      <span>{{ message }}</span>
    </div>
    <div v-if="results.length > 1" class="location-picker__results" aria-label="Coincidencias de ubicación">
      <span>Otras coincidencias</span>
      <button v-for="result in results.slice(1)" :key="`${result.latitud}-${result.longitud}`" type="button" @click="chooseResult(result)">{{ result.nombre }}</button>
    </div>
    <small>Geocodificación bajo demanda. Datos © colaboradores de OpenStreetMap. No se realizan búsquedas automáticas.</small>
  </div>
</template>

<style scoped>
.location-picker { display: grid; gap: var(--space-3); grid-column: 1 / -1; min-width: 0; }
.location-picker__toolbar { display: flex; align-items: end; justify-content: space-between; gap: var(--space-4); }
.location-picker__toolbar > div { display: grid; gap: var(--space-1); }
.location-picker__toolbar strong { font-size: .84rem; }
.location-picker__toolbar span,.location-picker > small { color: var(--text-tertiary); font-size: .72rem; line-height: 1.45; }
.location-picker__canvas { position: relative; height: 21rem; overflow: hidden; border: 1px solid var(--border-subtle); border-radius: var(--radius-card); background: var(--surface-1); }
.location-picker__map { position: absolute; inset: 0; }
.location-picker__state { position: absolute; inset: 0; z-index: 3; display: flex; align-items: center; justify-content: center; gap: var(--space-3); padding: var(--space-5); background: var(--surface-1); color: var(--text-secondary); font-size: .8rem; text-align: center; }
.location-picker__coordinates { position: absolute; right: var(--space-3); bottom: var(--space-3); z-index: 2; display: flex; min-height: 2.5rem; align-items: center; gap: var(--space-2); border: 1px solid var(--border-strong); border-radius: var(--radius-pill); padding-inline: var(--space-3); background: var(--surface-glass); color: var(--text-primary); font-size: .72rem; font-variant-numeric: tabular-nums; backdrop-filter: blur(10px); }
.location-picker__message { display: flex; align-items: flex-start; gap: var(--space-2); color: var(--status-success-text); font-size: .76rem; }
.location-picker__message--error { color: var(--status-danger-text); }
.location-picker__message svg { flex: 0 0 auto; }
.location-picker__results { display: grid; gap: var(--space-1); border-top: 1px solid var(--border-subtle); padding-top: var(--space-3); }
.location-picker__results > span { color: var(--text-tertiary); font-size: .7rem; }
.location-picker__results button { min-height: 2.75rem; border: 0; border-radius: var(--radius-control); padding: var(--space-2) var(--space-3); background: transparent; color: var(--text-secondary); cursor: pointer; font: inherit; font-size: .75rem; text-align: left; }
.location-picker__results button:hover { background: var(--surface-1); color: var(--text-primary); }
:deep(.leaflet-control-zoom a) { background: var(--surface-1); color: var(--text-primary); }
:deep(.leaflet-control-attribution) { background: var(--surface-glass-soft); color: var(--text-secondary); }
:deep(.leaflet-control-attribution a) { color: var(--status-info-strong); }
@media (max-width: 47.99rem) { .location-picker__toolbar { align-items: stretch; flex-direction: column; }.location-picker__canvas { height: 18rem; } }
</style>
