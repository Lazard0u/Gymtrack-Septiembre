<!--
  Componente administrativo AdminPageHeading. Presenta controles operativos y delega persistencia a stores o a la vista contenedora.
  En <script> se declaran imports, estado y funciones; <template> describe la interfaz y <style> limita su presentación.
-->
<script setup>
defineProps({ title: { type: String, required: true }, description: { type: String, default: '' } })
</script>

<template>
  <header class="admin-page-heading"><div><h1>{{ title }}</h1><p v-if="description">{{ description }}</p></div><div v-if="$slots.actions" class="admin-page-heading__actions"><slot name="actions" /></div></header>
</template>

<style scoped>
.admin-page-heading { display: flex; align-items: flex-end; justify-content: space-between; gap: var(--space-6); margin-bottom: var(--space-8); }.admin-page-heading h1 { margin-bottom: var(--space-2); font-size: clamp(1.8rem, 4vw, 2.8rem); }.admin-page-heading p { max-width: 48rem; margin: 0; color: var(--text-secondary); font-size: .9rem; }.admin-page-heading__actions { display: flex; flex-wrap: wrap; justify-content: flex-end; gap: var(--space-2); }
@media (max-width: 47.99rem) { .admin-page-heading { align-items: flex-start; flex-direction: column; }.admin-page-heading__actions { width: 100%; justify-content: flex-start; } }
</style>
