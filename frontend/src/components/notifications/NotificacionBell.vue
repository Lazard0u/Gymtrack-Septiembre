<script setup>
import { computed, onMounted, onUnmounted, ref, watch } from 'vue'
import { animate, useMotionValue, useReducedMotion, useSpring, useTransform, useVelocity } from 'motion-v'
import { useNotificacionesStore } from '../../stores/notificaciones'
import NotificacionBellIcon from './NotificacionBellIcon.vue'
import NotificacionBellCountBadge from './NotificacionBellCountBadge.vue'

// icon size as a fraction of the button size
const ICON = 0.46

// low damping so it keeps swinging for a bit
const SWING_SPRING = { type: 'spring', stiffness: 220, damping: 10, mass: 1, restDelta: 0.01 }
const CLAPPER_SPRING = { stiffness: 300, damping: 14, mass: 1 }

// degrees per second
const IMPULSE = 500
const MAX_VELOCITY = 900
const BURST = 5
const CLAPPER_SWEEP = 13
const CLAPPER_VELOCITY = 450

const clamp = (value, limit) => Math.max(-limit, Math.min(limit, value))

function useBellRing(total, reduced) {
  const swing = useMotionValue(0)
  const swingVelocity = useVelocity(swing)
  // clapper follows the bell's speed, so it lags behind on its own
  const clapperLag = useTransform(
    swingVelocity,
    [-CLAPPER_VELOCITY, 0, CLAPPER_VELOCITY],
    [CLAPPER_SWEEP, 0, -CLAPPER_SWEEP],
    { clamp: true },
  )
  const clapper = useSpring(clapperLag, CLAPPER_SPRING)
  const previous = ref(total.value)
  let ringing = null

  watch(total, (value) => {
    const delta = value - previous.value
    previous.value = value
    if (delta <= 0 || reduced.value) return

    const weight = 0.7 + (0.6 * Math.min(delta, BURST)) / BURST
    const moving = swing.getVelocity()
    // push it the way it is already moving so it swings harder
    const along = moving > 1 ? 1 : -1

    ringing = animate(swing, 0, {
      ...SWING_SPRING,
      velocity: clamp(moving + along * IMPULSE * weight, MAX_VELOCITY),
    })
  })

  onUnmounted(() => ringing?.stop())

  return { swing, clapper }
}

const props = defineProps({
  label: { type: String, default: 'Ver notificaciones' },
  size: { type: Number, default: 44 },
  color: { type: String, default: 'red' },
  variant: { type: String, default: 'count' },
  max: { type: Number, default: 99 },
})
defineEmits(['open'])

const notifications = useNotificacionesStore()
onMounted(() => notifications.load())

const reduced = useReducedMotion()
// use the sanitized total so a weird count cannot ring the bell
const total = computed(() => {
  const value = notifications.unread
  return Number.isFinite(value) ? Math.max(0, Math.floor(value)) : 0
})
const { swing, clapper } = useBellRing(total, reduced)
</script>

<template>
  <button
    type="button"
    class="notification-bell"
    :style="{ width: `${size}px`, height: `${size}px` }"
    :aria-label="label"
    @click="$emit('open')"
  >
    <span role="status" class="notification-bell__sr">
      {{ total > 0 ? `${total} notificaciones sin leer` : 'Sin notificaciones nuevas' }}
    </span>
    <NotificacionBellIcon :side="size * ICON" :swing="swing" :clapper="clapper" />
    <NotificacionBellCountBadge :total="total" :max="max" :size="size" :color="color" :dot="variant === 'dot'" :reduced="reduced" />
  </button>
</template>

<style scoped>
.notification-bell { position: relative; display: inline-grid; flex: 0 0 auto; place-items: center; border: none; border-radius: var(--radius-pill); background: var(--surface-2); color: var(--text-secondary); cursor: pointer; transition: background-color var(--duration-fast), color var(--duration-fast); }
.notification-bell:hover { background: var(--surface-raised); color: var(--text-primary); }
.notification-bell:active { transform: scale(0.9); }
.notification-bell__sr { position: absolute; width: 1px; height: 1px; overflow: hidden; clip: rect(0, 0, 0, 0); white-space: nowrap; }
@media (prefers-reduced-motion: reduce) {
  .notification-bell:active { transform: none; }
}
</style>
