<script setup>
import { computed } from 'vue'

// points debe venir en orden cronológico (el más viejo primero)
const props = defineProps({ points: { type: Array, required: true } })

const width = 320
const height = 64
const padX = 4

const values = computed(() => props.points.map((point) => point.weight_kg).filter((value) => value != null))
const min = computed(() => Math.min(...values.value))
const max = computed(() => Math.max(...values.value))
const range = computed(() => Math.max(max.value - min.value, 1))

// 0 = arriba, 1 = abajo, con 10% de margen para no pegar el punto al borde.
// La usan tanto la línea del SVG como los puntitos en HTML, para que coincidan.
function verticalFraction(weight) {
  return (1 - (weight - min.value) / range.value) * 0.8 + 0.1
}

// coordenadas dentro del viewBox, solo para dibujar la línea y el área
const coords = computed(() => {
  const total = props.points.length
  return props.points.map((point, index) => {
    const x = padX + (index * (width - padX * 2)) / Math.max(total - 1, 1)
    const y = point.weight_kg == null ? null : verticalFraction(point.weight_kg) * height
    return { x, y, weight: point.weight_kg }
  })
})
const known = computed(() => coords.value.filter((point) => point.y != null))
const linePath = computed(() => known.value.map((point, index) => `${index === 0 ? 'M' : 'L'}${point.x},${point.y}`).join(' '))
const areaPath = computed(() => {
  if (known.value.length < 2) return ''
  const first = known.value[0]
  const last = known.value[known.value.length - 1]
  return `${linePath.value} L${last.x},${height} L${first.x},${height} Z`
})

// posición de cada punto en % del contenedor, para los puntitos en HTML:
// así quedan siempre redondos, sin importar cuánto se estire el SVG a lo ancho
const dots = computed(() => {
  const total = props.points.length
  return props.points.map((point, index) => {
    if (point.weight_kg == null) return null
    return {
      key: index,
      left: (index / Math.max(total - 1, 1)) * 100,
      top: verticalFraction(point.weight_kg) * 100,
      weight: point.weight_kg,
      isLast: index === total - 1,
    }
  }).filter(Boolean)
})
</script>

<template>
  <div v-if="values.length > 1" class="weight-sparkline" role="img" aria-label="Tendencia de peso a lo largo del tiempo">
    <svg :viewBox="`0 0 ${width} ${height}`" preserveAspectRatio="none" class="weight-sparkline__svg">
      <defs>
        <linearGradient id="weight-sparkline-fill" x1="0" y1="0" x2="0" y2="1">
          <stop offset="0%" stop-color="var(--accent)" stop-opacity=".35" />
          <stop offset="100%" stop-color="var(--accent)" stop-opacity="0" />
        </linearGradient>
      </defs>
      <path :d="areaPath" class="weight-sparkline__area" fill="url(#weight-sparkline-fill)" />
      <path :d="linePath" class="weight-sparkline__line" fill="none" stroke="var(--accent)" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" pathLength="1" />
    </svg>
    <span
      v-for="dot in dots"
      :key="dot.key"
      :class="['weight-sparkline__dot', { 'weight-sparkline__dot--last': dot.isLast }]"
      :style="{ left: `${dot.left}%`, top: `${dot.top}%`, '--dot-delay': `${dot.key * 70}ms` }"
    />
  </div>
</template>

<style scoped>
.weight-sparkline { position: relative; width: 100%; height: 3.5rem; }
.weight-sparkline__svg { display: block; width: 100%; height: 100%; overflow: visible; }
.weight-sparkline__area { opacity: 0; animation: sparkline-fade-in 500ms 500ms var(--ease-out) forwards; }
.weight-sparkline__line { stroke-dasharray: 1; stroke-dashoffset: 1; animation: sparkline-draw 900ms var(--ease-out) forwards; }
.weight-sparkline__dot { position: absolute; z-index: 1; width: .5rem; height: .5rem; margin: -.25rem; border: 2px solid var(--accent); border-radius: 50%; background: var(--surface-1); opacity: 0; transform: scale(.4); animation: sparkline-dot-in 380ms var(--dot-delay, 0ms) var(--ease-out) forwards; }
.weight-sparkline__dot--last { width: .68rem; height: .68rem; margin: -.34rem; background: var(--accent); }
.weight-sparkline__dot--last::after { content: ''; position: absolute; inset: -.34rem; border: 2px solid var(--accent); border-radius: 50%; animation: sparkline-pulse 1900ms 1200ms ease-out infinite; }

@keyframes sparkline-draw { to { stroke-dashoffset: 0; } }
@keyframes sparkline-fade-in { to { opacity: 1; } }
@keyframes sparkline-dot-in { to { opacity: 1; transform: scale(1); } }
@keyframes sparkline-pulse { 0% { opacity: .55; transform: scale(1); } 100% { opacity: 0; transform: scale(2.1); } }

@media (prefers-reduced-motion: reduce) {
  .weight-sparkline__area { opacity: 1; animation: none; }
  .weight-sparkline__line { stroke-dasharray: none; stroke-dashoffset: 0; animation: none; }
  .weight-sparkline__dot { opacity: 1; transform: scale(1); animation: none; }
  .weight-sparkline__dot--last::after { animation: none; display: none; }
}
</style>
