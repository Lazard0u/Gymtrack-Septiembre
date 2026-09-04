<!--
  Vista administrativa AdminFinanceView. La ruta comprueba rol y permiso; la vista carga, presenta y modifica el recurso mediante su store.
  En <script> se declaran imports, estado y funciones; <template> describe la interfaz y <style> limita su presentación.
-->
<script setup>
import { computed, onBeforeUnmount, watch } from 'vue'
import { IconArrowDownRight, IconArrowUpRight, IconDownload } from '@tabler/icons-vue'
import AdminPageHeading from '../../components/admin/AdminPageHeading.vue'
import AppBadge from '../../components/ui/AppBadge.vue'
import AppButton from '../../components/ui/AppButton.vue'
import AppErrorState from '../../components/ui/AppErrorState.vue'
import AppLinkButton from '../../components/ui/AppLinkButton.vue'
import AppSkeleton from '../../components/ui/AppSkeleton.vue'
import { useAdminStore } from '../../stores/admin'
import { usePaymentsStore } from '../../stores/payments'

const admin = useAdminStore(); const payments = usePaymentsStore()
const data = computed(() => payments.finance || { statuses: {}, months: [], by_gym: [], by_membership: [], by_method: [] })
const maxMonth = computed(() => Math.max(1, ...data.value.months.map((month) => Number(month.total))))
const statusRows = computed(() => ['aprobado', 'pendiente', 'rechazado', 'vencido', 'reembolsado', 'cancelado']
  .filter((status) => status !== 'cancelado' || data.value.statuses?.cancelado)
  .map((status) => ({ status, count: data.value.statuses?.[status]?.count || 0, amount: data.value.statuses?.[status]?.amount || 0 })))
const changePositive = computed(() => Number(data.value.change_percent || 0) >= 0)
const revenueChartLabel = computed(() => `Ingresos aprobados de los últimos seis meses. ${data.value.months.map((month) => `${month.label}: ${money(month.total)}`).join('; ')}`)
function money(value) { return new Intl.NumberFormat('es-UY', { style: 'currency', currency: data.value.currency || 'UYU', maximumFractionDigits: 0 }).format(Number(value || 0)) }
function label(value) { return String(value).replaceAll('_', ' ').replace(/^./, (letter) => letter.toUpperCase()) }
function statusTone(status) { return status === 'aprobado' ? 'success' : status === 'pendiente' ? 'warning' : ['reembolsado', 'cancelado'].includes(status) ? 'neutral' : 'danger' }
function load() { payments.loadFinance() }
watch(() => admin.version, load, { immediate: true })
onBeforeUnmount(() => payments.reset())
</script>

<template>
  <section>
    <AdminPageHeading title="Finanzas" description="Ingresos confirmados, deuda y composición financiera con datos del gimnasio activo.">
      <template #actions><AppLinkButton :to="{ name: 'admin-reports', query: { module: 'finance' } }" variant="secondary"><template #icon><IconDownload :size="18" /></template>Exportar reporte</AppLinkButton></template>
    </AdminPageHeading>
    <div v-if="payments.financeStatus === 'loading'" class="finance-loading"><AppSkeleton height="9rem" /><AppSkeleton height="22rem" /><AppSkeleton height="16rem" /></div>
    <AppErrorState v-else-if="payments.financeStatus === 'error'" title="No pudimos cargar las finanzas" :description="payments.financeError" @retry="load" />
    <template v-else-if="payments.financeStatus === 'ready'">
      <section class="finance-lead" aria-labelledby="month-income">
        <div><span id="month-income">Ingresos de este mes</span><strong>{{ money(data.current_month) }}</strong><p>Únicamente pagos aprobados y confirmados.</p></div>
        <div class="finance-lead__comparison"><component :is="changePositive ? IconArrowUpRight : IconArrowDownRight" :size="22" /><span>{{ data.change_percent === null ? 'Sin período comparable' : `${Math.abs(data.change_percent)}% ${changePositive ? 'más' : 'menos'}` }}</span><small>Período anterior: {{ money(data.previous_month) }}</small></div>
        <div class="finance-lead__debt"><span>Deuda total</span><strong>{{ money(data.debt_total) }}</strong><small>Pendiente más vencida</small></div>
      </section>

      <section class="finance-section" aria-labelledby="trend-title"><div class="finance-section__heading"><div><h2 id="trend-title">Últimos seis meses</h2><p>Ingresos aprobados por fecha efectiva de pago.</p></div><AppLinkButton :to="{ name: 'admin-payments' }" variant="secondary" size="sm">Ver transacciones</AppLinkButton></div>
        <div class="revenue-chart" role="img" :aria-label="revenueChartLabel"><div v-for="month in data.months" :key="month.period" class="revenue-chart__column"><span>{{ money(month.total) }}</span><div><i :style="{ height: `${Math.max(month.total ? 8 : 2, (Number(month.total) / maxMonth) * 100)}%` }" /></div><strong>{{ month.label }}</strong></div></div>
      </section>

      <section class="finance-section" aria-labelledby="states-title"><div class="finance-section__heading"><div><h2 id="states-title">Estado de cobros</h2><p>Cantidad e importe acumulado por resultado.</p></div></div><div class="payment-states"><div v-for="row in statusRows" :key="row.status"><AppBadge :tone="statusTone(row.status)">{{ label(row.status) }}</AppBadge><strong>{{ row.count }}</strong><span>{{ money(row.amount) }}</span></div></div></section>

      <section class="breakdowns" aria-label="Composición de ingresos">
        <div><h2>Por membresía</h2><p v-if="!data.by_membership.length">Aún no hay ingresos aprobados.</p><ol v-else><li v-for="item in data.by_membership" :key="item.label"><span>{{ item.label }}</span><strong>{{ money(item.total) }}</strong><small>{{ item.count }} pagos</small></li></ol></div>
        <div><h2>Por método</h2><p v-if="!data.by_method.length">Aún no hay métodos registrados.</p><ol v-else><li v-for="item in data.by_method" :key="item.label"><span>{{ label(item.label) }}</span><strong>{{ money(item.total) }}</strong><small>{{ item.count }} pagos</small></li></ol></div>
        <div><h2>Por gimnasio</h2><p v-if="!data.by_gym.length">No hay información disponible.</p><ol v-else><li v-for="item in data.by_gym" :key="item.label"><span>{{ item.label }}</span><strong>{{ money(item.total) }}</strong><small>{{ item.count }} pagos</small></li></ol></div>
      </section>
    </template>
  </section>
