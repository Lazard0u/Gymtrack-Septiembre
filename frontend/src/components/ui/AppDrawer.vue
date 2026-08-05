<script setup>
import { ref, toRef } from 'vue'
import { IconX } from '@tabler/icons-vue'
import { useFocusTrap } from '../../composables/useFocusTrap'
import AppIconButton from './AppIconButton.vue'

const props = defineProps({ open: Boolean, title: { type: String, required: true }, side: { type: String, default: 'right' } })
const emit = defineEmits(['close'])
const panel = ref(null)
const close = () => emit('close')
useFocusTrap(toRef(props, 'open'), panel, close)
</script>

<template>
  <Teleport to="body">
    <Transition name="drawer">
      <div v-if="open" class="drawer" role="presentation" @mousedown.self="close">
        <aside ref="panel" :class="['drawer__panel', `drawer__panel--${side}`]" role="dialog" aria-modal="true" :aria-labelledby="'drawer-title'" tabindex="-1">
          <header class="drawer__header"><h2 id="drawer-title">{{ title }}</h2><AppIconButton label="Cerrar menú" @click="close"><IconX :size="20" /></AppIconButton></header>
          <div class="drawer__body"><slot /></div>
          <footer v-if="$slots.footer" class="drawer__footer"><slot name="footer" /></footer>
        </aside>
      </div>
    </Transition>
  </Teleport>
</template>

<style scoped>
.drawer { position: fixed; inset: 0; z-index: var(--z-overlay); background: var(--overlay); }
.drawer__panel { position: absolute; inset-block: 0; width: min(90vw, 24rem); display: flex; flex-direction: column; background: var(--surface-1); box-shadow: var(--shadow-lg); }
.drawer__panel--right { right: 0; border-left: 1px solid var(--border-strong); }
.drawer__panel--left { left: 0; border-right: 1px solid var(--border-strong); }
.drawer__header { display: flex; min-height: var(--header-height); align-items: center; justify-content: space-between; gap: var(--space-4); padding: var(--space-4) var(--space-5); border-bottom: 1px solid var(--border-subtle); }
.drawer__header h2 { margin: 0; font-size: 1.1rem; }
.drawer__body { flex: 1; overflow: auto; padding: var(--space-5); }
.drawer__footer { padding: var(--space-5); border-top: 1px solid var(--border-subtle); }
.drawer-enter-active, .drawer-leave-active { transition: opacity var(--duration-normal) var(--ease-standard); }
.drawer-enter-active .drawer__panel, .drawer-leave-active .drawer__panel { transition: transform var(--duration-normal) var(--ease-out); }
.drawer-enter-from, .drawer-leave-to { opacity: 0; }
.drawer-enter-from .drawer__panel--right, .drawer-leave-to .drawer__panel--right { transform: translateX(100%); }
.drawer-enter-from .drawer__panel--left, .drawer-leave-to .drawer__panel--left { transform: translateX(-100%); }
</style>
