<!--
  Componente visual reutilizable AppAlert. Props y slots forman su API; emite eventos al padre sin guardar datos de negocio.
  En <script> se declaran imports, estado y funciones; <template> describe la interfaz y <style> limita su presentación.
-->
<script setup>
import { IconAlertCircle, IconCircleCheck, IconInfoCircle, IconAlertTriangle } from '@tabler/icons-vue'

const icons = { info: IconInfoCircle, success: IconCircleCheck, warning: IconAlertTriangle, danger: IconAlertCircle }
defineProps({ tone: { type: String, default: 'info' }, title: { type: String, default: '' } })
</script>

<template>
  <div :class="['alert', `alert--${tone}`]" role="status">
    <component :is="icons[tone] || icons.info" :size="20" aria-hidden="true" />
    <div><strong v-if="title">{{ title }}</strong><slot /></div>
  </div>
</template>

<style scoped>
.alert { display: flex; gap: var(--space-3); align-items: flex-start; border: 1px solid; border-radius: var(--radius-control); padding: var(--space-4); font-size: .875rem; }
.alert strong { display: block; margin-bottom: var(--space-1); color: var(--text-primary); }
.alert--info { border-color: var(--info-soft); background: var(--info-soft); color: var(--status-info-text); }
.alert--success { border-color: var(--success-soft); background: var(--success-soft); color: var(--status-success-text); }
.alert--warning { border-color: var(--warning-soft); background: var(--warning-soft); color: var(--status-warning-text); }
.alert--danger { border-color: var(--danger-soft); background: var(--danger-soft); color: var(--status-danger-text); }
</style>
