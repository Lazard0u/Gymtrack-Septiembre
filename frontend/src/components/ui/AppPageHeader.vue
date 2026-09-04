<!--
  Componente visual reutilizable AppPageHeader. Props y slots forman su API; emite eventos al padre sin guardar datos de negocio.
  En <script> se declaran imports, estado y funciones; <template> describe la interfaz y <style> limita su presentación.
-->
<script setup>
defineProps({ eyebrow: { type: String, default: '' }, title: { type: String, required: true }, description: { type: String, default: '' } })
</script>

<template>
  <header class="page-header"><div><span v-if="eyebrow" class="eyebrow">{{ eyebrow }}</span><h1>{{ title }}</h1><p v-if="description">{{ description }}</p></div><div v-if="$slots.actions" class="page-header__actions"><slot name="actions" /></div></header>
</template>

<style scoped>
.page-header { display: flex; align-items: flex-end; justify-content: space-between; gap: var(--space-8); padding-block: clamp(var(--space-12), 7vw, var(--space-20)); }
.page-header h1 { max-width: 13ch; margin-bottom: var(--space-4); font-size: clamp(2.4rem, 6vw, 4.5rem); }.page-header p { max-width: 42rem; margin: 0; color: var(--text-secondary); font-size: clamp(1rem, 2vw, 1.15rem); }.page-header__actions { display: flex; gap: var(--space-3); }
@media (max-width: 47.99rem) { .page-header { align-items: flex-start; flex-direction: column; }.page-header__actions { width: 100%; } }
</style>
