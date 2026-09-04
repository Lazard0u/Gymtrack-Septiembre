<!--
  Vista administrativa AdminPaymentsView. La ruta comprueba rol y permiso; la vista carga, presenta y modifica el recurso mediante su store.
  En <script> se declaran imports, estado y funciones; <template> describe la interfaz y <style> limita su presentación.
-->
<script setup>
import { computed, onBeforeUnmount, reactive, ref, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { IconPlus, IconReceiptRefund } from '@tabler/icons-vue'
import AdminDataTable from '../../components/admin/AdminDataTable.vue'
import AdminPageHeading from '../../components/admin/AdminPageHeading.vue'
import AppAlert from '../../components/ui/AppAlert.vue'
import AppButton from '../../components/ui/AppButton.vue'
import AppCard from '../../components/ui/AppCard.vue'
import AppDialog from '../../components/ui/AppDialog.vue'
import AppDrawer from '../../components/ui/AppDrawer.vue'
import AppInput from '../../components/ui/AppInput.vue'
import AppSelect from '../../components/ui/AppSelect.vue'
import { useAdminStore } from '../../stores/admin'
import { usePaymentsStore } from '../../stores/payments'

const route = useRoute(); const router = useRouter(); const admin = useAdminStore(); const payments = usePaymentsStore()
const drawerOpen = ref(false); const detail = ref(null); const refundOpen = ref(false); const notice = ref(null); const refundReason = ref(''); const manualError = ref('')
const manual = reactive({ membership_id: '', amount: '', currency: 'UYU', method: 'efectivo', paid_at: new Date().toISOString().slice(0, 10), concept: 'Pago de membresía', notes: '' })
const query = computed(() => ({ q: String(route.query.q || ''), status: String(route.query.status || ''), method: String(route.query.method || ''), from: String(route.query.from || ''), to: String(route.query.to || ''), sort: String(route.query.sort || 'paid_at'), direction: route.query.direction === 'asc' ? 'asc' : 'desc', page: Math.max(1, Number(route.query.page || 1)), per_page: 20 }))
const filter = reactive({ q: '', status: '', method: '', from: '', to: '' })
const canManual = computed(() => admin.hasPermission('payments.manual'))
const membershipOptions = computed(() => [{ value: '', label: 'Seleccioná una membresía' }, ...(payments.options.memberships || []).map((item) => ({ value: String(item.id), label: `${item.usuario_nombre} · ${item.plan} · ${money(item.monto, item.moneda)}` }))])
const columns = [
  { key: 'usuario_nombre', label: 'Socio', sortable: true }, { key: 'referencia_externa', label: 'Referencia' },
  { key: 'monto', label: 'Importe', format: 'money', currencyKey: 'moneda', sortable: true }, { key: 'metodo', label: 'Método', sortable: true },
  { key: 'estado', label: 'Estado', format: 'status', sortable: true }, { key: 'fecha_pago', label: 'Fecha', format: 'datetime', sortable: true },
]
const statusOptions = [{ value: '', label: 'Todos' }, ...['pendiente', 'aprobado', 'rechazado', 'vencido', 'reembolsado', 'cancelado'].map((value) => ({ value, label: value[0].toUpperCase() + value.slice(1) }))]
const methodOptions = [{ value: '', label: 'Todos' }, { value: 'efectivo', label: 'Efectivo' }, { value: 'transferencia', label: 'Transferencia' }, { value: 'tarjeta', label: 'Tarjeta' }, { value: 'debito', label: 'Débito' }, { value: 'mercado_pago', label: 'Mercado Pago' }]

function money(value, currency = 'UYU') { return new Intl.NumberFormat('es-UY', { style: 'currency', currency }).format(Number(value || 0)) }
function syncFilter() { Object.assign(filter, { q: query.value.q, status: query.value.status, method: query.value.method, from: query.value.from, to: query.value.to }) }
function applyFilters() { router.replace({ query: Object.fromEntries(Object.entries({ ...filter, page: 1 }).filter(([, value]) => value !== '')) }) }
function clearFilters() { Object.assign(filter, { q: '', status: '', method: '', from: '', to: '' }); router.replace({ query: {} }) }
function sort(key) { router.replace({ query: { ...route.query, sort: key, direction: query.value.sort === key && query.value.direction === 'asc' ? 'desc' : 'asc', page: 1 } }) }
function page(value) { router.replace({ query: { ...route.query, page: value } }) }
async function openManual() { drawerOpen.value = true; notice.value = null; manualError.value = ''; if (!payments.options.memberships.length && !await payments.loadOptions()) manualError.value = 'No pudimos cargar las membresías. Cerrá el panel e intentá nuevamente.' }
function selectMembership(value) { manual.membership_id = value; const item = payments.options.memberships.find((row) => String(row.id) === String(value)); if (item) { manual.amount = item.monto; manual.currency = item.moneda } }
async function saveManual() { manualError.value = ''; try { await payments.createManual({ ...manual, membership_id: Number(manual.membership_id), amount: Number(manual.amount) }); drawerOpen.value = false; notice.value = { tone: 'success', title: 'Pago registrado', text: 'La transacción quedó aprobada y la membresía fue actualizada.' }; await payments.loadPayments(query.value) } catch (error) { manualError.value = error.message } }
function openDetail(item) { detail.value = item }
function requestRefund() { refundReason.value = ''; refundOpen.value = true }
async function refund() { try { await payments.refund(detail.value.id, refundReason.value); refundOpen.value = false; detail.value = null; notice.value = { tone: 'success', title: 'Reembolso confirmado', text: 'El estado del pago y su historial fueron actualizados.' }; await payments.loadPayments(query.value) } catch (error) { notice.value = { tone: 'danger', title: 'No se pudo reembolsar', text: error.message }; refundOpen.value = false } }

watch([() => route.query, () => admin.version], () => { syncFilter(); payments.loadPayments(query.value); payments.loadOptions(); if (route.query.action === 'manual' && canManual.value) drawerOpen.value = true }, { immediate: true, deep: true })
onBeforeUnmount(() => payments.reset())
</script>

<template>
  <section>
    <AdminPageHeading title="Pagos" description="Controlá transacciones, estados y referencias verificables del gimnasio activo.">
      <template #actions><AppButton v-if="canManual" @click="openManual"><template #icon><IconPlus :size="18" /></template>Registrar pago</AppButton></template>
    </AdminPageHeading>
    <AppAlert v-if="notice" :tone="notice.tone" :title="notice.title" class="payments-notice"><p>{{ notice.text }}</p></AppAlert>
    <AppCard :padded="false" class="payments-card">
      <form class="payment-filters" role="search" @submit.prevent="applyFilters">
        <AppInput v-model="filter.q" label="Buscar" placeholder="Socio, correo o referencia" />
        <AppSelect v-model="filter.status" label="Estado" :options="statusOptions" />
        <AppSelect v-model="filter.method" label="Método" :options="methodOptions" />
        <AppInput v-model="filter.from" type="date" label="Desde" />
        <AppInput v-model="filter.to" type="date" label="Hasta" />
        <div class="payment-filters__actions"><AppButton type="submit" size="sm" :loading="payments.paymentsStatus === 'loading'">Aplicar</AppButton><AppButton variant="ghost" size="sm" @click="clearFilters">Limpiar</AppButton></div>
      </form>
      <AdminDataTable :columns="columns" :items="payments.items" :pagination="payments.pagination" :status="payments.paymentsStatus" :error="payments.paymentsError" :sort="query.sort" :direction="query.direction" empty-title="No hay transacciones" empty-description="No encontramos pagos con los filtros seleccionados." row-action-label="Ver detalle" @sort="sort" @page="page" @retry="payments.loadPayments(query)" @row="openDetail" />
    </AppCard>

    <AppDrawer :open="drawerOpen" title="Registrar pago manual" @close="drawerOpen = false">
      <form class="payment-form" @submit.prevent="saveManual">
        <AppAlert tone="info" title="Confirmación administrativa"><p>Este registro aprueba el pago inmediatamente y queda auditado con tu usuario.</p></AppAlert>
        <AppAlert v-if="manualError" tone="danger" title="No se pudo registrar el pago"><p>{{ manualError }}</p></AppAlert>
        <AppSelect :model-value="manual.membership_id" label="Membresía" :options="membershipOptions" @update:model-value="selectMembership" />
        <div class="payment-form__row"><AppInput v-model="manual.amount" type="number" inputmode="decimal" label="Importe" required /><AppInput v-model="manual.currency" label="Moneda" maxlength="3" required /></div>
        <AppSelect v-model="manual.method" label="Método" :options="methodOptions.filter((option) => option.value && option.value !== 'mercado_pago')" />
        <AppInput v-model="manual.paid_at" type="date" label="Fecha del pago" required />
        <AppInput v-model="manual.concept" label="Concepto" maxlength="180" required />
        <AppInput v-model="manual.notes" label="Notas" maxlength="500" hint="Opcional. No incluyas datos sensibles de tarjetas." />
        <div class="payment-form__actions"><AppButton variant="ghost" @click="drawerOpen = false">Cancelar</AppButton><AppButton type="submit" :loading="payments.working === 'manual'" :disabled="!manual.membership_id">Confirmar pago</AppButton></div>
      </form>
    </AppDrawer>

    <AppDrawer :open="Boolean(detail)" title="Detalle de transacción" @close="detail = null">
      <dl v-if="detail" class="payment-detail"><div><dt>Estado</dt><dd>{{ detail.estado }}</dd></div><div><dt>Referencia</dt><dd>{{ detail.referencia_externa }}</dd></div><div><dt>Socio</dt><dd>{{ detail.usuario_nombre }}<small>{{ detail.usuario_email }}</small></dd></div><div><dt>Plan</dt><dd>{{ detail.plan }}</dd></div><div><dt>Importe</dt><dd>{{ money(detail.monto, detail.moneda) }}</dd></div><div><dt>Proveedor</dt><dd>{{ detail.proveedor.replace('_', ' ') }} · {{ detail.modo }}</dd></div></dl>
      <AppButton v-if="detail?.estado === 'aprobado' && canManual" variant="danger" block @click="requestRefund"><template #icon><IconReceiptRefund :size="18" /></template>Reembolsar pago completo</AppButton>
    </AppDrawer>
    <AppDialog :open="refundOpen" title="Confirmar reembolso" description="El reembolso completo no puede deshacerse desde GymTrack." @close="refundOpen = false"><AppInput v-model="refundReason" label="Motivo" minlength="8" maxlength="255" required /><template #footer><AppButton variant="ghost" @click="refundOpen = false">Cancelar</AppButton><AppButton variant="danger" :loading="payments.working.startsWith('refund-')" :disabled="refundReason.trim().length < 8" @click="refund">Confirmar reembolso</AppButton></template></AppDialog>
  </section>
</template>

<style scoped>
.payments-notice { margin-bottom: var(--space-5); }.payments-notice p, .payment-form p { margin: 0; }.payments-card { overflow: clip; }.payment-filters { display: grid; grid-template-columns: minmax(13rem,1.5fr) repeat(4,minmax(9rem,1fr)) auto; align-items: end; gap: var(--space-3); border-bottom: 1px solid var(--border-subtle); padding: var(--space-4); }.payment-filters__actions { display: flex; gap: var(--space-2); }.payment-form { display: grid; gap: var(--space-5); }.payment-form__row { display: grid; grid-template-columns: 2fr 1fr; gap: var(--space-3); }.payment-form__actions { display: flex; justify-content: flex-end; gap: var(--space-2); padding-top: var(--space-3); }.payment-detail { display: grid; margin: 0 0 var(--space-6); }.payment-detail div { display: grid; grid-template-columns: 8rem 1fr; gap: var(--space-4); border-bottom: 1px solid var(--border-subtle); padding: var(--space-3) 0; }.payment-detail dt { color: var(--text-tertiary); font-size: .72rem; font-weight: 720; }.payment-detail dd { margin: 0; color: var(--text-primary); overflow-wrap: anywhere; }.payment-detail small { display: block; margin-top: var(--space-1); color: var(--text-tertiary); }
@media (max-width: 84rem) { .payment-filters { grid-template-columns: repeat(3,minmax(0,1fr)); }.payment-filters__actions { grid-column: 1/-1; } }
@media (max-width: 47.99rem) { .payment-filters { grid-template-columns: 1fr; }.payment-filters__actions { grid-column: auto; }.payment-filters__actions > * { flex: 1; }.payment-form__row { grid-template-columns: 1fr; }.payment-form__actions { display: grid; grid-template-columns: 1fr 1fr; }.payment-detail div { grid-template-columns: 1fr; gap: var(--space-1); } }
</style>
