<script setup>
import { onBeforeUnmount, watch } from 'vue'
import { IconAlertCircle, IconArrowRight, IconClock, IconDatabaseOff } from '@tabler/icons-vue'
import AdminPageHeading from '../../components/admin/AdminPageHeading.vue'
import AdminQuickActions from '../../components/admin/AdminQuickActions.vue'
import AppAlert from '../../components/ui/AppAlert.vue'
import AppBadge from '../../components/ui/AppBadge.vue'
import AppButton from '../../components/ui/AppButton.vue'
import AppCard from '../../components/ui/AppCard.vue'
import AppErrorState from '../../components/ui/AppErrorState.vue'
import AppSkeleton from '../../components/ui/AppSkeleton.vue'
import { useAdminStore } from '../../stores/admin'
import { useAdminActivityStore } from '../../stores/adminResources'

const admin = useAdminStore()
const activity = useAdminActivityStore()

function load() {
  admin.loadSummary()
  if (admin.hasPermission('gym.configure')) activity.load({ page: 1, per_page: 10, sort: 'created_at', direction: 'desc' })
}
function actionLabel(action) {
  return ({
    'admin.context.selected': 'Contexto de gimnasio seleccionado',
    'admin.access.denied': 'Acceso administrativo denegado',
  })[action] || String(action).replaceAll('.', ' ')
}
function resultLabel(result) { return result === 'success' ? 'Completado' : result === 'denied' ? 'Denegado' : 'Fallido' }
function formatDate(value) { return new Intl.DateTimeFormat('es-UY', { dateStyle: 'short', timeStyle: 'short' }).format(new Date(String(value).replace(' ', 'T'))) }
watch(() => admin.version, load, { immediate: true })
onBeforeUnmount(() => activity.cancel())
</script>

<template>
  <section>
    <AdminPageHeading title="Resumen operativo" :description="`Estado real de ${admin.activeGym?.nombre || 'tu gimnasio'}, accesos frecuentes y alertas que requieren atención.`"><template #actions><AppBadge v-if="admin.isDemo" tone="info">Entorno de demostración</AppBadge></template></AdminPageHeading>
    <AppAlert v-if="admin.supportMode" tone="warning" title="Estás consultando como soporte"><p>Motivo registrado: {{ admin.supportReason }}</p></AppAlert>

    <div v-if="admin.summaryStatus === 'loading'" class="metric-grid" aria-label="Cargando indicadores"><AppSkeleton v-for="index in 5" :key="index" height="8rem" /></div>
    <AppErrorState v-else-if="admin.summaryStatus === 'error'" :description="admin.summaryError" @retry="admin.loadSummary" />
    <div v-else class="metric-grid">
      <AppCard v-for="widget in admin.summary.widgets" :key="widget.key" class="metric-card-admin" :class="{ 'metric-card-admin--fallback': widget.status !== 'ready' }">
        <div class="metric-card-admin__top"><span>{{ widget.label }}</span><IconDatabaseOff v-if="widget.status === 'unavailable'" :size="18" aria-hidden="true" /><IconAlertCircle v-else-if="widget.status === 'error'" :size="18" aria-hidden="true" /></div>
        <strong v-if="widget.status === 'ready'">{{ widget.value }}</strong><span v-else class="metric-card-admin__fallback">{{ widget.status === 'error' ? 'No se pudo consultar' : 'No disponible' }}</span>
        <p v-if="widget.detail">{{ widget.detail }}</p>
      </AppCard>
    </div>

    <section v-if="admin.summary.alerts?.length" class="admin-section" aria-labelledby="alerts-title"><div class="section-heading"><h2 id="alerts-title">Alertas operativas</h2></div><div class="alerts"><AppAlert v-for="alert in admin.summary.alerts" :key="alert.title" :tone="alert.tone" :title="alert.title"><p>{{ alert.detail }}</p><RouterLink :to="alert.route">Revisar <IconArrowRight :size="15" /></RouterLink></AppAlert></div></section>

    <section class="admin-section" aria-labelledby="quick-title"><div class="section-heading"><h2 id="quick-title">Operación diaria</h2><RouterLink :to="{ name: 'admin-operations' }">Ver gestión operativa <IconArrowRight :size="16" /></RouterLink></div><AdminQuickActions /></section>

    <section class="admin-section" aria-labelledby="activity-title"><div class="section-heading"><h2 id="activity-title">Actividad reciente</h2></div>
      <AppCard :padded="false" class="activity-card">
        <div v-if="!admin.hasPermission('gym.configure')" class="activity-empty"><IconClock :size="23" /><p>Tu rol no incluye acceso al historial de auditoría.</p></div>
        <div v-else-if="activity.status === 'loading'" class="activity-loading"><AppSkeleton v-for="index in 4" :key="index" height="3.5rem" /></div>
        <AppErrorState v-else-if="activity.status === 'error'" :description="activity.error" @retry="load" />
        <div v-else-if="activity.status === 'empty'" class="activity-empty"><IconClock :size="23" /><p>Todavía no hay actividad administrativa registrada para este gimnasio.</p></div>
        <ul v-else class="activity-list"><li v-for="event in activity.items" :key="event.id"><span class="activity-list__icon"><IconClock :size="16" /></span><div><strong>{{ actionLabel(event.accion) }}</strong><p>{{ event.usuario_email || 'Sistema' }} · {{ event.entidad }}</p></div><div class="activity-list__meta"><AppBadge :tone="event.resultado === 'success' ? 'success' : 'danger'">{{ resultLabel(event.resultado) }}</AppBadge><time>{{ formatDate(event.creado_en) }}</time></div></li></ul>
      </AppCard>
    </section>
  </section>
