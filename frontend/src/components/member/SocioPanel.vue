<script setup>
import { computed, onMounted, watch } from 'vue'
import { IconCalendarEvent, IconChevronRight, IconCreditCard, IconIdBadge2, IconReceipt, IconTrendingUp } from '@tabler/icons-vue'
import { useAuthStore } from '../../stores/auth'
import { useSocioStore } from '../../stores/socio'
import AppBadge from '../ui/AppBadge.vue'
import AppEmptyState from '../ui/AppEmptyState.vue'
import AppErrorState from '../ui/AppErrorState.vue'
import AppLinkButton from '../ui/AppLinkButton.vue'
import AppSkeleton from '../ui/AppSkeleton.vue'

const auth = useAuthStore()
const member = useSocioStore()
const hasContext = computed(() => Boolean(auth.user?.active_gym_id))
const data = computed(() => member.summary || {})
const progress = computed(() => data.value.monthly_progress || { goal: 0, attended: 0, percentage: 0 })

function dateTime(value, timezone = 'America/Montevideo') {
  if (!value) return 'Sin fecha'
  return new Intl.DateTimeFormat('es-UY', { dateStyle: 'medium', timeStyle: 'short', timeZone: timezone }).format(new Date(String(value).replace(' ', 'T')))
}
function date(value) {
  if (!value) return 'Sin fecha'
  return new Intl.DateTimeFormat('es-UY', { dateStyle: 'medium' }).format(new Date(`${String(value).slice(0,10)}T12:00:00`))
}
function money(value, currency = 'UYU') {
  return new Intl.NumberFormat('es-UY', { style: 'currency', currency }).format(Number(value || 0))
}
function statusLabel(status) {
  return String(status || 'sin estado').replaceAll('_', ' ').replace(/^./, (letter) => letter.toUpperCase())
}
async function load() {
  if (!hasContext.value) return
  await member.loadSummary()
}

watch(() => auth.user?.active_gym_id, () => { member.reset(); load() })
onMounted(load)
</script>

