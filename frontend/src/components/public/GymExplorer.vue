<script setup>
import { computed, nextTick, onBeforeUnmount, onMounted, ref } from 'vue'
import { IconChevronDown, IconChevronLeft, IconChevronRight, IconMapPin, IconSearch } from '@tabler/icons-vue'
import 'leaflet/dist/leaflet.css'
import AppEmptyState from '../ui/AppEmptyState.vue'
import AppErrorState from '../ui/AppErrorState.vue'
import AppIconButton from '../ui/AppIconButton.vue'
import AppSkeleton from '../ui/AppSkeleton.vue'

defineProps({ compactHeading: Boolean })

const mapElement = ref(null)
const mapStatus = ref('loading')
const panelCollapsed = ref(false)
const sheetPosition = ref('medium')
let map = null
let resizeObserver = null
let tileTimeout = null

const sheetLabel = computed(() => ({ closed: 'Abrir lista', medium: 'Expandir lista', full: 'Reducir lista' })[sheetPosition.value])

async function initialiseMap() {
  mapStatus.value = 'loading'
  try {
    const leaflet = await import('leaflet')
    await nextTick()
    if (!mapElement.value) return

    map?.remove()
    window.clearTimeout(tileTimeout)
    map = leaflet.map(mapElement.value, { center: [-34.9011, -56.1645], zoom: 12, zoomControl: true, attributionControl: true })
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
  } catch (error) {
    console.error('No se pudo iniciar el mapa público.', error)
    mapStatus.value = 'error'
  }
}

function togglePanel() {
  panelCollapsed.value = !panelCollapsed.value
  window.setTimeout(() => map?.invalidateSize({ pan: false }), 240)
}

function cycleSheet() {
  sheetPosition.value = sheetPosition.value === 'closed' ? 'medium' : sheetPosition.value === 'medium' ? 'full' : 'closed'
  window.setTimeout(() => map?.invalidateSize({ pan: false }), 240)
}

onMounted(initialiseMap)
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
      <p>El mapa y la lista viven juntos para que comparar ubicaciones sea simple. Los gimnasios aparecerán cuando el catálogo operativo esté conectado.</p>
    </div>
    <h2 v-else id="explorer-title" class="visually-hidden">Explorador de gimnasios</h2>

    <div :class="['explorer__workspace', { 'explorer__workspace--collapsed': panelCollapsed }]">
      <div class="explorer__map-wrap">
        <div ref="mapElement" class="explorer__map" aria-label="Mapa de gimnasios de GymTrack" />
        <div v-if="mapStatus === 'loading'" class="explorer__map-state" role="status" aria-label="Cargando mapa"><AppSkeleton width="100%" height="100%" /></div>
        <div v-else-if="mapStatus === 'error'" class="explorer__map-state"><AppErrorState title="El mapa no está disponible" description="No pudimos cargar OpenStreetMap en este momento." @retry="initialiseMap" /></div>
        <div class="map-context"><IconMapPin :size="17" aria-hidden="true" /><span>Vista inicial: Montevideo</span></div>
      </div>

      <aside :class="['explorer__panel', `explorer__panel--${sheetPosition}`, { 'explorer__panel--hidden': panelCollapsed }]" aria-label="Lista de gimnasios">
        <button class="sheet-handle" type="button" :aria-label="sheetLabel" @click="cycleSheet"><span /><IconChevronDown :size="18" aria-hidden="true" /></button>
        <header class="explorer__panel-header">
          <div><strong>Gimnasios</strong><span>Catálogo pendiente de conexión</span></div>
          <AppIconButton class="collapse-control" :label="panelCollapsed ? 'Mostrar lista' : 'Ocultar lista'" :pressed="!panelCollapsed" @click="togglePanel">
            <IconChevronRight v-if="!panelCollapsed" :size="20" /><IconChevronLeft v-else :size="20" />
          </AppIconButton>
        </header>
        <div class="explorer__panel-body">
          <AppEmptyState title="Todavía no hay gimnasios publicados" description="No mostramos ubicaciones, distancias ni precios inventados. Esta lista se alimentará desde MySQL cuando el módulo de gimnasios esté disponible.">
            <template #icon><IconSearch :size="24" /></template>
          </AppEmptyState>
        </div>
      </aside>

      <AppIconButton v-if="panelCollapsed" class="reopen-control" label="Mostrar lista de gimnasios" :pressed="false" @click="togglePanel"><IconChevronLeft :size="20" /></AppIconButton>
    </div>
  </section>
</template>

