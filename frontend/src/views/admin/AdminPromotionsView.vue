<!--
  Vista administrativa AdminPromotionsView. La ruta comprueba rol y permiso; la vista carga, presenta y modifica el recurso mediante su store.
  En <script> se declaran imports, estado y funciones; <template> describe la interfaz y <style> limita su presentación.
-->
<script setup>
import { computed, nextTick, onBeforeUnmount, onMounted, reactive, ref, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { IconChartBar, IconGift, IconPlayerPause, IconPlayerPlay, IconPlus, IconUpload } from '@tabler/icons-vue'
import AdminDataTable from '../../components/admin/AdminDataTable.vue'
import AdminFilterBar from '../../components/admin/AdminFilterBar.vue'
import AdminPageHeading from '../../components/admin/AdminPageHeading.vue'
import AppAlert from '../../components/ui/AppAlert.vue'
import AppBadge from '../../components/ui/AppBadge.vue'
import AppButton from '../../components/ui/AppButton.vue'
import AppCard from '../../components/ui/AppCard.vue'
import AppCheckbox from '../../components/ui/AppCheckbox.vue'
import AppDialog from '../../components/ui/AppDialog.vue'
import AppDrawer from '../../components/ui/AppDrawer.vue'
import AppInput from '../../components/ui/AppInput.vue'
import AppSelect from '../../components/ui/AppSelect.vue'
import AppSkeleton from '../../components/ui/AppSkeleton.vue'
import AppTextarea from '../../components/ui/AppTextarea.vue'
import AppToast from '../../components/ui/AppToast.vue'
import { useAdminStore } from '../../stores/admin'
import { useAdminManagementStore } from '../../stores/adminManagement'
import { usePromotionsStore } from '../../stores/promotions'
import { api } from '../../services/api'

const admin = useAdminStore()
const management = useAdminManagementStore()
const promotions = usePromotionsStore()
const route = useRoute()
const router = useRouter()
const editorOpen = ref(false)
const detailOpen = ref(false)
const resultsOpen = ref(false)
const confirmAction = ref(null)
const fields = ref({})
const formError = ref('')
const selectedFile = ref(null)
const fileInput = ref(null)
const gymsFieldset = ref(null)
const channelsFieldset = ref(null)
const toast = reactive({ open: false, title: '', message: '', tone: 'success' })
const form = reactive(blankPromotion())
const canWrite = computed(() => admin.hasPermission('promotions.write'))
const query = computed(() => ({ q: String(route.query.q || ''), status: String(route.query.status || ''), page: Math.max(1, Number(route.query.page || 1)), per_page: 20 }))
const filters = [{ key: 'status', label: 'Estado', options: [{ value: '', label: 'Todos' }, { value: 'borrador', label: 'Borradores' }, { value: 'programada', label: 'Programadas' }, { value: 'activa', label: 'Activas' }, { value: 'pausada', label: 'Pausadas' }, { value: 'finalizada', label: 'Finalizadas' }] }]
const columns = [
  { key: 'nombre', label: 'Promoción' }, { key: 'audiencia', label: 'Audiencia' }, { key: 'canales_label', label: 'Canales' }, { key: 'inicio_en', label: 'Inicio', format: 'datetime' }, { key: 'estado', label: 'Estado', format: 'status' }, { key: 'destinatarios_total', label: 'Destinatarios' },
]
const gymOptions = computed(() => admin.gyms.map((gym) => ({ id: Number(gym.gimnasio_id), label: gym.nombre })))
const selected = computed(() => promotions.selected || {})
const resultMetrics = computed(() => {
  const data = promotions.results?.totals || promotions.results || {}
  return [
    ['Destinatarios', data.recipients ?? data.destinatarios_total ?? 0],
    ['Enviados', data.sent ?? data.enviados ?? 0],
    ['Pendientes', data.pending ?? data.pendientes ?? 0],
    ['Fallidos', data.failed ?? data.fallidos ?? 0],
  ]
})

function blankPromotion() {
  const start = new Date(Date.now() + 24 * 60 * 60 * 1000)
  const end = new Date(Date.now() + 15 * 24 * 60 * 60 * 1000)
  return { id: null, nombre: '', descripcion: '', audiencia: 'todos_socios', inicio_en: localInput(start), fin_en: localInput(end), zona_horaria: 'America/Montevideo', tipo_descuento: 'sin_descuento', valor_descuento: '', moneda: 'UYU', codigo_descuento: '', imagen_archivo_id: null, gimnasio_ids: [], canales: ['internal'] }
}
function localInput(date) { const adjusted = new Date(date.getTime() - date.getTimezoneOffset() * 60000); return adjusted.toISOString().slice(0, 16) }
function apiDate(value) { return value ? value.replace('T', ' ') + (value.length === 16 ? ':00' : '') : '' }
function inputDate(value) { return String(value || '').replace(' ', 'T').slice(0, 16) }
function channels(item) { const values = Array.isArray(item.canales) ? item.canales : []; return values.map((value) => value === 'internal' ? 'Interna' : value === 'email' ? 'Correo' : 'WhatsApp').join(', ') || 'Sin canales' }
function normalizeItems() { return promotions.items.map((item) => ({ ...item, canales_label: channels(item), destinatarios_total: item.destinatarios_total ?? item.resultados?.destinatarios_total ?? 0 })) }
const tableItems = computed(normalizeItems)
function setQuery(next) { router.replace({ query: Object.fromEntries(Object.entries(next).filter(([, value]) => value !== '' && value !== null && value !== undefined)) }) }
function toggle(list, value, checked) { const current = form[list]; form[list] = checked ? [...new Set([...current, value])] : current.filter((item) => item !== value) }
function resetErrors() { fields.value = {}; formError.value = '' }
function notify(title, message = '', tone = 'success') { Object.assign(toast, { open: true, title, message, tone }) }

function openCreate() {
  Object.assign(form, blankPromotion(), { gimnasio_ids: admin.activeGymId ? [Number(admin.activeGymId)] : [] })
  selectedFile.value = null
  resetErrors()
  editorOpen.value = true
}
async function openDetail(item) {
  detailOpen.value = true
  resultsOpen.value = false
  resetErrors()
  try { await promotions.detail(item.id) } catch (error) { formError.value = error.message }
}
async function openEdit() {
  const item = selected.value
  Object.assign(form, blankPromotion(), item, {
    inicio_en: inputDate(item.inicio_en), fin_en: inputDate(item.fin_en),
    gimnasio_ids: Array.isArray(item.gimnasio_ids)
      ? item.gimnasio_ids.map(Number)
      : (item.gimnasios || []).map((gym) => Number(gym.id || gym.gimnasio_id)),
    canales: Array.isArray(item.canales) ? [...item.canales] : ['internal'],
    valor_descuento: item.valor_descuento ?? '',
  })
  detailOpen.value = false
  editorOpen.value = true
}
async function uploadImage() {
  if (!selectedFile.value) return null
  const result = await management.upload(selectedFile.value, 'promocion_imagen')
  if (!result.ok) throw new Error(result.message)
  return Number(result.data.id)
}
async function save() {
  resetErrors()
  const groupErrors = {}
  if (!form.gimnasio_ids.length) groupErrors.gimnasio_ids = 'Elegí al menos un gimnasio.'
  if (!form.canales.length) groupErrors.canales = 'Elegí al menos un canal.'
  if (Object.keys(groupErrors).length) {
    fields.value = groupErrors
    await nextTick()
    ;(groupErrors.gimnasio_ids ? gymsFieldset : channelsFieldset).value?.focus()
    return
  }
  try {
    const imageId = await uploadImage()
    const payload = {
      nombre: form.nombre, descripcion: form.descripcion, audiencia: form.audiencia,
      inicio_en: apiDate(form.inicio_en), fin_en: apiDate(form.fin_en), zona_horaria: form.zona_horaria,
      tipo_descuento: form.tipo_descuento,
      valor_descuento: form.tipo_descuento === 'sin_descuento' ? null : Number(form.valor_descuento),
      moneda: form.tipo_descuento === 'monto_fijo' ? form.moneda : null,
      codigo_descuento: form.codigo_descuento || null,
      imagen_archivo_id: imageId || form.imagen_archivo_id || null,
      gimnasio_ids: form.gimnasio_ids.map(Number), canales: form.canales,
    }
    await promotions.save(payload, form.id)
    editorOpen.value = false
    await promotions.load(query.value)
    notify(form.id ? 'Promoción actualizada' : 'Promoción creada', 'Los cambios quedaron guardados en MySQL.')
  } catch (error) { fields.value = error.fields || {}; formError.value = error.message }
}
async function transition(action) {
  try {
    await promotions.transition(selected.value.id, action)
    confirmAction.value = null
    detailOpen.value = false
    await promotions.load(query.value)
    notify(action === 'schedule' ? 'Promoción programada' : action === 'pause' ? 'Promoción pausada' : 'Promoción finalizada')
  } catch (error) { confirmAction.value = null; formError.value = error.message; notify('No se pudo cambiar el estado', error.message, 'danger') }
}
async function showResults() {
  resultsOpen.value = true
  try { await promotions.loadResults(selected.value.id) } catch (error) { formError.value = error.message }
}
async function removeDraft() {
  try {
    const response = await api.delete(`/admin/promotions/${selected.value.id}`)
    if (!response.ok || response.data?.error) throw new Error(response.data?.mensaje || 'No se pudo archivar el borrador.')
    confirmAction.value = null; detailOpen.value = false; await promotions.load(query.value); notify('Borrador archivado')
  } catch (error) { confirmAction.value = null; notify('No se pudo archivar', error.message, 'danger') }
}
watch(query, () => promotions.load(query.value), { deep: true })
watch(() => admin.version, () => promotions.load(query.value))
onMounted(async () => { await promotions.load(query.value); if (route.query.action === 'create' && canWrite.value) { openCreate(); await router.replace({ query: { ...route.query, action: undefined } }) } })
onBeforeUnmount(() => promotions.reset())
</script>

<template>
  <section class="promotions-view">
    <AdminPageHeading title="Promociones" description="Creá campañas reales, definí la audiencia y consultá el estado de cada entrega."><template #actions><AppButton v-if="canWrite" @click="openCreate"><template #icon><IconPlus :size="18" /></template>Nueva promoción</AppButton></template></AdminPageHeading>
    <AppAlert tone="info" title="Canales disponibles"><p>Notificaciones internas y correo están operativos. WhatsApp continúa desactivado hasta configurar un proveedor y consentimiento válido.</p></AppAlert>
    <div class="promotion-summary" aria-label="Resumen de promociones en esta página"><div><span>Borradores en esta página</span><strong>{{ promotions.items.filter((item) => item.estado === 'borrador').length }}</strong></div><div><span>Programadas en esta página</span><strong>{{ promotions.items.filter((item) => item.estado === 'programada').length }}</strong></div><div><span>Activas en esta página</span><strong>{{ promotions.items.filter((item) => item.estado === 'activa').length }}</strong></div><div><span>Finalizadas en esta página</span><strong>{{ promotions.items.filter((item) => item.estado === 'finalizada').length }}</strong></div></div>
    <AppCard :padded="false" class="promotion-table"><AdminFilterBar :model-value="query" :filters="filters" :loading="promotions.listStatus === 'loading'" search-placeholder="Nombre o código" @apply="setQuery" @clear="setQuery({})" /><AdminDataTable :columns="columns" :items="tableItems" :pagination="promotions.pagination" :status="promotions.listStatus" :error="promotions.error" :request-id="promotions.requestId" empty-title="No hay promociones" empty-description="Creá la primera promoción para el gimnasio activo." row-action-label="Abrir" @page="setQuery({ ...query, page: $event })" @retry="promotions.load(query)" @row="openDetail" /></AppCard>

    <AppDrawer :open="editorOpen" :title="form.id ? 'Editar promoción' : 'Nueva promoción'" @close="editorOpen = false"><form id="promotion-form" class="promotion-form" @submit.prevent="save"><AppAlert v-if="formError" tone="danger" title="No pudimos guardar"><p>{{ formError }}</p></AppAlert><AppInput v-model="form.nombre" label="Nombre" name="promotion-name" required minlength="3" maxlength="120" :error="fields.nombre" /><AppTextarea v-model="form.descripcion" label="Descripción" name="promotion-description" required maxlength="5000" :rows="5" hint="Se enviará como texto plano, sin HTML." :error="fields.descripcion" /><div class="form-pair"><AppSelect v-model="form.audiencia" label="Audiencia" name="promotion-audience" :options="[{ value: 'todos_socios', label: 'Todos los socios' }, { value: 'socios_activos', label: 'Socios activos' }, { value: 'socios_con_deuda', label: 'Socios con deuda' }, { value: 'socios_inactivos', label: 'Socios inactivos' }]" :error="fields.audiencia" /><AppInput v-model="form.zona_horaria" label="Zona horaria" name="promotion-timezone" required :error="fields.zona_horaria" /></div><div class="form-pair"><AppInput v-model="form.inicio_en" label="Inicio" name="promotion-start" type="datetime-local" required :error="fields.inicio_en" /><AppInput v-model="form.fin_en" label="Fin" name="promotion-end" type="datetime-local" required :error="fields.fin_en" /></div><fieldset ref="gymsFieldset" :class="{ invalid: fields.gimnasio_ids }" :aria-invalid="Boolean(fields.gimnasio_ids) || undefined" :aria-describedby="fields.gimnasio_ids ? 'promotion-gyms-error' : undefined" tabindex="-1"><legend>Gimnasios</legend><AppCheckbox v-for="gym in gymOptions" :key="gym.id" :model-value="form.gimnasio_ids.includes(gym.id)" :label="gym.label" @update:model-value="toggle('gimnasio_ids', gym.id, $event)" /><small v-if="fields.gimnasio_ids" id="promotion-gyms-error" role="alert">{{ fields.gimnasio_ids }}</small></fieldset><fieldset ref="channelsFieldset" :class="{ invalid: fields.canales }" :aria-invalid="Boolean(fields.canales) || undefined" :aria-describedby="fields.canales ? 'promotion-channels-error' : undefined" tabindex="-1"><legend>Canales</legend><AppCheckbox :model-value="form.canales.includes('internal')" label="Notificación interna" description="Aparece en el centro de notificaciones del socio." @update:model-value="toggle('canales', 'internal', $event)" /><AppCheckbox :model-value="form.canales.includes('email')" label="Correo electrónico" description="Respeta consentimiento y preferencia del socio." @update:model-value="toggle('canales', 'email', $event)" /><AppCheckbox :model-value="false" label="WhatsApp, Beta" description="No disponible hasta configurar un adaptador real." disabled /><small v-if="fields.canales" id="promotion-channels-error" role="alert">{{ fields.canales }}</small></fieldset><section class="discount-fields" aria-labelledby="discount-title"><h3 id="discount-title">Descuento opcional</h3><AppSelect v-model="form.tipo_descuento" label="Tipo" name="promotion-discount-type" :options="[{ value: 'sin_descuento', label: 'Sin descuento' }, { value: 'porcentaje', label: 'Porcentaje' }, { value: 'monto_fijo', label: 'Monto fijo' }]" /><div v-if="form.tipo_descuento !== 'sin_descuento'" class="form-pair"><AppInput v-model="form.valor_descuento" label="Valor" name="promotion-discount-value" type="number" min="0.01" :max="form.tipo_descuento === 'porcentaje' ? 100 : 99999999" step="0.01" required :error="fields.valor_descuento" /><AppSelect v-if="form.tipo_descuento === 'monto_fijo'" v-model="form.moneda" label="Moneda" :options="[{ value: 'UYU', label: 'UYU' }, { value: 'USD', label: 'USD' }]" /></div><AppInput v-model="form.codigo_descuento" label="Código" name="promotion-code" maxlength="60" hint="Opcional. No se aplica automáticamente al checkout." /></section><div class="upload-field"><input ref="fileInput" class="visually-hidden" type="file" accept="image/jpeg,image/png,image/webp" aria-label="Seleccionar imagen de promoción" @change="selectedFile = $event.target.files?.[0] || null" /><AppButton variant="secondary" type="button" @click="fileInput?.click()"><template #icon><IconUpload :size="17" /></template>Seleccionar imagen</AppButton><span>{{ selectedFile?.name || (form.imagen_archivo_id ? 'Imagen actual conservada' : 'Sin imagen') }}</span></div></form><template #footer><div class="drawer-actions"><AppButton variant="secondary" @click="editorOpen = false">Cancelar</AppButton><AppButton type="submit" form="promotion-form" :loading="promotions.working">Guardar promoción</AppButton></div></template></AppDrawer>

    <AppDrawer :open="detailOpen" title="Detalle de promoción" @close="detailOpen = false"><div v-if="promotions.detailStatus === 'loading'" class="detail-loading"><AppSkeleton height="8rem" /><AppSkeleton height="12rem" /></div><div v-else class="promotion-detail"><AppAlert v-if="formError" tone="danger" title="No pudimos cargar"><p>{{ formError }}</p></AppAlert><div class="detail-title"><IconGift :size="24" /><div><h3>{{ selected.nombre }}</h3><AppBadge :tone="selected.estado === 'activa' ? 'success' : selected.estado === 'programada' ? 'info' : selected.estado === 'pausada' ? 'warning' : 'neutral'">{{ selected.estado }}</AppBadge></div></div><p>{{ selected.descripcion }}</p><dl><div><dt>Audiencia</dt><dd>{{ selected.audiencia?.replaceAll('_', ' ') }}</dd></div><div><dt>Canales</dt><dd>{{ channels(selected) }}</dd></div><div><dt>Vigencia</dt><dd>{{ selected.inicio_en }} a {{ selected.fin_en }}</dd></div><div><dt>Descuento</dt><dd>{{ selected.tipo_descuento === 'sin_descuento' ? 'Sin descuento' : `${selected.valor_descuento} ${selected.tipo_descuento === 'monto_fijo' ? selected.moneda : '%'}` }}</dd></div></dl><AppButton variant="secondary" block @click="showResults"><template #icon><IconChartBar :size="17" /></template>Consultar resultados</AppButton></div><template #footer><div v-if="canWrite" class="detail-actions"><AppButton v-if="selected.estado === 'borrador'" variant="ghost" @click="confirmAction = 'remove'">Archivar borrador</AppButton><AppButton v-if="selected.estado === 'borrador' || selected.estado === 'pausada'" variant="secondary" @click="openEdit">Editar</AppButton><AppButton v-if="selected.estado === 'borrador'" @click="confirmAction = 'schedule'"><template #icon><IconPlayerPlay :size="17" /></template>Programar</AppButton><AppButton v-if="selected.estado === 'programada' || selected.estado === 'activa'" variant="secondary" @click="confirmAction = 'pause'"><template #icon><IconPlayerPause :size="17" /></template>Pausar</AppButton><AppButton v-if="selected.estado === 'pausada'" @click="confirmAction = 'schedule'"><template #icon><IconPlayerPlay :size="17" /></template>Volver a programar</AppButton><AppButton v-if="['programada','activa','pausada'].includes(selected.estado)" variant="ghost" @click="confirmAction = 'finish'">Finalizar</AppButton></div></template></AppDrawer>

    <AppDrawer :open="resultsOpen" title="Resultados de la promoción" @close="resultsOpen = false"><div v-if="promotions.resultsStatus === 'loading'" class="detail-loading"><AppSkeleton v-for="item in 4" :key="item" height="5rem" /></div><div v-else-if="promotions.resultsStatus === 'error'"><AppAlert tone="danger" title="No pudimos cargar resultados"><p>{{ formError }}</p></AppAlert><AppButton variant="secondary" @click="showResults">Reintentar</AppButton></div><div v-else class="results-grid"><div v-for="metric in resultMetrics" :key="metric[0]"><span>{{ metric[0] }}</span><strong>{{ metric[1] }}</strong></div><AppAlert tone="info" title="Métricas reales"><p>Los valores provienen del historial de entregas. No se estiman aperturas o resultados ausentes.</p></AppAlert></div></AppDrawer>
    <AppDialog :open="Boolean(confirmAction)" :title="confirmAction === 'remove' ? 'Archivar borrador' : confirmAction === 'pause' ? 'Pausar promoción' : confirmAction === 'finish' ? 'Finalizar promoción' : selected.estado === 'pausada' ? 'Volver a programar' : 'Programar promoción'" description="El cambio quedará registrado y no confirmaremos entregas que todavía no hayan ocurrido." @close="confirmAction = null"><p class="confirm-copy">{{ confirmAction === 'schedule' ? 'La cola respetará las fechas, la audiencia y el consentimiento vigente al momento de cada envío.' : confirmAction === 'remove' ? 'El borrador dejará de aparecer, pero su auditoría se conservará.' : 'Las entregas pendientes se ajustarán al nuevo estado.' }}</p><template #footer><AppButton variant="secondary" @click="confirmAction = null">Cancelar</AppButton><AppButton :variant="confirmAction === 'remove' || confirmAction === 'finish' ? 'danger' : 'primary'" :loading="promotions.working" @click="confirmAction === 'remove' ? removeDraft() : transition(confirmAction)">Confirmar</AppButton></template></AppDialog>
    <AppToast v-bind="toast" @close="toast.open = false" />
  </section>
</template>

<style scoped>
.promotions-view { min-width: 0; }.promotions-view > :deep(.alert) { margin-bottom: var(--space-6); }.promotions-view :deep(.alert p),.confirm-copy { margin: 0; }.promotion-summary { display: grid; grid-template-columns: repeat(4,minmax(0,1fr)); margin-bottom: var(--space-6); border-block: 1px solid var(--border-subtle); }.promotion-summary div { display: grid; gap: var(--space-2); border-inline-end: 1px solid var(--border-subtle); padding: var(--space-5); }.promotion-summary div:last-child { border-inline-end: 0; }.promotion-summary span { color: var(--text-tertiary); font-size: .72rem; }.promotion-summary strong { font-size: 1.75rem; font-variant-numeric: tabular-nums; }.promotion-table { overflow: hidden; }.promotion-form,.promotion-detail,.detail-loading { display: grid; gap: var(--space-5); }.form-pair { display: grid; grid-template-columns: 1fr 1fr; gap: var(--space-3); }.promotion-form fieldset { display: grid; gap: var(--space-2); min-width: 0; margin: 0; border: 1px solid var(--border-subtle); border-radius: var(--radius-control); padding: var(--space-4); }.promotion-form fieldset:focus-visible { outline: 2px solid var(--focus-ring); outline-offset: 2px; }.promotion-form legend { padding-inline: var(--space-2); font-size: .82rem; font-weight: 720; }.promotion-form fieldset.invalid { border-color: var(--danger); }.promotion-form fieldset > small { color: var(--status-danger-text); }.discount-fields { display: grid; gap: var(--space-4); padding-top: var(--space-4); border-top: 1px solid var(--border-subtle); }.discount-fields h3 { margin: 0; font-size: .95rem; }.upload-field { display: grid; grid-template-columns: auto minmax(0,1fr); align-items: center; gap: var(--space-3); }.upload-field span { min-width: 0; overflow: hidden; color: var(--text-tertiary); font-size: .76rem; text-overflow: ellipsis; white-space: nowrap; }.drawer-actions,.detail-actions { display: flex; flex-wrap: wrap; justify-content: flex-end; gap: var(--space-2); }.detail-title { display: grid; grid-template-columns: 2rem 1fr; gap: var(--space-3); }.detail-title > svg { color: var(--info); }.detail-title h3 { margin: 0 0 var(--space-2); overflow-wrap: anywhere; }.promotion-detail > p { margin: 0; color: var(--text-secondary); white-space: pre-wrap; overflow-wrap: anywhere; }.promotion-detail dl { display: grid; gap: var(--space-3); margin: 0; }.promotion-detail dl div { display: grid; grid-template-columns: 7rem minmax(0,1fr); gap: var(--space-3); border-bottom: 1px solid var(--border-subtle); padding-bottom: var(--space-3); }.promotion-detail dt { color: var(--text-tertiary); font-size: .72rem; }.promotion-detail dd { margin: 0; color: var(--text-secondary); font-size: .8rem; overflow-wrap: anywhere; }.results-grid { display: grid; grid-template-columns: 1fr 1fr; gap: var(--space-3); }.results-grid > div { display: grid; gap: var(--space-2); border-bottom: 1px solid var(--border-subtle); padding: var(--space-4) 0; }.results-grid span { color: var(--text-tertiary); font-size: .72rem; }.results-grid strong { font-size: 1.6rem; font-variant-numeric: tabular-nums; }.results-grid :deep(.alert) { grid-column: 1 / -1; }
@media (max-width: 47.99rem) { .promotion-summary { grid-template-columns: 1fr 1fr; }.promotion-summary div:nth-child(2) { border-inline-end: 0; }.promotion-summary div:nth-child(-n+2) { border-bottom: 1px solid var(--border-subtle); }.form-pair { grid-template-columns: 1fr; }.drawer-actions,.detail-actions { display: grid; grid-template-columns: 1fr; width: 100%; }.drawer-actions > *,.detail-actions > * { width: 100%; }.promotion-detail dl div { grid-template-columns: 1fr; }.results-grid { grid-template-columns: 1fr 1fr; } }
@media (max-width: 22.5rem) { .results-grid { grid-template-columns: 1fr; }.results-grid :deep(.alert) { grid-column: auto; } }
</style>
