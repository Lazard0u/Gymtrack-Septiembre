<script setup>
import { computed } from 'vue'

const props = defineProps({
  percentage: { type: Number, required: true },
  size: { type: Number, default: 112 },
  stroke: { type: Number, default: 11 },
})

const radius = computed(() => (props.size - props.stroke) / 2)
const circumference = computed(() => 2 * Math.PI * radius.value)
const clamped = computed(() => Math.min(100, Math.max(0, props.percentage || 0)))
const offset = computed(() => circumference.value * (1 - clamped.value / 100))
</script>

<template>
  <svg :width="size" :height="size" :viewBox="`0 0 ${size} ${size}`" class="progress-ring" role="img" :aria-label="`${clamped}% del objetivo`">
    <circle :cx="size / 2" :cy="size / 2" :r="radius" fill="none" stroke="var(--border-subtle)" :stroke-width="stroke" />
    <circle
      :cx="size / 2" :cy="size / 2" :r="radius" fill="none" stroke="var(--accent)" :stroke-width="stroke"
      stroke-linecap="round" :stroke-dasharray="circumference" :stroke-dashoffset="offset"
      :transform="`rotate(-90 ${size / 2} ${size / 2})`" class="progress-ring__value"
    />
  </svg>
</template>

<style scoped>
.progress-ring__value { transition: stroke-dashoffset var(--duration-normal) var(--ease-out); }
@media (prefers-reduced-motion: reduce) { .progress-ring__value { transition: none; } }
</style>