</template>

<style scoped>
.metric-grid { display: grid; grid-template-columns: repeat(5, minmax(0, 1fr)); gap: var(--space-3); margin-top: var(--space-5); }.metric-card-admin { display: grid; min-height: 8.5rem; align-content: space-between; gap: var(--space-3); padding: var(--space-4) !important; }.metric-card-admin__top { display: flex; align-items: flex-start; justify-content: space-between; gap: var(--space-2); color: var(--text-secondary); font-size: .72rem; }.metric-card-admin__top svg { flex: 0 0 auto; color: var(--warning); }.metric-card-admin strong { font-size: 1.9rem; letter-spacing: -.04em; }.metric-card-admin__fallback { color: var(--text-tertiary); font-size: 1rem; font-weight: 700; }.metric-card-admin p { margin: 0; color: var(--text-tertiary); font-size: .72rem; line-height: 1.45; }.admin-section { margin-top: var(--space-10); padding-top: var(--space-8); border-top: 1px solid var(--border-subtle); }.section-heading { display: flex; align-items: flex-end; justify-content: space-between; gap: var(--space-5); margin-bottom: var(--space-5); }.section-heading h2 { margin: 0; font-size: 1.35rem; }.section-heading > a { display: inline-flex; align-items: center; gap: var(--space-2); color: var(--status-info-strong); font-size: .76rem; font-weight: 700; text-decoration: none; }.alerts { display: grid; gap: var(--space-3); }.alerts :deep(.alert) { justify-content: flex-start; }.alerts a { display: inline-flex; align-items: center; gap: var(--space-1); margin-top: var(--space-2); color: inherit; font-weight: 720; }.activity-card { overflow: hidden; }.activity-loading { display: grid; gap: 1px; padding: var(--space-2); }.activity-empty { display: grid; min-height: 10rem; place-items: center; align-content: center; gap: var(--space-3); color: var(--text-tertiary); text-align: center; }.activity-empty p { margin: 0; }.activity-list { margin: 0; padding: 0; list-style: none; }.activity-list li { display: grid; grid-template-columns: 2rem 1fr auto; align-items: center; gap: var(--space-3); padding: var(--space-3) var(--space-4); border-bottom: 1px solid var(--border-subtle); }.activity-list li:last-child { border: 0; }.activity-list__icon { display: grid; width: 2rem; height: 2rem; place-items: center; border-radius: var(--radius-control); background: var(--surface-2); color: var(--text-tertiary); }.activity-list strong { font-size: .78rem; }.activity-list p { margin: .12rem 0 0; color: var(--text-tertiary); font-size: .7rem; }.activity-list__meta { display: flex; align-items: center; gap: var(--space-3); }.activity-list time { color: var(--text-tertiary); font-size: .7rem; }
@media (max-width: 79rem) { .metric-grid { grid-template-columns: repeat(3, minmax(0, 1fr)); } }
@media (max-width: 47.99rem) { .metric-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); }.metric-card-admin--fallback { grid-column: 1 / -1; }.section-heading { align-items: flex-start; flex-direction: column; }.activity-list li { grid-template-columns: 2rem 1fr; }.activity-list__meta { grid-column: 2; align-items: flex-start; flex-direction: column; gap: var(--space-1); } }
</style>
