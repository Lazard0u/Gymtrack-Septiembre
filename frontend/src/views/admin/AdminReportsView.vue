<!--
  Vista administrativa AdminReportsView. La ruta comprueba rol y permiso; la vista carga, presenta y modifica el recurso mediante su store.
  En <script> se declaran imports, estado y funciones; <template> describe la interfaz y <style> limita su presentación.
-->
<script setup>
import { computed, onBeforeUnmount, reactive, ref, watch } from 'vue'
import { useRoute } from 'vue-router'
import { IconDownload, IconFileSpreadsheet, IconFileTypePdf } from '@tabler/icons-vue'
import AdminDataTable from '../../components/admin/AdminDataTable.vue'
import AdminPageHeading from '../../components/admin/AdminPageHeading.vue'
import AppAlert from '../../components/ui/AppAlert.vue'
import AppButton from '../../components/ui/AppButton.vue'
import AppCard from '../../components/ui/AppCard.vue'
import AppInput from '../../components/ui/AppInput.vue'
import AppSelect from '../../components/ui/AppSelect.vue'
import { useAdminStore } from '../../stores/admin'
import { usePaymentsStore } from '../../stores/payments'

const admin = useAdminStore(); const payments = usePaymentsStore(); const route = useRoute(); const notice = ref(null)
const form = reactive({ module: String(route.query.module || 'payments'), status: '', from: '', to: '' })
const columns = [{ key: 'modulo', label: 'Módulo', sortable: true }, { key: 'tipo', label: 'Formato' }, { key: 'estado', label: 'Estado', format: 'status', sortable: true }, { key: 'archivo_nombre', label: 'Archivo' }, { key: 'usuario_email', label: 'Solicitado por' }, { key: 'creado_en', label: 'Fecha', format: 'datetime', sortable: true }]
const moduleOptions = [{ value: 'payments', label: 'Transacciones' }, { value: 'finance', label: 'Finanzas de seis meses' }]
const statusOptions = [{ value: '', label: 'Todos los estados' }, ...['aprobado', 'pendiente', 'rechazado', 'vencido', 'reembolsado'].map((value) => ({ value, label: value[0].toUpperCase() + value.slice(1) }))]
const selected = ref(null)
const reportItems = computed(() => payments.exports.map((item) => ({ ...item, estado: item.estado === 'completado' && Number(item.descargable) !== 1 ? 'vencido' : item.estado })))
const canDownload = computed(() => selected.value?.estado === 'completado' && Number(selected.value?.descargable) === 1)
function filters() { return { status: form.status, from: form.from, to: form.to } }
async function generate(type) { try { const result = await payments.generateReport(type, form.module, filters()); notice.value = { tone: 'success', title: 'Reporte generado', text: `${result.filename} está listo para descargar.` }; selected.value = payments.exports.find((item) => Number(item.id) === Number(result.id)) || null } catch (error) { notice.value = { tone: 'danger', title: 'No se pudo generar el reporte', text: error.message } } }
function download(item) { if (!item || Number(item.descargable) !== 1) { notice.value = { tone: 'warning', title: 'El reporte ya no está disponible', text: 'Generá una nueva exportación para descargar datos actuales.' }; return } window.location.assign(`/api/admin/exports/${item.id}/download`) }
function load() { payments.loadExports() }
watch(() => admin.version, load, { immediate: true })
onBeforeUnmount(() => payments.reset())
</script>

<template>
  <section>
    <AdminPageHeading title="Reportes" description="Generá archivos Excel y PDF con filtros y alcance del gimnasio activo." />
    <AppAlert v-if="notice" :tone="notice.tone" :title="notice.title" class="reports-notice"><p>{{ notice.text }}</p></AppAlert>
    <AppCard class="report-builder">
      <div><h2>Preparar un reporte</h2><p>El archivo refleja datos reales al momento de generarlo y vence luego de 24 horas.</p></div>
      <div class="report-builder__fields"><AppSelect v-model="form.module" label="Contenido" :options="moduleOptions" /><AppSelect v-if="form.module === 'payments'" v-model="form.status" label="Estado" :options="statusOptions" /><AppInput v-if="form.module === 'payments'" v-model="form.from" type="date" label="Desde" /><AppInput v-if="form.module === 'payments'" v-model="form.to" type="date" label="Hasta" /></div>
      <div class="report-builder__actions"><AppButton :loading="payments.working === 'export-xlsx'" @click="generate('xlsx')"><template #icon><IconFileSpreadsheet :size="18" /></template>Generar Excel</AppButton><AppButton variant="secondary" :loading="payments.working === 'export-pdf'" @click="generate('pdf')"><template #icon><IconFileTypePdf :size="18" /></template>Generar PDF</AppButton></div>
    </AppCard>
    <section class="history" aria-labelledby="history-title"><div class="history__heading"><div><h2 id="history-title">Historial de exportaciones</h2><p>Seleccioná una fila completada para descargarla nuevamente.</p></div><AppButton v-if="canDownload" variant="secondary" size="sm" @click="download(selected)"><template #icon><IconDownload :size="17" /></template>Descargar seleccionado</AppButton></div><AppCard :padded="false" class="reports-card"><AdminDataTable :columns="columns" :items="reportItems" :pagination="payments.exportPagination" :status="payments.exportsStatus" :error="payments.exportsError" empty-title="Todavía no hay exportaciones" empty-description="Generá el primer reporte con los controles superiores." row-action-label="Seleccionar" @retry="load" @row="selected = $event" /></AppCard></section>
  </section>
</template>

<style scoped>
.reports-notice { margin-bottom: var(--space-5); }.reports-notice p { margin: 0; }.report-builder { display: grid; grid-template-columns: minmax(13rem,.8fr) minmax(24rem,2fr) auto; align-items: end; gap: var(--space-6); }.report-builder h2, .history h2 { margin: 0 0 var(--space-2); font-size: 1.15rem; }.report-builder p, .history p { margin: 0; color: var(--text-tertiary); font-size: .78rem; }.report-builder__fields { display: grid; grid-template-columns: repeat(4,minmax(8rem,1fr)); gap: var(--space-3); }.report-builder__actions { display: flex; gap: var(--space-2); }.history { margin-top: var(--space-10); padding-top: var(--space-8); border-top: 1px solid var(--border-subtle); }.history__heading { display: flex; align-items: flex-end; justify-content: space-between; gap: var(--space-5); margin-bottom: var(--space-5); }.reports-card { overflow: clip; }
@media (max-width: 100rem) { .report-builder { grid-template-columns: 1fr; align-items: start; }.report-builder__actions { justify-content: flex-start; } }
@media (max-width: 47.99rem) { .report-builder__fields { grid-template-columns: 1fr; }.report-builder__actions { display: grid; grid-template-columns: 1fr 1fr; }.history__heading { align-items: flex-start; flex-direction: column; }.history__heading > :last-child { width: 100%; } }
</style>
