<script setup>
import { IconAlertCircle } from '@tabler/icons-vue'
import AppButton from './AppButton.vue'
defineProps({ title: { type: String, default: 'No pudimos cargar este contenido' }, description: { type: String, default: 'Revisá tu conexión e intentá nuevamente.' } })
defineEmits(['retry'])
</script>

<template>
  <div class="state" role="alert"><IconAlertCircle :size="28" /><h3>{{ title }}</h3><p>{{ description }}</p><AppButton variant="secondary" @click="$emit('retry')">Reintentar</AppButton></div>
</template>

<style scoped>
.state { display: grid; min-height: 14rem; place-items: center; align-content: center; gap: var(--space-3); padding: var(--space-8); text-align: center; color: var(--danger); }
.state h3 { margin: 0; color: var(--text-primary); font-size: 1.1rem; }.state p { max-width: 30rem; margin: 0 0 var(--space-2); color: var(--text-secondary); font-size: .9rem; }
</style>
