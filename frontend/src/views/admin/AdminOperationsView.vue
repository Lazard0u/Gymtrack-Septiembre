<!--
  Vista administrativa AdminOperationsView. La ruta comprueba rol y permiso; la vista carga, presenta y modifica el recurso mediante su store.
  En <script> se declaran imports, estado y funciones; <template> describe la interfaz y <style> limita su presentación.
-->
<script setup>
import AdminPageHeading from '../../components/admin/AdminPageHeading.vue'
import AdminQuickActions from '../../components/admin/AdminQuickActions.vue'
import AppAlert from '../../components/ui/AppAlert.vue'
</script>

<template><section><AdminPageHeading title="Gestión operativa" description="Accesos directos a las tareas frecuentes del gimnasio activo, con disponibilidad explícita." /><AppAlert tone="info" title="Acciones verificables"><p>Los accesos abren módulos conectados a MySQL y respetan los permisos efectivos del gimnasio activo.</p></AppAlert><div class="operations-grid"><AdminQuickActions /></div></section></template>

<style scoped>.operations-grid { margin-top: var(--space-6); }</style>
