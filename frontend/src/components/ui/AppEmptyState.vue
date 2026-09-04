<!--
  Componente visual reutilizable AppEmptyState. Props y slots forman su API; emite eventos al padre sin guardar datos de negocio.
  En <script> se declaran imports, estado y funciones; <template> describe la interfaz y <style> limita su presentación.
-->
<script setup>
import { IconInbox } from '@tabler/icons-vue'
defineProps({ title: { type: String, required: true }, description: { type: String, required: true } })
</script>

<template>
  <div class="state" role="status"><div class="state__icon"><slot name="icon"><IconInbox :size="24" /></slot></div><h3>{{ title }}</h3><p>{{ description }}</p><div v-if="$slots.action" class="state__action"><slot name="action" /></div></div>
</template>

<style scoped>
.state { display: grid; min-height: 14rem; place-items: center; align-content: center; padding: var(--space-8); text-align: center; }
.state__icon { display: grid; width: 3rem; height: 3rem; place-items: center; margin-bottom: var(--space-4); border: 1px solid var(--border-subtle); border-radius: var(--radius-control); background: var(--surface-2); color: var(--text-secondary); }
.state h3 { margin-bottom: var(--space-2); font-size: 1.1rem; }.state p { max-width: 30rem; margin: 0; color: var(--text-secondary); font-size: .9rem; }.state__action { margin-top: var(--space-5); }
</style>
