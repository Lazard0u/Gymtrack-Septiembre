<script setup>
import { computed } from 'vue'
import { AnimatePresence, motion } from 'motion-v'
import NotificacionBellDigitColumn from './NotificacionBellDigitColumn.vue'

// all sizes are a fraction of the size prop
const BADGE = 0.38
const DOT = 0.22
const FONT = 0.21
const PAD = 0.09
// how far out the badge sits, 1 puts it right on the edge
const ORBIT = 0.9
const ENTER_SPRING = { type: 'spring', stiffness: 600, damping: 20 }
const FADE = { duration: 0.15 }

const props = defineProps({
  total: { type: Number, required: true },
  max: { type: Number, required: true },
  size: { type: Number, required: true },
  color: { type: String, required: true },
  dot: { type: Boolean, required: true },
  reduced: { type: Boolean, required: true },
})

const side = computed(() => props.size * (props.dot ? DOT : BADGE))
// puts the badge on the circle so it lines up at any size
const inset = computed(() => props.size / 2 - (ORBIT * props.size * Math.SQRT1_2) / 2 - side.value / 2)
const clamped = computed(() => props.total > props.max)
const places = computed(() => (clamped.value ? 0 : String(props.total).length))
const digitPlaces = computed(() => Array.from({ length: places.value }, (_, i) => places.value - 1 - i))
</script>

<template>
  <AnimatePresence>
    <motion.span
      v-if="total > 0"
      key="badge"
      class="count-badge"
      :class="`count-badge--${color}`"
      aria-hidden="true"
      :style="{
        top: `${inset}px`,
        right: `${inset}px`,
        height: `${side}px`,
        minWidth: `${side}px`,
        paddingInline: dot ? 0 : `${size * PAD}px`,
        fontSize: `${size * FONT}px`,
      }"
      :initial="{ scale: 0, opacity: 0 }"
      :animate="{ scale: 1, opacity: 1 }"
      :exit="{ scale: 0, opacity: 0 }"
      :transition="reduced ? FADE : ENTER_SPRING"
    >
      <span v-if="!dot" class="count-badge__digits">
        <template v-if="clamped">{{ max }}+</template>
        <template v-else>
          <NotificacionBellDigitColumn
            v-for="place in digitPlaces"
            :key="place"
            :value="Math.floor(total / 10 ** place)"
            :reduced="reduced"
          />
        </template>
      </span>
    </motion.span>
  </AnimatePresence>
</template>

<style scoped>
.count-badge { pointer-events: none; position: absolute; z-index: 10; display: grid; place-items: center; border-radius: var(--radius-pill); }
.count-badge--red { background: var(--danger); }
.count-badge--orange { background: var(--warning); }
.count-badge--green { background: var(--success); }
.count-badge--blue { background: var(--accent); }
.count-badge--violet { background: #af52de; }
.count-badge__digits { display: flex; font-weight: 780; line-height: 1; letter-spacing: -0.01em; color: var(--text-on-accent); font-variant-numeric: tabular-nums; }
</style>
