<script setup>
import { getCurrentInstance, ref, toRef } from 'vue'
import { IconX } from '@tabler/icons-vue'
import { useFocusTrap } from '../../composables/useFocusTrap'
import AppIconButton from './AppIconButton.vue'

const props = defineProps({ open: Boolean, title: { type: String, required: true }, description: { type: String, default: '' } })
const emit = defineEmits(['close'])
const panel = ref(null)
const close = () => emit('close')
const instanceId = getCurrentInstance().uid
const titleId = `app-dialog-title-${instanceId}`
const descriptionId = `app-dialog-description-${instanceId}`
const { isTopLayer, stackIndex } = useFocusTrap(toRef(props, 'open'), panel, close)
</script>

<template>
  <Teleport to="body">
    <Transition name="dialog">
      <div v-if="open" class="dialog" role="presentation" :style="{ zIndex: `calc(var(--z-overlay) + ${stackIndex})` }" @mousedown.self="close">
        <section ref="panel" class="dialog__panel" role="dialog" :aria-modal="isTopLayer || undefined" :aria-hidden="!isTopLayer || undefined" :inert="!isTopLayer || undefined" :aria-labelledby="titleId" :aria-describedby="description ? descriptionId : undefined" tabindex="-1">
          <header class="dialog__header">
            <div><h2 :id="titleId">{{ title }}</h2><p v-if="description" :id="descriptionId">{{ description }}</p></div>
            <AppIconButton label="Cerrar diálogo" @click="close"><IconX :size="20" /></AppIconButton>
          </header>
          <div class="dialog__body"><slot /></div>
          <footer v-if="$slots.footer" class="dialog__footer"><slot name="footer" /></footer>
        </section>
      </div>
    </Transition>
  </Teleport>
</template>

<style scoped>
.dialog { position: fixed; inset: 0; z-index: var(--z-overlay); display: grid; place-items: center; padding: var(--space-4); background: var(--overlay); }
.dialog__panel { width: min(100%, 34rem); max-height: min(90dvh, 46rem); overflow: auto; border: 1px solid var(--border-strong); border-radius: var(--radius-dialog); background: var(--surface-1); box-shadow: var(--shadow-lg); }
.dialog__header { display: flex; align-items: flex-start; justify-content: space-between; gap: var(--space-4); padding: var(--space-6); border-bottom: 1px solid var(--border-subtle); }
.dialog__header h2 { margin-bottom: var(--space-2); font-size: 1.35rem; }
.dialog__header p { margin: 0; color: var(--text-secondary); font-size: .875rem; }
.dialog__body { padding: var(--space-6); }
.dialog__footer { display: flex; justify-content: flex-end; gap: var(--space-3); padding: var(--space-4) var(--space-6); border-top: 1px solid var(--border-subtle); }
.dialog-enter-active, .dialog-leave-active { transition: opacity var(--duration-normal) var(--ease-standard); }
.dialog-enter-active .dialog__panel, .dialog-leave-active .dialog__panel { transition: transform var(--duration-normal) var(--ease-out), opacity var(--duration-normal); }
.dialog-enter-from, .dialog-leave-to { opacity: 0; }
.dialog-enter-from .dialog__panel, .dialog-leave-to .dialog__panel { opacity: 0; transform: translateY(8px) scale(.985); }
</style>
