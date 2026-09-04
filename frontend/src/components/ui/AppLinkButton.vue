<!--
  Componente visual reutilizable AppLinkButton. Props y slots forman su API; emite eventos al padre sin guardar datos de negocio.
  En <script> se declaran imports, estado y funciones; <template> describe la interfaz y <style> limita su presentación.
-->
<script setup>
defineProps({ to: { type: [String, Object], required: true }, variant: { type: String, default: 'primary' }, size: { type: String, default: 'md' }, block: Boolean })
</script>

<template>
  <RouterLink :to="to" :class="['link-button', `link-button--${variant}`, `link-button--${size}`, { 'link-button--block': block }]">
    <slot name="icon" /><span><slot /></span>
  </RouterLink>
</template>

<style scoped>
.link-button { display: inline-flex; min-height: 2.75rem; align-items: center; justify-content: center; gap: var(--space-2); border: 1px solid transparent; border-radius: var(--radius-control); padding-inline: var(--space-5); font-size: .9rem; font-weight: 720; line-height: 1; text-decoration: none; transition: background-color var(--duration-fast), border-color var(--duration-fast), color var(--duration-fast), transform var(--duration-fast) var(--ease-out); }
.link-button--primary { background: var(--accent); color: var(--text-on-accent); }.link-button--primary:hover { background: var(--accent-hover); transform: translateY(-1px); }.link-button--primary:active { background: var(--accent-pressed); transform: translateY(0); }
.link-button--secondary { border-color: var(--border-strong); background: var(--surface-2); color: var(--text-primary); }.link-button--secondary:hover { border-color: var(--text-tertiary); background: var(--surface-raised); }
.link-button--ghost { color: var(--text-secondary); }.link-button--ghost:hover { background: var(--surface-2); color: var(--text-primary); }
.link-button--lg { min-height: 3.25rem; padding-inline: var(--space-6); font-size: .95rem; }.link-button--sm { min-height: 2.25rem; padding-inline: var(--space-3); font-size: .8rem; }.link-button--block { width: 100%; }
</style>
