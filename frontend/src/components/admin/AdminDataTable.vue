<script setup>
import { IconArrowDown, IconArrowUp, IconChevronLeft, IconChevronRight } from '@tabler/icons-vue'
import AppBadge from '../ui/AppBadge.vue'
import AppButton from '../ui/AppButton.vue'
import AppEmptyState from '../ui/AppEmptyState.vue'
import AppErrorState from '../ui/AppErrorState.vue'
import AppSkeleton from '../ui/AppSkeleton.vue'

const props = defineProps({ columns: { type: Array, required: true }, items: { type: Array, default: () => [] }, pagination: { type: Object, required: true }, status: { type: String, default: 'idle' }, error: { type: String, default: '' }, sort: { type: String, default: '' }, direction: { type: String, default: 'desc' }, emptyTitle: { type: String, default: 'No hay registros' }, emptyDescription: { type: String, default: 'No encontramos resultados con estos filtros.' }, requestId: { type: String, default: '' }, rowActionLabel: { type: String, default: '' } })
const emit = defineEmits(['sort', 'page', 'retry', 'row'])

function tone(value) {
  const normalized = String(value ?? '').toLowerCase()
  if (['activo','activa','confirmada','asistio','publicado','registrado','aprobado','completado','success'].includes(normalized)) return 'success'
  if (['vencida','vencido','rechazado','suspendida','cancelada','fallido','failed','denied'].includes(normalized)) return 'danger'
  if (['pendiente','temporalmente_cerrado','procesando'].includes(normalized)) return 'warning'
  return 'neutral'
}
function label(value) { return String(value ?? 'Sin dato').replaceAll('_', ' ') }
function valueOf(item, column) {
  const value = item[column.key]
  if (column.format === 'money') return new Intl.NumberFormat('es-UY', { style: 'currency', currency: item[column.currencyKey] || column.currency || 'UYU' }).format(Number(value || 0))
  if (column.format === 'date' && value) return new Intl.DateTimeFormat('es-UY', { dateStyle: 'medium' }).format(new Date(`${String(value).slice(0, 10)}T12:00:00`))
  if (column.format === 'datetime' && value) return new Intl.DateTimeFormat('es-UY', { dateStyle: 'medium', timeStyle: 'short' }).format(new Date(String(value).replace(' ', 'T')))
  if (column.format === 'boolean') return Number(value) === 1 || value === true ? 'Sí' : 'No'
  if (column.format === 'present') return value ? 'Sí' : 'No'
  if (column.format === 'time' && value) return String(value).slice(0, 5)
  return value ?? 'Sin dato'
}
</script>

<template>
  <div class="data-region">
    <div v-if="status === 'loading'" class="table-loading" role="status" aria-label="Cargando registros"><AppSkeleton v-for="index in 7" :key="index" height="3.25rem" /></div>
    <AppErrorState v-else-if="status === 'error'" :description="error" @retry="emit('retry')" />
    <AppEmptyState v-else-if="status === 'empty'" :title="emptyTitle" :description="emptyDescription" />
    <template v-else>
      <div class="table-scroll">
        <table>
          <thead><tr><th v-for="column in columns" :key="column.key" :aria-sort="column.sortable && sort === column.key ? (direction === 'asc' ? 'ascending' : 'descending') : undefined"><button v-if="column.sortable" type="button" @click="emit('sort', column.key)">{{ column.label }}<component :is="direction === 'asc' ? IconArrowUp : IconArrowDown" v-if="sort === column.key" :size="14" /></button><span v-else>{{ column.label }}</span></th><th v-if="rowActionLabel" class="action-heading">Acción</th></tr></thead>
          <tbody><tr v-for="item in items" :key="item.id"><td v-for="column in columns" :key="column.key" :data-label="column.label"><AppBadge v-if="column.format === 'status'" :tone="tone(item[column.key])">{{ label(item[column.key]) }}</AppBadge><span v-else>{{ valueOf(item, column) }}</span></td><td v-if="rowActionLabel" data-label="Acción"><AppButton variant="ghost" size="sm" @click="emit('row', item)">{{ rowActionLabel }}</AppButton></td></tr></tbody>
        </table>
      </div>
      <footer class="pagination"><p>{{ pagination.total }} registro(s) · Página {{ pagination.page }} de {{ pagination.total_pages }}</p><div><AppButton variant="ghost" size="sm" :disabled="pagination.page <= 1" @click="emit('page', pagination.page - 1)"><template #icon><IconChevronLeft :size="16" /></template>Anterior</AppButton><AppButton variant="ghost" size="sm" :disabled="pagination.page >= pagination.total_pages" @click="emit('page', pagination.page + 1)">Siguiente<template #icon><IconChevronRight :size="16" /></template></AppButton></div></footer>
    </template>
    <p v-if="requestId && status === 'error'" class="request-id">Referencia: {{ requestId }}</p>
  </div>
</template>

<style scoped>
.data-region { background: var(--surface-1); }.table-loading { display: grid; min-height: 16rem; gap: 1px; padding: var(--space-2); }.table-scroll { overflow-x: auto; }table { width: 100%; border-collapse: collapse; font-size: .8rem; }th, td { border-bottom: 1px solid var(--border-subtle); padding: .85rem var(--space-4); text-align: left; vertical-align: middle; }th { position: sticky; top: 0; z-index: 1; background: var(--surface-2); color: var(--text-tertiary); font-size: .69rem; font-weight: 760; letter-spacing: .035em; text-transform: uppercase; }th button { display: inline-flex; align-items: center; gap: var(--space-1); border: 0; padding: 0; background: transparent; color: inherit; cursor: pointer; font: inherit; text-transform: inherit; }td { color: var(--text-secondary); }td:first-child { color: var(--text-primary); font-weight: 650; }tbody tr:hover { background: rgba(255,255,255,.018); }.pagination { display: flex; align-items: center; justify-content: space-between; gap: var(--space-4); padding: var(--space-3) var(--space-4); }.pagination p { margin: 0; color: var(--text-tertiary); font-size: .72rem; }.pagination > div { display: flex; gap: var(--space-1); }.request-id { margin: 0; padding: var(--space-3); color: var(--text-tertiary); font-family: monospace; font-size: .68rem; text-align: center; }
.action-heading { width: 1%; white-space: nowrap; }
@media (max-width: 47.99rem) { .table-scroll { overflow: visible; }table, thead, tbody, tr, th, td { display: block; }thead { position: absolute; width: 1px; height: 1px; overflow: hidden; clip: rect(0,0,0,0); }tbody { display: grid; gap: var(--space-3); padding: var(--space-3); }tbody tr { border: 1px solid var(--border-subtle); border-radius: var(--radius-card); padding: var(--space-2) var(--space-3); background: var(--surface-2); }td { display: grid; grid-template-columns: minmax(7rem, .8fr) 1fr; gap: var(--space-3); border: 0; padding: .45rem 0; overflow-wrap: anywhere; }td::before { content: attr(data-label); color: var(--text-tertiary); font-size: .66rem; font-weight: 740; letter-spacing: .025em; text-transform: uppercase; }.pagination { align-items: stretch; flex-direction: column; }.pagination > div { display: grid; grid-template-columns: 1fr 1fr; } }
</style>
