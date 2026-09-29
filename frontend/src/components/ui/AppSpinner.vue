<script setup>
defineProps({ size: { type: String, default: 'md' }, label: { type: String, default: 'Cargando' } })
</script>

<template>
  <span :class="['spinner', `spinner--${size}`]" role="status">
    <span class="visually-hidden">{{ label }}</span>
  </span>
</template>

<style scoped>
.spinner { width: 1.25rem; height: 1.25rem; border: 2px solid currentColor; border-right-color: transparent; border-radius: 50%; animation: spin 700ms linear infinite; }
.spinner--sm { width: 1rem; height: 1rem; }
.spinner--lg { width: 2rem; height: 2rem; border-width: 3px; }
@keyframes spin { to { transform: rotate(360deg); } }
</style>
