<!--
  Componente visual reutilizable AppIconButton. Props y slots forman su API; emite eventos al padre sin guardar datos de negocio.
  En <script> se declaran imports, estado y funciones; <template> describe la interfaz y <style> limita su presentación.
-->
<script setup>
defineProps({ label: { type: String, required: true }, disabled: Boolean, pressed: { type: Boolean, default: undefined } })
</script>

<template>
  <button class="icon-button" type="button" :aria-label="label" :aria-pressed="pressed" :disabled="disabled">
    <slot />
  </button>
</template>

<style scoped>
.icon-button { display: inline-grid; width: 2.75rem; height: 2.75rem; place-items: center; flex: 0 0 auto; border: 1px solid var(--border-subtle); border-radius: var(--radius-control); background: var(--surface-1); color: var(--text-secondary); cursor: pointer; transition: background-color var(--duration-fast), border-color var(--duration-fast), color var(--duration-fast); }
.icon-button:hover:not(:disabled) { background: var(--surface-2); border-color: var(--border-strong); color: var(--text-primary); }
</style>