<template>
  <section class="member-dashboard" aria-labelledby="member-summary-title">
    <div v-if="!hasContext" class="member-dashboard__empty">
      <AppEmptyState title="Elegí un gimnasio" description="Seleccioná tu contexto activo para ver clases, progreso, membresía y pagos." />
    </div>
    <div v-else-if="member.summaryStatus === 'loading'" class="summary-skeleton" aria-label="Cargando resumen del socio">
      <AppSkeleton height="16rem" /><AppSkeleton height="16rem" /><AppSkeleton height="10rem" />
    </div>
    <AppErrorState v-else-if="member.summaryStatus === 'error'" title="No pudimos cargar tu actividad" :description="member.error" @retry="load" />
    <template v-else>
      <header class="member-dashboard__heading">
        <div><h2 id="member-summary-title">Lo importante para hoy</h2><p>Tu próxima actividad y el estado actual de tu cuenta.</p></div>
        <AppLinkButton :to="{ name: 'member-card' }" variant="secondary"><template #icon><IconIdBadge2 :size="18" /></template>Ver carné</AppLinkButton>
      </header>

      <div class="dashboard-focus">
        <article class="next-class">
          <div class="summary-label"><IconCalendarEvent :size="20" /><span>Próxima clase</span></div>
          <template v-if="data.next_class">
            <h3>{{ data.next_class.nombre }}</h3>
            <p>{{ dateTime(data.next_class.inicio_en, data.next_class.zona_horaria) }}</p>
            <span>{{ data.next_class.sede_nombre || 'Sede principal' }}<template v-if="data.next_class.instructor_nombre">, {{ data.next_class.instructor_nombre }}</template></span>
            <AppBadge :tone="data.next_class.reserva_estado === 'confirmada' ? 'success' : 'warning'">{{ statusLabel(data.next_class.reserva_estado) }}</AppBadge>
          </template>
          <template v-else>
            <h3>No hay una clase reservada</h3><p>Consultá cupos y elegí tu próximo entrenamiento.</p>
          </template>
          <AppLinkButton :to="{ name: 'member-schedule' }" size="sm">Abrir agenda</AppLinkButton>
        </article>

        <section class="quick-stats" aria-label="Resumen de progreso, membresía y pagos">
          <RouterLink :to="{ name: 'member-activity' }">
            <IconTrendingUp :size="19" aria-hidden="true" /><span>Progreso del mes</span>
            <strong>{{ progress.attended }} de {{ progress.goal }}</strong><small>{{ progress.percentage }}% del objetivo</small>
            <IconChevronRight class="quick-stats__chevron" :size="16" aria-hidden="true" />
          </RouterLink>
          <RouterLink :to="{ name: 'member-payments' }">
            <IconIdBadge2 :size="19" aria-hidden="true" /><span>Membresía</span>
            <template v-if="data.membership"><strong>{{ data.membership.plan }}</strong><small>Vence {{ date(data.membership.fecha_vencimiento) }}</small></template>
            <template v-else><strong>Sin membresía</strong><small>Revisá los planes disponibles</small></template>
            <IconChevronRight class="quick-stats__chevron" :size="16" aria-hidden="true" />
          </RouterLink>
          <RouterLink :to="{ name: 'member-payments' }" :class="{ 'quick-stats__alert': data.pending_payment }">
            <IconReceipt :size="19" aria-hidden="true" /><span>Pagos</span>
            <template v-if="data.pending_payment"><strong>{{ money(data.pending_payment.monto, data.pending_payment.moneda) }}</strong><small>{{ statusLabel(data.pending_payment.estado) }}</small></template>
            <template v-else><strong>Al día</strong><small>Sin deuda pendiente</small></template>
            <IconChevronRight class="quick-stats__chevron" :size="16" aria-hidden="true" />
          </RouterLink>
        </section>
      </div>

      <section class="recent-activity" aria-labelledby="recent-activity-title">
        <IconTrendingUp :size="20" aria-hidden="true" />
        <div><h3 id="recent-activity-title">Actividad reciente</h3><p>{{ progress.attended }} {{ progress.attended === 1 ? 'asistencia registrada' : 'asistencias registradas' }} este mes.</p></div>
        <AppLinkButton :to="{ name: 'member-activity' }" variant="ghost" size="sm">Ver historial</AppLinkButton>
        <AppLinkButton :to="{ name: 'member-payments' }" variant="ghost" size="sm"><template #icon><IconCreditCard :size="17" /></template>Ver pagos</AppLinkButton>
      </section>

      <details v-if="data.promotions?.length" class="member-promotions">
        <summary><span><strong>Promociones disponibles</strong><small>{{ data.promotions.length }} {{ data.promotions.length === 1 ? 'beneficio vigente' : 'beneficios vigentes' }}</small></span><span aria-hidden="true">+</span></summary>
        <div class="promotion-list">
          <article v-for="promotion in data.promotions" :key="promotion.id">
            <div><strong>{{ promotion.nombre }}</strong><p>{{ promotion.descripcion }}</p></div>
            <div><AppBadge tone="info">Vigente hasta {{ date(promotion.fin_en) }}</AppBadge><strong v-if="promotion.tipo_descuento !== 'sin_descuento'">{{ promotion.tipo_descuento === 'porcentaje' ? `${promotion.valor_descuento}%` : money(promotion.valor_descuento, promotion.moneda) }}</strong><span v-if="promotion.codigo_descuento">Código: {{ promotion.codigo_descuento }}</span></div>
          </article>
        </div>
      </details>
    </template>
  </section>
</template>

