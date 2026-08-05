<script setup>
defineProps({ tone: { type: String, default: 'neutral' } })
</script>

<template><span :class="['badge', `badge--${tone}`]"><slot /></span></template>

<style scoped>
.badge { display: inline-flex; min-height: 1.65rem; align-items: center; border: 1px solid var(--border-subtle); border-radius: var(--radius-pill); padding: 0 var(--space-3); background: var(--surface-2); color: var(--text-secondary); font-size: 0.72rem; font-weight: 720; }
.badge--success { border-color: var(--success-soft); background: var(--success-soft); color: var(--status-success-strong); }
.badge--warning { border-color: var(--warning-soft); background: var(--warning-soft); color: var(--status-warning-strong); }
.badge--info { border-color: var(--info-soft); background: var(--info-soft); color: var(--status-info-strong); }
.badge--danger { border-color: var(--danger-soft); background: var(--danger-soft); color: var(--status-danger-strong); }
</style>
