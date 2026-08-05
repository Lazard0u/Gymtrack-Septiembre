<script setup>
defineProps({ width: { type: String, default: '100%' }, height: { type: String, default: '1rem' }, rounded: Boolean })
</script>

<template><span class="skeleton" :class="{ 'skeleton--rounded': rounded }" :style="{ width, height }" aria-hidden="true" /></template>

<style scoped>
.skeleton { display: block; border-radius: var(--radius-control); background: linear-gradient(90deg, var(--surface-1) 25%, var(--surface-raised) 50%, var(--surface-1) 75%); background-size: 200% 100%; animation: shimmer 1.4s ease infinite; }
.skeleton--rounded { border-radius: var(--radius-pill); }
@keyframes shimmer { to { background-position: -200% 0; } }
</style>
