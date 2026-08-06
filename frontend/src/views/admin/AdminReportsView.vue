<script setup>
import { onBeforeUnmount, watch } from 'vue'
import AdminDataTable from '../../components/admin/AdminDataTable.vue'
import AdminPageHeading from '../../components/admin/AdminPageHeading.vue'
import AppAlert from '../../components/ui/AppAlert.vue'
import AppCard from '../../components/ui/AppCard.vue'
import { useAdminStore } from '../../stores/admin'
import { useAdminExportsStore } from '../../stores/adminResources'

const admin = useAdminStore()
const exportsStore = useAdminExportsStore()
const columns = [
  { key: 'modulo', label: 'Módulo', sortable: true }, { key: 'tipo', label: 'Formato' },
  { key: 'estado', label: 'Estado', format: 'status', sortable: true }, { key: 'usuario_email', label: 'Solicitado por' },
  { key: 'creado_en', label: 'Fecha', format: 'datetime', sortable: true },
]
function load() { exportsStore.load({ page: 1, per_page: 20, sort: 'created_at', direction: 'desc' }) }
watch(() => admin.version, load, { immediate: true })
onBeforeUnmount(() => exportsStore.cancel())
</script>

<template><section><AdminPageHeading title="Reportes" description="Historial real de exportaciones solicitadas para el gimnasio activo." /><AppAlert tone="info" title="Exportación preparada, ejecución pendiente"><p>La tabla de seguimiento y su alcance por gimnasio ya existen. La generación de Excel y PDF se habilitará en la Fase 7; por eso no hay botones de descarga falsos.</p></AppAlert><AppCard :padded="false" class="reports-card"><AdminDataTable :columns="columns" :items="exportsStore.items" :pagination="exportsStore.pagination" :status="exportsStore.status" :error="exportsStore.error" empty-title="Todavía no hay exportaciones" empty-description="El historial se completará cuando la generación de archivos esté habilitada en la Fase 7." :request-id="exportsStore.requestId" @retry="load" /></AppCard></section></template>

<style scoped>.reports-card { margin-top: var(--space-5); overflow: clip; }</style>
