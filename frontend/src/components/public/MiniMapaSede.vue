<script setup>
import { onBeforeUnmount, onMounted, ref, watch } from 'vue'
import 'leaflet/dist/leaflet.css'

// Mapa chico de una sede. Leaflet se carga recién al montar para no pesar en el resto de la página.
const props = defineProps({
  latitud: { type: [Number, String], required: true },
  longitud: { type: [Number, String], required: true },
  nombre: { type: String, default: '' },
})
const element = ref(null)
let map = null; let marker = null
const point = () => [Number(props.latitud), Number(props.longitud)]

onMounted(async () => {
  const L = await import('leaflet')
  // En pantallas táctiles no se arrastra el mapa: así no atrapa el scroll de la página.
  map = L.map(element.value, { center: point(), zoom: 15, zoomControl: false, scrollWheelZoom: false, dragging: !L.Browser.mobile, attributionControl: false })
  L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', { maxZoom: 19 }).addTo(map)
  L.control.attribution({ prefix: false }).addAttribution('© OpenStreetMap').addTo(map)
  L.control.zoom({ position: 'bottomright' }).addTo(map)
  marker = L.marker(point(), { keyboard: false, icon: L.divIcon({ className: 'sede-pin', html: '<span></span>', iconSize: [28, 28], iconAnchor: [14, 14] }) }).addTo(map)
})
watch(() => [props.latitud, props.longitud], () => { if (map) { map.setView(point(), 15); marker.setLatLng(point()) } })
onBeforeUnmount(() => map?.remove())
</script>

<template>
  <div ref="element" class="mini-map" role="img" :aria-label="`Mapa con la ubicación de ${nombre}`"></div>
</template>

<style scoped>
.mini-map { height: 16rem; overflow: hidden; border: 1px solid var(--border-subtle); border-radius: var(--radius-card); background: var(--surface-2); }
.mini-map :deep(.sede-pin span) { display: block; width: 28px; height: 28px; border: 4px solid #fff; border-radius: 50%; background: var(--accent); box-shadow: 0 2px 10px rgba(0, 0, 0, .4); }
.mini-map :deep(.leaflet-control-zoom a) { width: 2.5rem; height: 2.5rem; line-height: 2.5rem; border-color: var(--border-subtle); background: var(--surface-1); color: var(--text-primary); }
.mini-map :deep(.leaflet-control-attribution) { background: var(--surface-glass); color: var(--text-tertiary); font-size: .65rem; }
.mini-map :deep(.leaflet-control-attribution a) { color: var(--status-info-strong); }
</style>
