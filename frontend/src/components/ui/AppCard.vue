<script setup>
defineProps({ as: { type: String, default: 'div' }, padded: { type: Boolean, default: true }, interactive: Boolean })
</script>

<template>
  <component :is="as" :class="['app-card', { 'app-card--padded': padded, 'app-card--interactive': interactive }]">
    <slot />
  </component>
</template>

<style scoped>
.app-card { border: 1px solid var(--border-subtle); border-radius: var(--radius-card); background: var(--surface-1); box-shadow: var(--shadow-sm); }
.app-card--padded { padding: clamp(var(--space-5), 3vw, var(--space-8)); }
.app-card--interactive { transition: border-color var(--duration-fast), transform var(--duration-fast) var(--ease-out); }
.app-card--interactive:hover { border-color: var(--border-strong); transform: translateY(-2px); }
</style>
