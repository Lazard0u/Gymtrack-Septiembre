<script setup>
import AdminPageHeading from '../../components/admin/AdminPageHeading.vue'
import AdminQuickActions from '../../components/admin/AdminQuickActions.vue'
import AppAlert from '../../components/ui/AppAlert.vue'
</script>

<template><section><AdminPageHeading title="Gestión operativa" description="Accesos directos a las tareas frecuentes del gimnasio activo, con disponibilidad explícita por fase." /><AppAlert tone="info" title="Acciones verificables"><p>Los accesos habilitados abren módulos conectados a MySQL. Las operaciones todavía no implementadas permanecen desactivadas y explican su alcance.</p></AppAlert><div class="operations-grid"><AdminQuickActions /></div></section></template>

<style scoped>.operations-grid { margin-top: var(--space-6); }</style>