<style scoped>
.explorer__intro { display: grid; grid-template-columns: minmax(0, 1.25fr) minmax(18rem, .75fr); align-items: end; gap: var(--space-12); margin-bottom: var(--space-8); }
.explorer__intro h2 { max-width: 14ch; margin: 0; }.explorer__intro p { margin: 0; color: var(--text-secondary); }
.explorer__workspace { position: relative; display: grid; grid-template-columns: minmax(0, 1fr) minmax(20rem, 25rem); min-height: 36rem; overflow: hidden; border: 1px solid var(--border-subtle); border-radius: var(--radius-dialog); background: var(--surface-1); box-shadow: var(--shadow-md); transition: grid-template-columns var(--duration-normal) var(--ease-out); }
.explorer__workspace--collapsed { grid-template-columns: minmax(0, 1fr) 0; }
.explorer__map-wrap { position: relative; min-width: 0; min-height: 36rem; background: var(--surface-2); }.explorer__map { position: absolute; inset: 0; z-index: 0; }.explorer__map-state { position: absolute; inset: 0; z-index: 2; background: var(--surface-1); }.explorer__map-state :deep(.skeleton) { border-radius: 0; }
.map-context { position: absolute; left: var(--space-4); bottom: var(--space-4); z-index: 3; display: flex; align-items: center; gap: var(--space-2); border: 1px solid var(--border-subtle); border-radius: var(--radius-pill); padding: var(--space-2) var(--space-3); background: var(--surface-glass); color: var(--text-secondary); font-size: .72rem; backdrop-filter: blur(10px); }
.explorer__panel { position: relative; z-index: 4; display: flex; min-width: 0; flex-direction: column; border-left: 1px solid var(--border-subtle); background: var(--bg-subtle); transition: opacity var(--duration-fast), transform var(--duration-normal) var(--ease-out); }.explorer__panel--hidden { opacity: 0; pointer-events: none; transform: translateX(100%); }
.explorer__panel-header { display: flex; min-height: 4.75rem; align-items: center; justify-content: space-between; gap: var(--space-3); padding: var(--space-4); border-bottom: 1px solid var(--border-subtle); }.explorer__panel-header div { display: grid; }.explorer__panel-header strong { font-size: .95rem; }.explorer__panel-header span { color: var(--text-tertiary); font-size: .72rem; }.explorer__panel-body { flex: 1; overflow: auto; }
.sheet-handle { display: none; }.reopen-control { position: absolute; right: var(--space-4); top: var(--space-4); z-index: 5; }
:deep(.leaflet-control-zoom a) { background: var(--surface-1); color: var(--text-primary); border-color: var(--border-subtle); }:deep(.leaflet-control-zoom a:hover) { background: var(--surface-raised); }:deep(.leaflet-control-attribution) { background: var(--surface-glass-soft); color: var(--text-secondary); }:deep(.leaflet-control-attribution a) { color: var(--info); }

@media (max-width: 47.99rem) {
  .explorer__intro { grid-template-columns: 1fr; gap: var(--space-4); }.explorer__intro h2 { max-width: 17ch; }
  .explorer__workspace, .explorer__workspace--collapsed { display: block; min-height: 34rem; }.explorer__map-wrap { min-height: 34rem; }
  .explorer__panel { position: absolute; inset-inline: 0; bottom: 0; height: calc(100% - 1.5rem); border: 1px solid var(--border-strong); border-bottom: 0; border-radius: var(--radius-dialog) var(--radius-dialog) 0 0; box-shadow: var(--shadow-lg); transform: translateY(calc(100% - 15rem)); }.explorer__panel--hidden { opacity: 1; pointer-events: auto; transform: translateY(calc(100% - 4.5rem)); }.explorer__panel--closed { transform: translateY(calc(100% - 4.5rem)); }.explorer__panel--medium { transform: translateY(calc(100% - 15rem)); }.explorer__panel--full { transform: translateY(0); }
  .sheet-handle { display: flex; width: 100%; min-height: 2.25rem; align-items: center; justify-content: center; gap: var(--space-2); border: 0; border-bottom: 1px solid var(--border-subtle); background: transparent; color: var(--text-tertiary); cursor: pointer; }.sheet-handle span { width: 2.5rem; height: 3px; border-radius: var(--radius-pill); background: var(--border-strong); }
  .collapse-control, .reopen-control { display: none; }.explorer__panel-header { min-height: 3.75rem; padding-block: var(--space-2); }.map-context { bottom: 16rem; }
}
</style>
