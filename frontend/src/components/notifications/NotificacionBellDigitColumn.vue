<script setup>
import { computed, watch } from 'vue'
import { motion, useSpring, useTransform, useVelocity } from 'motion-v'

// how many digits to keep above and below, and how far behind the spring can get
const WINDOW = 3
const LAG = 2
// a taller window would show the next digit at rest, so the fade rides velocity instead
const ROLL_FADE = 34
const ROLL_VELOCITY = 9
const COLUMN_SPRING = { stiffness: 400, damping: 30, mass: 0.9 }

const digitOf = (value) => ((value % 10) + 10) % 10

const props = defineProps({
  value: { type: Number, required: true },
  reduced: { type: Boolean, required: true },
})

// this only moves the way the count moved, so the digits roll the right way
const position = useSpring(props.value, COLUMN_SPRING)
const y = useTransform(position, (p) => `${-p * 100}%`)
const velocity = useVelocity(position)
const mask = useTransform(velocity, (v) => {
  const fade = Math.min(ROLL_FADE, (Math.abs(v) / ROLL_VELOCITY) * ROLL_FADE)
  return `linear-gradient(to bottom, transparent 0%, #000 ${fade}%, #000 ${100 - fade}%, transparent 100%)`
})

const tiles = computed(() => Array.from({ length: WINDOW * 2 + 1 }, (_, i) => props.value - WINDOW + i))

watch(
  () => props.value,
  (value) => {
    const gap = value - position.get()
    // on a big jump, move it closer first so there are still digits to show
    if (Math.abs(gap) > LAG) position.jump(value - Math.sign(gap) * LAG)
    if (props.reduced) position.jump(value)
    else position.set(value)
  },
  { immediate: true },
)
</script>

<template>
  <motion.span
    class="digit-column"
    :style="{ maskImage: reduced ? undefined : mask, WebkitMaskImage: reduced ? undefined : mask }"
  >
    <motion.span class="digit-column__track" :style="{ y }">
      <span
        v-for="tile in tiles"
        :key="tile"
        class="digit-column__tile"
        :style="{ top: `${tile * 100}%` }"
      >{{ digitOf(tile) }}</span>
    </motion.span>
  </motion.span>
</template>

<style scoped>
.digit-column { position: relative; display: inline-block; width: 1ch; height: 1em; overflow: hidden; }
.digit-column__track { position: absolute; inset: 0; }
.digit-column__tile { position: absolute; inset-inline: 0; display: flex; justify-content: center; }
</style>
