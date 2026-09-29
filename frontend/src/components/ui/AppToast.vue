<script setup>
import { IconX } from '@tabler/icons-vue'
import AppIconButton from './AppIconButton.vue'
defineProps({ open: Boolean, title: { type: String, required: true }, message: { type: String, default: '' }, tone: { type: String, default: 'info' } })
defineEmits(['close'])
</script>

<template>
  <Teleport to="body">
    <Transition name="toast">
      <div v-if="open" :class="['toast', `toast--${tone}`]" role="status" aria-live="polite">
        <div><strong>{{ title }}</strong><p v-if="message">{{ message }}</p></div>
        <AppIconButton label="Cerrar notificación" @click="$emit('close')"><IconX :size="18" /></AppIconButton>
      </div>
    </Transition>
  </Teleport>
</template>

<style scoped>
.toast { position: fixed; right: var(--space-4); bottom: var(--space-4); z-index: var(--z-toast); display: flex; width: min(calc(100% - 2rem), 24rem); align-items: flex-start; justify-content: space-between; gap: var(--space-3); border: 1px solid var(--border-strong); border-radius: var(--radius-card); padding: var(--space-4); background: var(--surface-raised); box-shadow: var(--shadow-lg); }
.toast::before { width: .5rem; height: .5rem; flex: 0 0 auto; margin-top: .4rem; border-radius: 50%; background: var(--info); content: ''; }
.toast--success::before { background: var(--success); }.toast--danger::before { background: var(--danger); }.toast--warning::before { background: var(--warning); }
.toast strong { font-size: .9rem; }.toast p { margin: var(--space-1) 0 0; color: var(--text-secondary); font-size: .8rem; }
.toast-enter-active, .toast-leave-active { transition: transform var(--duration-normal) var(--ease-out), opacity var(--duration-normal); }
.toast-enter-from, .toast-leave-to { opacity: 0; transform: translateY(8px); }
</style>
