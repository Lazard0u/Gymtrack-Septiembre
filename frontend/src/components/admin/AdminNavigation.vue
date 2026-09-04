<!--
  Componente administrativo AdminNavigation. Presenta controles operativos y delega persistencia a stores o a la vista contenedora.
  En <script> se declaran imports, estado y funciones; <template> describe la interfaz y <style> limita su presentación.
-->
<script setup>
import { computed } from 'vue'
import {
  IconActivity, IconBuildingCog, IconCalendarEvent, IconChartBar, IconCreditCard,
  IconFileAnalytics, IconGift, IconLayoutDashboard, IconReceipt, IconSettings,
  IconIdBadge2, IconShieldCheck, IconUsers, IconUsersGroup,
} from '@tabler/icons-vue'
import { useAdminStore } from '../../stores/admin'

defineEmits(['navigate'])
const admin = useAdminStore()

const items = computed(() => [
  { label: 'Resumen', route: 'admin-summary', icon: IconLayoutDashboard },
  { label: 'Gestión operativa', route: 'admin-operations', icon: IconActivity },
  { label: 'Socios', route: 'admin-members', icon: IconUsers, permission: 'members.read' },
  { label: 'Verificar carné', route: 'admin-member-card-verify', icon: IconIdBadge2, permission: 'members.read' },
  { label: 'Empleados', route: 'admin-staff', icon: IconUsersGroup, permission: 'staff.manage' },
  { label: 'Entrenadores', route: 'admin-trainers', icon: IconShieldCheck, permission: 'staff.manage' },
  { label: 'Clases', route: 'admin-classes', icon: IconCalendarEvent, permission: 'classes.read' },
  { label: 'Reservas', route: 'admin-reservations', icon: IconReceipt, permission: 'reservations.read' },
  { label: 'Membresías', route: 'admin-memberships', icon: IconBuildingCog, permission: 'memberships.read' },
  { label: 'Pagos', route: 'admin-payments', icon: IconCreditCard, permission: 'payments.read' },
  { label: 'Promociones', route: 'admin-promotions', icon: IconGift, permission: 'promotions.read' },
  { label: 'Finanzas', route: 'admin-finance', icon: IconChartBar, permission: 'finance.read' },
  { label: 'Reportes', route: 'admin-reports', icon: IconFileAnalytics, permission: 'reports.export' },
  { label: 'Configuración', route: 'admin-settings', icon: IconSettings, permission: 'gym.configure' },
].filter((item) => !item.permission || admin.hasPermission(item.permission)))
</script>

<template>
  <nav class="admin-nav" aria-label="Navegación administrativa">
    <RouterLink v-for="item in items" :key="item.route" :to="{ name: item.route }" @click="$emit('navigate')">
      <component :is="item.icon" :size="19" aria-hidden="true" />
      <span>{{ item.label }}</span>
    </RouterLink>
  </nav>
</template>

<style scoped>
.admin-nav { display: grid; gap: var(--space-1); }
.admin-nav a { display: grid; min-height: 2.6rem; grid-template-columns: 1.4rem 1fr auto; align-items: center; gap: var(--space-3); border-radius: var(--radius-control); padding: 0 var(--space-3); color: var(--text-secondary); font-size: .84rem; font-weight: 650; text-decoration: none; }
.admin-nav a:hover { background: var(--surface-2); color: var(--text-primary); }
.admin-nav a.router-link-active { background: var(--accent-soft); color: var(--status-info-strong); }
</style>
