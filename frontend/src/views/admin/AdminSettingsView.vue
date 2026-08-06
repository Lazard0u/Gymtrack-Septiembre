<script setup>
import { IconBuilding, IconClock, IconMapPin, IconShieldCheck } from '@tabler/icons-vue'
import AdminPageHeading from '../../components/admin/AdminPageHeading.vue'
import AppAlert from '../../components/ui/AppAlert.vue'
import AppBadge from '../../components/ui/AppBadge.vue'
import AppCard from '../../components/ui/AppCard.vue'
import { useAdminStore } from '../../stores/admin'

const admin = useAdminStore()
</script>

<template><section><AdminPageHeading title="Configuración" description="Contexto y permisos efectivos del gimnasio activo. La edición completa se mantiene para la Fase 5." /><div class="settings-grid"><AppCard><IconBuilding :size="22" /><h2>{{ admin.activeGym?.nombre }}</h2><dl><div><dt>Estado</dt><dd><AppBadge :tone="admin.activeGym?.estado === 'publicado' ? 'success' : 'warning'">{{ admin.activeGym?.estado?.replaceAll('_', ' ') }}</AppBadge></dd></div><div><dt>Rol efectivo</dt><dd>{{ admin.effectiveRole }}</dd></div><div><dt>Identificador</dt><dd>{{ admin.activeGymId }}</dd></div></dl></AppCard><AppCard><IconMapPin :size="22" /><h2>Ubicación y horario</h2><dl><div><dt>Ciudad</dt><dd>{{ admin.activeGym?.ciudad }}, {{ admin.activeGym?.departamento }}</dd></div><div><dt>Zona horaria</dt><dd><IconClock :size="15" /> {{ admin.activeGym?.zona_horaria || 'Sin dato' }}</dd></div></dl></AppCard><AppCard class="permissions-card"><IconShieldCheck :size="22" /><h2>Permisos activos</h2><div class="permissions"><AppBadge v-for="permission in admin.permissions" :key="permission">{{ permission }}</AppBadge></div></AppCard></div><AppAlert class="settings-notice" tone="info" title="Edición protegida"><p>Esta vista no modifica el gimnasio. El formulario definitivo, sus validaciones y la auditoría de cambios se implementarán junto al CRUD de la Fase 5.</p></AppAlert></section></template>

<style scoped>.settings-grid { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: var(--space-4); }.settings-grid :deep(.app-card) { min-width: 0; }.settings-grid svg { color: var(--info); }.settings-grid h2 { margin: var(--space-5) 0; font-size: 1.15rem; }.settings-grid dl { display: grid; gap: var(--space-3); margin: 0; }.settings-grid dl div { display: flex; align-items: center; justify-content: space-between; gap: var(--space-4); border-top: 1px solid var(--border-subtle); padding-top: var(--space-3); }.settings-grid dt { color: var(--text-tertiary); font-size: .72rem; }.settings-grid dd { display: flex; align-items: center; gap: var(--space-2); margin: 0; color: var(--text-secondary); font-size: .78rem; overflow-wrap: anywhere; }.permissions-card { grid-column: 1 / -1; }.permissions { display: flex; flex-wrap: wrap; gap: var(--space-2); }.settings-notice { margin-top: var(--space-5); }
@media (max-width: 47.99rem) { .settings-grid { grid-template-columns: 1fr; }.permissions-card { grid-column: auto; }.settings-grid dl div { align-items: flex-start; flex-direction: column; gap: var(--space-1); } }</style>
