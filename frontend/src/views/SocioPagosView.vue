<script setup>
import { computed, onBeforeUnmount, onMounted, ref } from 'vue'
import { useRoute } from 'vue-router'
import { IconCreditCard, IconRefresh } from '@tabler/icons-vue'
import { useAuthStore } from '../stores/auth'
import { usePagosStore } from '../stores/pagos'
import AppAlert from '../components/ui/AppAlert.vue'
import AppBadge from '../components/ui/AppBadge.vue'
import AppButton from '../components/ui/AppButton.vue'
import AppCard from '../components/ui/AppCard.vue'
import AppEmptyState from '../components/ui/AppEmptyState.vue'
import AppErrorState from '../components/ui/AppErrorState.vue'
import AppSelect from '../components/ui/AppSelect.vue'
import AppSkeleton from '../components/ui/AppSkeleton.vue'
import PublicFooter from '../components/public/PublicFooter.vue'
import SocioBottomNav from '../components/member/SocioBottomNav.vue'
import SocioPageHeader from '../components/member/SocioPageHeader.vue'
import SocioTopNav from '../components/member/SocioTopNav.vue'

const auth = useAuthStore(); const payments = usePagosStore(); const route = useRoute(); const notice = ref(null)
const contexts = computed(() => (auth.user?.gimnasios || []).filter((gym) => gym.rol_nombre === 'socio'))
const contextOptions = computed(() => [{ value: '', label: 'Seleccioná un gimnasio' }, ...contexts.value.map((gym) => ({ value: String(gym.gimnasio_id), label: gym.nombre }))])
const hasContext = computed(() => Boolean(auth.user?.active_gym_id))
const pending = computed(() => payments.memberItems.filter((item) => ['pendiente', 'vencido'].includes(item.estado)))
function money(value, currency = 'UYU') { return new Intl.NumberFormat('es-UY', { style: 'currency', currency }).format(Number(value || 0)) }
function date(value) { if (!value) return 'Sin confirmar'; return new Intl.DateTimeFormat('es-UY', { dateStyle: 'medium', timeStyle: 'short' }).format(new Date(String(value).replace(' ', 'T'))) }
function tone(status) { return ({ aprobado: 'success', pendiente: 'warning', vencido: 'danger', rechazado: 'danger', reembolsado: 'neutral', cancelado: 'neutral' })[status] || 'neutral' }
function label(status) { return String(status).replaceAll('_', ' ').replace(/^./, (letter) => letter.toUpperCase()) }
async function load() { if (hasContext.value) await payments.loadMember() }
async function switchGym(value) { if (!value) return; try { await auth.cambiarGimnasio(Number(value)); notice.value = null; await load() } catch (error) { notice.value = { tone: 'danger', title: 'No se pudo cambiar el gimnasio', text: error.message } } }
async function checkout(plan) { try { const result = await payments.checkout(plan.id); if (!result.checkout_url) { notice.value = { tone: 'warning', title: 'Checkout todavía no disponible', text: 'La solicitud existe, pero el proveedor no devolvió una dirección de pago.' }; return } window.location.assign(result.checkout_url) } catch (error) { notice.value = { tone: 'danger', title: 'No se pudo iniciar el pago', text: error.message } } }
onMounted(async () => { if (route.query.resultado) notice.value = { tone: route.query.resultado === 'fallido' ? 'danger' : 'info', title: route.query.resultado === 'fallido' ? 'El pago no se completó' : 'Estamos verificando el pago', text: route.query.resultado === 'fallido' ? 'Podés volver a intentarlo. No se activó ninguna membresía.' : 'Volver a GymTrack no aprueba el cobro. El estado cambiará únicamente después de confirmar el webhook de Mercado Pago.' }; await load() })
onBeforeUnmount(() => payments.reset())
</script>