<style scoped>
.member-dashboard { margin-top: var(--space-4); border-top: 1px solid var(--border-subtle); padding-top: var(--space-5); }
.member-dashboard__heading { display: flex; align-items: flex-end; justify-content: space-between; gap: var(--space-6); margin-bottom: var(--space-4); }
.member-dashboard__heading h2 { margin: 0 0 var(--space-1); font-size: clamp(1.15rem,2.2vw,1.5rem); }
.member-dashboard__heading p { max-width: 60ch; margin: 0; color: var(--text-secondary); font-size: .85rem; }
.summary-skeleton { display: grid; grid-template-columns: 1.2fr .8fr; gap: var(--space-4); }.summary-skeleton > :last-child { grid-column: 1/-1; }
.dashboard-focus { display: grid; grid-template-columns: minmax(0,1.2fr) minmax(20rem,.8fr); border: 1px solid var(--border-subtle); border-radius: var(--radius-card); background: var(--surface-1); overflow: hidden; }
.summary-label { display: flex; align-items: center; gap: var(--space-2); color: var(--status-info-strong); font-size: .75rem; font-weight: 720; }
.next-class { display: grid; min-height: 12rem; align-content: start; gap: var(--space-3); padding: clamp(var(--space-5),3vw,var(--space-6)); }
.next-class h3 { margin: var(--space-2) 0 0; font-size: clamp(1.25rem,2.5vw,1.7rem); }.next-class p { margin: 0; color: var(--text-secondary); }.next-class > span:not(.badge) { color: var(--text-tertiary); font-size: .78rem; }.next-class :deep(.link-button) { justify-self: start; margin-top: auto; }
.quick-stats { display: grid; min-width: 0; border-left: 1px solid var(--border-subtle); }
.quick-stats > a { position: relative; display: grid; min-width: 0; grid-template-columns: auto minmax(0,1fr) auto; gap: .1rem var(--space-3); align-content: center; padding: var(--space-4) var(--space-5); border-bottom: 1px solid var(--border-subtle); color: inherit; text-decoration: none; transition: background-color var(--duration-fast) var(--ease-standard); }
.quick-stats > a:last-child { border-bottom: 0; }.quick-stats > a:hover,.quick-stats > a:focus-visible { background: var(--surface-2); }.quick-stats > a:active { transform: scale(.99); }
.quick-stats svg:first-child { grid-column: 1; grid-row: 1/4; color: var(--info); }.quick-stats span { grid-column: 2; color: var(--text-tertiary); font-size: .68rem; }.quick-stats strong { grid-column: 2; overflow: hidden; font-size: .95rem; text-overflow: ellipsis; white-space: nowrap; }.quick-stats small { grid-column: 2; color: var(--text-secondary); font-size: .7rem; }
.quick-stats__chevron { grid-column: 3; grid-row: 1/4; align-self: center; color: var(--text-tertiary); }
.quick-stats__alert strong,.quick-stats__alert small { color: var(--status-danger-strong); }
.recent-activity { display: grid; grid-template-columns: auto minmax(0,1fr) auto auto; align-items: center; gap: var(--space-3); margin-top: var(--space-4); border-block: 1px solid var(--border-subtle); padding: var(--space-3) 0; }.recent-activity > svg { color: var(--info); }.recent-activity h3 { margin: 0; font-size: .9rem; }.recent-activity p { margin: .1rem 0 0; color: var(--text-secondary); font-size: .74rem; }
.member-promotions { margin-top: var(--space-4); border-bottom: 1px solid var(--border-subtle); }.member-promotions summary { display: flex; min-height: 3.75rem; align-items: center; justify-content: space-between; cursor: pointer; list-style: none; }.member-promotions summary::-webkit-details-marker { display: none; }.member-promotions summary > span:first-child { display: grid; }.member-promotions summary strong { font-size: .84rem; }.member-promotions summary small { color: var(--text-tertiary); font-size: .7rem; }.member-promotions summary > span:last-child { color: var(--text-tertiary); font-size: 1.25rem; transition: transform var(--duration-normal) var(--ease-out); }.member-promotions[open] summary > span:last-child { transform: rotate(45deg); }.promotion-list { border-top: 1px solid var(--border-subtle); }.promotion-list article { display: grid; grid-template-columns: minmax(0,1fr) auto; gap: var(--space-5); border-bottom: 1px solid var(--border-subtle); padding: var(--space-4) 0; }.promotion-list article:last-child { border-bottom: 0; }.promotion-list article p { max-width: 58ch; margin: var(--space-2) 0 0; color: var(--text-secondary); font-size: .8rem; }.promotion-list article > div:last-child { display: grid; justify-items: end; gap: var(--space-2); }.promotion-list article > div:last-child > strong { font-size: 1.2rem; font-variant-numeric: tabular-nums; }.promotion-list article > div:last-child > span { color: var(--status-info-strong); font-size: .72rem; font-weight: 750; }
@media (max-width: 63.99rem) { .dashboard-focus { grid-template-columns: minmax(0,1fr) minmax(17rem,.75fr); } }
@media (max-width: 47.99rem) {
  .member-dashboard__heading { align-items: stretch; flex-direction: column; }.member-dashboard__heading > :last-child { width: 100%; }
  .summary-skeleton,.dashboard-focus { grid-template-columns: 1fr; }.summary-skeleton > :last-child { grid-column: auto; }
  .next-class { min-height: 13rem; }.quick-stats { border-top: 1px solid var(--border-subtle); border-left: 0; }.quick-stats > div { min-height: 4.7rem; }
  .recent-activity { grid-template-columns: minmax(0,1fr); row-gap: var(--space-2); }.recent-activity > svg { display: none; }.recent-activity > :deep(.link-button) { width: 100%; }
  .promotion-list article { grid-template-columns: 1fr; }.promotion-list article > div:last-child { justify-items: start; }
}
</style>
