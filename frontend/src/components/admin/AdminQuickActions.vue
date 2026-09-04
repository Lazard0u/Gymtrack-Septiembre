<!--
  Componente administrativo AdminQuickActions. Presenta controles operativos y delega persistencia a stores o a la vista contenedora.
  En <script> se declaran imports, estado y funciones; <template> describe la interfaz y <style> limita su presentación.
-->
<script setup>
import { computed } from 'vue'
import {
  IconCalendarPlus, IconCreditCardPay, IconDownload, IconFileTypePdf, IconGift,
  IconIdBadge2, IconReceipt, IconUsersPlus,
} from '@tabler/icons-vue'
import AppBadge from '../ui/AppBadge.vue'
import { useAdminStore } from '../../stores/admin'

const admin = useAdminStore()
const actionDefinitions = [
  { label: 'Registrar socio', icon: IconUsersPlus, route: 'admin-members', query: { action: 'invite' }, permission: 'members.write', note: 'Invitación y alta operativa' },
  { label: 'Verificar carné', icon: IconIdBadge2, route: 'admin-member-card-verify', permission: 'members.read', note: 'Validación temporal para controlar ingresos' },
  { label: 'Crear clase', icon: IconCalendarPlus, route: 'admin-classes', permission: 'classes.write', note: 'Abrir agenda y crear una clase' },
  { label: 'Registrar pago manual', icon: IconCreditCardPay, route: 'admin-payments', query: { action: 'manual' }, permission: 'payments.manual', note: 'Confirmación administrativa auditada' },
  { label: 'Revisar pagos pendientes', icon: IconReceipt, route: 'admin-payments', query: { status: 'pendiente' }, permission: 'payments.read', note: 'Cobros pendientes y vencidos' },
  { label: 'Gestionar membresías', icon: IconIdBadge2, route: 'admin-memberships', permission: 'memberships.read', note: 'Consulta operativa real' },
  { label: 'Consultar reservas', icon: IconReceipt, route: 'admin-reservations', permission: 'reservations.read', note: 'Consulta operativa real' },
  { label: 'Crear promoción', icon: IconGift, route: 'admin-promotions', query: { action: 'create' }, permission: 'promotions.write', note: 'Definir audiencia, canales y fechas' },
  { label: 'Descargar Excel', icon: IconDownload, route: 'admin-reports', query: { module: 'payments', type: 'xlsx' }, permission: 'reports.export', note: 'Reporte real de transacciones' },
  { label: 'Descargar PDF', icon: IconFileTypePdf, route: 'admin-reports', query: { module: 'finance', type: 'pdf' }, permission: 'reports.export', note: 'Resumen financiero descargable' },
]
const actions = computed(() => actionDefinitions.map((action) => action.permission && !admin.hasPermission(action.permission)
  ? { ...action, route: undefined, query: undefined, disabled: true, note: 'Tu rol no tiene permiso para esta acción' }
  : action))
</script>

<template>
  <div class="quick-actions">
    <component :is="action.route ? 'RouterLink' : 'button'" v-for="action in actions" :key="action.label" :to="action.route ? { name: action.route, query: action.query } : undefined" :type="action.route ? undefined : 'button'" :disabled="action.disabled || undefined" :aria-disabled="action.disabled || undefined" class="quick-action">
      <component :is="action.icon" :size="20" aria-hidden="true" />
      <span><strong>{{ action.label }}</strong><small>{{ action.note }}</small></span>
      <AppBadge v-if="action.beta" tone="warning">Beta</AppBadge>
    </component>
  </div>
</template>

<style scoped>
.quick-actions { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: var(--space-3); }.quick-action { display: grid; min-height: 5.6rem; grid-template-columns: 1.5rem 1fr auto; align-items: flex-start; gap: var(--space-3); border: 1px solid var(--border-subtle); border-radius: var(--radius-card); padding: var(--space-4); background: var(--surface-1); color: var(--text-primary); font: inherit; text-align: left; text-decoration: none; }.quick-action:not(:disabled):hover { border-color: var(--border-strong); background: var(--surface-2); }.quick-action > svg { margin-top: .1rem; color: var(--info); }.quick-action span { display: grid; gap: var(--space-1); }.quick-action strong { font-size: .82rem; }.quick-action small { color: var(--text-tertiary); font-size: .72rem; line-height: 1.4; }.quick-action:disabled { background: var(--background-subtle); color: var(--text-tertiary); cursor: not-allowed; }.quick-action:disabled > svg { color: var(--text-tertiary); }
@media (max-width: 74rem) { .quick-actions { grid-template-columns: repeat(2, minmax(0, 1fr)); } }
@media (max-width: 35rem) { .quick-actions { grid-template-columns: 1fr; } }
</style>