</template>

<style scoped>
.finance-loading { display: grid; gap: var(--space-4); }.finance-lead { display: grid; grid-template-columns: minmax(16rem,1.6fr) minmax(13rem,1fr) minmax(12rem,.8fr); gap: 1px; overflow: hidden; border: 1px solid var(--border-subtle); border-radius: var(--radius-card); background: var(--border-subtle); box-shadow: var(--shadow-sm); }.finance-lead > div { display: grid; align-content: center; min-height: 10rem; gap: var(--space-2); padding: var(--space-6); background: var(--surface-1); }.finance-lead span { color: var(--text-secondary); font-size: .78rem; }.finance-lead strong { font-size: clamp(1.55rem,3vw,2.6rem); font-variant-numeric: tabular-nums; letter-spacing: -.03em; }.finance-lead p, .finance-lead small { margin: 0; color: var(--text-tertiary); font-size: .72rem; }.finance-lead__comparison svg { color: var(--success); }.finance-lead__comparison span { color: var(--text-primary); font-size: 1rem; font-weight: 720; }.finance-lead__debt strong { color: var(--status-warning-strong); }.finance-section { margin-top: var(--space-10); border-top: 1px solid var(--border-subtle); padding-top: var(--space-8); }.finance-section__heading { display: flex; align-items: flex-end; justify-content: space-between; gap: var(--space-5); margin-bottom: var(--space-6); }.finance-section h2, .breakdowns h2 { margin: 0; font-size: 1.15rem; }.finance-section__heading p, .breakdowns > div > p { margin: var(--space-1) 0 0; color: var(--text-tertiary); font-size: .78rem; }.revenue-chart { display: grid; height: 19rem; grid-template-columns: repeat(6,minmax(4rem,1fr)); gap: var(--space-4); overflow-x: auto; padding-top: var(--space-4); }.revenue-chart__column { display: grid; grid-template-rows: 1.4rem 1fr 1.5rem; gap: var(--space-2); text-align: center; }.revenue-chart__column > span { color: var(--text-secondary); font-size: .68rem; font-variant-numeric: tabular-nums; }.revenue-chart__column > div { display: flex; min-height: 11rem; align-items: flex-end; border-bottom: 1px solid var(--border-strong); background: var(--surface-1); }.revenue-chart__column i { width: 100%; min-height: 2px; background: var(--accent); }.revenue-chart__column strong { color: var(--text-tertiary); font-size: .7rem; }.payment-states { display: grid; grid-template-columns: repeat(5,minmax(0,1fr)); border: 1px solid var(--border-subtle); border-radius: var(--radius-card); overflow: hidden; }.payment-states > div { display: grid; gap: var(--space-3); padding: var(--space-5); border-right: 1px solid var(--border-subtle); background: var(--surface-1); }.payment-states > div:last-child { border: 0; }.payment-states strong { font-size: 1.6rem; font-variant-numeric: tabular-nums; }.payment-states span:last-child { color: var(--text-secondary); font-size: .76rem; }.breakdowns { display: grid; grid-template-columns: repeat(3,minmax(0,1fr)); gap: var(--space-6); margin-top: var(--space-10); }.breakdowns > div { min-width: 0; }.breakdowns ol { margin: var(--space-4) 0 0; padding: 0; list-style: none; }.breakdowns li { display: grid; grid-template-columns: minmax(0,1fr) auto; gap: var(--space-1) var(--space-3); border-bottom: 1px solid var(--border-subtle); padding: var(--space-3) 0; }.breakdowns li span { overflow: hidden; text-overflow: ellipsis; white-space: nowrap; color: var(--text-secondary); font-size: .8rem; }.breakdowns li strong { font-size: .8rem; font-variant-numeric: tabular-nums; }.breakdowns li small { grid-column: 1/-1; color: var(--text-tertiary); font-size: .68rem; }
@media (max-width: 74rem) { .finance-lead { grid-template-columns: 1fr 1fr; }.finance-lead > :first-child { grid-column: 1/-1; }.payment-states { grid-template-columns: repeat(3,1fr); }.payment-states > div { border-bottom: 1px solid var(--border-subtle); }.breakdowns { grid-template-columns: 1fr 1fr; } }
@media (max-width: 47.99rem) { .finance-lead { grid-template-columns: 1fr; }.finance-lead > :first-child { grid-column: auto; }.finance-lead > div { min-height: 8rem; }.finance-section__heading { align-items: flex-start; flex-direction: column; }.finance-section__heading > :last-child { width: 100%; }.revenue-chart { grid-template-columns: repeat(6,minmax(0,1fr)); gap: var(--space-1); overflow-x: visible; }.revenue-chart__column { min-width: 0; }.revenue-chart__column > span,.revenue-chart__column strong { font-size: .62rem; white-space: nowrap; }.payment-states { grid-template-columns: repeat(2,1fr); }.breakdowns { grid-template-columns: 1fr; } }
</style>