<template>
  <div class="member-payments-shell">
    <SocioTopNav />
    <main class="container payments-main">
      <SocioPageHeader title="Pagos" description="Elegí un plan, iniciá el checkout y consultá la confirmación recibida por GymTrack." />
      <AppAlert v-if="notice" :tone="notice.tone" :title="notice.title" class="member-notice"><p>{{ notice.text }}</p></AppAlert>
      <AppCard v-if="contexts.length" class="context-card"><AppSelect :model-value="String(auth.user?.active_gym_id || '')" label="Gimnasio" :options="contextOptions" hint="Planes, membresías y pagos se aíslan por gimnasio." @update:model-value="switchGym" /></AppCard>
      <AppEmptyState v-if="!contexts.length" title="No tenés un gimnasio asociado" description="Necesitás una asociación activa como socio para consultar planes y pagos." />
      <AppEmptyState v-else-if="!hasContext" title="Elegí un gimnasio" description="Seleccioná arriba el contexto donde querés consultar tus pagos." />
      <div v-else-if="payments.memberStatus === 'loading'" class="member-loading"><AppSkeleton height="10rem" /><AppSkeleton height="18rem" /></div>
      <AppErrorState v-else-if="payments.memberStatus === 'error'" :description="payments.memberError" @retry="load" />
      <template v-else>
        <AppAlert v-if="!payments.memberProvider.configured" tone="warning" title="Mercado Pago sin configurar"><p>Podés consultar planes e historial. El checkout se habilitará cuando el servidor tenga credenciales {{ payments.memberProvider.mode === 'produccion' ? 'de producción' : 'de prueba' }}.</p></AppAlert>
        <section v-if="pending.length" class="payments-section" aria-labelledby="pending-title"><div class="section-heading"><h2 id="pending-title">Pagos por resolver</h2><AppButton variant="ghost" size="sm" @click="load"><template #icon><IconRefresh :size="17" /></template>Actualizar</AppButton></div><div class="transaction-list"><AppCard v-for="payment in pending" :key="payment.id" class="transaction"><div><AppBadge :tone="tone(payment.estado)">{{ label(payment.estado) }}</AppBadge><h3>{{ payment.plan }}</h3><p>{{ payment.referencia_externa }}</p></div><strong>{{ money(payment.monto, payment.moneda) }}</strong><span>{{ payment.estado === 'vencido' ? 'La solicitud venció; iniciá un checkout nuevo.' : `Disponible hasta ${date(payment.fecha_vencimiento)}` }}</span></AppCard></div></section>
        <section class="payments-section" aria-labelledby="plans-title"><div class="section-heading"><div><h2 id="plans-title">Planes disponibles</h2><p>La membresía se activa sólo después de confirmar el pago.</p></div></div><AppEmptyState v-if="!payments.memberPlans.length" title="No hay planes disponibles" description="Este gimnasio todavía no publicó planes activos." /><div v-else class="plan-list"><AppCard v-for="plan in payments.memberPlans" :key="plan.id" class="plan-row"><div><h3>{{ plan.nombre }}</h3><p>{{ plan.descripcion }}</p><span>{{ plan.duracion_dias }} días · {{ plan.beneficios.join(' · ') }}</span></div><strong>{{ money(plan.precio, plan.moneda) }}</strong><AppButton :loading="payments.working === `checkout-${plan.id}`" :disabled="!payments.memberProvider.configured" @click="checkout(plan)"><template #icon><IconCreditCard :size="18" /></template>Pagar con Mercado Pago</AppButton></AppCard></div></section>
        <section class="payments-section" aria-labelledby="history-title"><div class="section-heading"><h2 id="history-title">Historial</h2><span>{{ payments.memberItems.length }} transacciones</span></div><AppEmptyState v-if="!payments.memberItems.length" title="Todavía no hay pagos" description="Cuando inicies o registres un pago aparecerá aquí." /><div v-else class="history-list"><div v-for="payment in payments.memberItems" :key="payment.id"><span><AppBadge :tone="tone(payment.estado)">{{ label(payment.estado) }}</AppBadge>{{ payment.plan }}</span><strong>{{ money(payment.monto, payment.moneda) }}</strong><time>{{ date(payment.fecha_pago || payment.creado_en) }}</time></div></div></section>
      </template>
    </main><PublicFooter /><SocioBottomNav />
  </div>
</template>

<style scoped>
.member-payments-shell { min-height: 100vh; background: var(--bg-canvas); }.payments-main { min-height: 75vh; padding-bottom: var(--space-20); }.member-notice p { margin: 0; color: var(--text-secondary); }.member-notice { margin-bottom: var(--space-5); }.context-card { width: min(100%,30rem); margin-bottom: var(--space-8); padding: var(--space-4); }.member-loading { display: grid; gap: var(--space-4); }.payments-section { margin-top: var(--space-10); border-top: 1px solid var(--border-subtle); padding-top: var(--space-8); }.section-heading { display: flex; align-items: flex-end; justify-content: space-between; gap: var(--space-5); margin-bottom: var(--space-5); }.section-heading h2 { margin: 0; }.section-heading p,.section-heading > span { margin: var(--space-1) 0 0; color: var(--text-tertiary); font-size: .78rem; }.transaction-list,.plan-list { display: grid; gap: var(--space-3); }.transaction { display: grid; grid-template-columns: minmax(0,1fr) auto; gap: var(--space-2) var(--space-5); }.transaction h3,.plan-row h3 { margin: var(--space-3) 0 var(--space-1); font-size: 1rem; }.transaction p,.plan-row p { margin: 0; color: var(--text-secondary); font-size: .8rem; }.transaction > strong,.plan-row > strong { font-size: 1.15rem; font-variant-numeric: tabular-nums; }.transaction > span { grid-column: 1/-1; color: var(--text-tertiary); font-size: .72rem; }.plan-row { display: grid; grid-template-columns: minmax(0,1fr) auto auto; align-items: center; gap: var(--space-6); }.plan-row > div > span { display: block; margin-top: var(--space-3); color: var(--text-tertiary); font-size: .72rem; }.history-list { border-top: 1px solid var(--border-subtle); }.history-list > div { display: grid; grid-template-columns: minmax(0,1fr) auto 11rem; align-items: center; gap: var(--space-4); border-bottom: 1px solid var(--border-subtle); padding: var(--space-4) 0; }.history-list span { display: flex; align-items: center; gap: var(--space-3); min-width: 0; }.history-list strong { font-variant-numeric: tabular-nums; }.history-list time { color: var(--text-tertiary); font-size: .75rem; text-align: right; }
@media (max-width: 63.99rem) { .plan-row { grid-template-columns: 1fr auto; }.plan-row > :last-child { grid-column: 1/-1; width: 100%; } }
@media (max-width: 47.99rem) { .section-heading { align-items: flex-start; flex-direction: column; }.transaction,.plan-row { grid-template-columns: 1fr; }.transaction > span,.plan-row > :last-child { grid-column: auto; }.history-list > div { grid-template-columns: 1fr auto; }.history-list time { grid-column: 1/-1; text-align: left; } }
</style>
