<!--
  Vista administrativa AdminScheduleView. La ruta comprueba rol y permiso; la vista carga, presenta y modifica el recurso mediante su store.
  En <script> se declaran imports, estado y funciones; <template> describe la interfaz y <style> limita su presentación.
-->
<script setup>
import { computed, onBeforeUnmount, onMounted, reactive, ref, watch } from 'vue'
import { IconAdjustmentsHorizontal, IconCalendarPlus, IconChevronLeft, IconChevronRight, IconClipboardCheck, IconClock, IconUsers } from '@tabler/icons-vue'
import { useAdminStore } from '../../stores/admin'
import { useScheduleStore } from '../../stores/schedule'
import AdminPageHeading from '../../components/admin/AdminPageHeading.vue'
import AppAlert from '../../components/ui/AppAlert.vue'
import AppBadge from '../../components/ui/AppBadge.vue'
import AppButton from '../../components/ui/AppButton.vue'
import AppCard from '../../components/ui/AppCard.vue'
import AppDialog from '../../components/ui/AppDialog.vue'
import AppDrawer from '../../components/ui/AppDrawer.vue'
import AppEmptyState from '../../components/ui/AppEmptyState.vue'
import AppErrorState from '../../components/ui/AppErrorState.vue'
import AppInput from '../../components/ui/AppInput.vue'
import AppSelect from '../../components/ui/AppSelect.vue'
import AppSkeleton from '../../components/ui/AppSkeleton.vue'
import AppTextarea from '../../components/ui/AppTextarea.vue'

const props = defineProps({ mode: { type: String, default: 'classes' } })
const admin = useAdminStore()
const schedule = useScheduleStore()
const notice = ref(null)
const saving = ref(false)
const classDrawer = ref(false)
const sessionDrawer = ref(false)
const rosterDialog = ref(false)
const cancelDialog = ref(false)
const bookingDialog = ref(false)
const selectedSession = ref(null)
const filters = reactive({ q: '', status: '', from: today(), to: plusDays(35), page: 1, per_page: 20 })
const classForm = reactive({ nombre: '', descripcion: '', instructor_id: '', sede_id: '', first_date: today(), hora_inicio: '18:00', hora_fin: '19:00', cupo_maximo: 16, repeat_weeks: 6, cancelacion_minutos: 120, color: '#0D7A56', notas: '' })
const sessionForm = reactive({ instructor_id: '', sede_id: '', inicio_en: '', fin_en: '', cupo_maximo: 1, notas: '' })
const cancelReason = ref('')
const memberId = ref('')
const formErrors = ref({})
const filtersExpanded = ref(false)

const isReservations = computed(() => props.mode === 'reservations')
const title = computed(() => isReservations.value ? 'Reservas y asistencia' : 'Agenda de clases')
const description = computed(() => isReservations.value
  ? 'Abrí cada sesión para gestionar socios, lista de espera y asistencia sin perder el contexto del gimnasio.'
  : 'Creá clases recurrentes y administrá cada fecha, entrenador, sede y cupo desde una agenda real.')
const statusOptions = [
  { value: '', label: 'Todos los estados' },
  { value: 'programada', label: 'Programadas' },
  { value: 'cancelada', label: 'Canceladas' },
  { value: 'finalizada', label: 'Finalizadas' },
]
const locationOptions = computed(() => [{ value: '', label: 'Seleccioná una sede' }, ...schedule.options.locations.map((item) => ({ value: String(item.id), label: item.nombre }))])
const trainerOptions = computed(() => [{ value: '', label: 'Seleccioná un entrenador' }, ...schedule.options.trainers.map((item) => ({ value: String(item.id), label: item.nombre }))])
const memberOptions = computed(() => [{ value: '', label: 'Seleccioná un socio' }, ...schedule.options.members.map((item) => ({ value: String(item.id), label: `${item.nombre} · ${item.numero_socio}` }))])

function today() { return new Date().toISOString().slice(0, 10) }
function plusDays(days) { const date = new Date(); date.setDate(date.getDate() + days); return date.toISOString().slice(0, 10) }
function localValue(value) { return String(value || '').replace(' ', 'T').slice(0, 16) }
function apiDateTime(value) { return String(value || '').replace('T', ' ') }
function sessionDate(value) { return new Intl.DateTimeFormat('es-UY', { weekday: 'short', day: '2-digit', month: 'short', timeZone: 'America/Montevideo' }).format(new Date(`${String(value).replace(' ', 'T')}-03:00`)) }
function sessionTime(value) { return String(value || '').slice(11, 16) }
function statusTone(status) { return ({ programada: 'info', cancelada: 'danger', finalizada: 'success' })[status] || 'neutral' }
function bookingTone(status) { return ({ confirmada: 'success', lista_espera: 'warning', cancelada: 'neutral', asistio: 'success', no_asistio: 'danger' })[status] || 'neutral' }
function bookingLabel(status) { return ({ confirmada: 'Confirmada', lista_espera: 'En espera', cancelada: 'Cancelada', asistio: 'Asistió', no_asistio: 'No asistió' })[status] || status }
function attendanceLabel(status) { return ({ presente: 'Presente', ausente: 'Ausente', tarde: 'Llegó tarde', justificada: 'Ausencia justificada' })[status] || status }

async function load() { await schedule.loadAdmin(filters) }
async function bootstrap() {
  try { await Promise.all([schedule.loadOptions(), load()]) }
  catch (error) { notice.value = { tone: 'danger', title: 'No se pudo preparar la agenda', text: error.message } }
}
function applyFilters() { filters.page = 1; filtersExpanded.value = false; load() }
function resetFilters() { Object.assign(filters, { q: '', status: '', from: today(), to: plusDays(35), page: 1, per_page: 20 }); filtersExpanded.value = false; load() }
function changePage(page) { filters.page = page; load() }

function openCreate() {
  Object.assign(classForm, { nombre: '', descripcion: '', instructor_id: '', sede_id: '', first_date: today(), hora_inicio: '18:00', hora_fin: '19:00', cupo_maximo: 16, repeat_weeks: 6, cancelacion_minutos: 120, color: '#0D7A56', notas: '' })
  formErrors.value = {}
  classDrawer.value = true
}
async function submitClass() {
  saving.value = true; formErrors.value = {}
  try {
    const result = await schedule.createClass({ ...classForm, instructor_id: Number(classForm.instructor_id), sede_id: Number(classForm.sede_id), cupo_maximo: Number(classForm.cupo_maximo), repeat_weeks: Number(classForm.repeat_weeks), cancelacion_minutos: Number(classForm.cancelacion_minutos) })
    classDrawer.value = false
    notice.value = { tone: 'success', title: 'Clase creada', text: `Se generaron ${result.sessions_created} sesiones en la agenda.` }
    await Promise.all([schedule.loadOptions(), load()])
  } catch (error) { formErrors.value = error.fields || {}; notice.value = { tone: 'danger', title: 'No se pudo crear la clase', text: error.message } }
  finally { saving.value = false }
}
function openEdit(session) {
  selectedSession.value = session
  Object.assign(sessionForm, { instructor_id: String(session.instructor_id), sede_id: String(session.sede_id), inicio_en: localValue(session.inicio_en), fin_en: localValue(session.fin_en), cupo_maximo: session.cupo_maximo, notas: session.notas || '' })
  formErrors.value = {}
  sessionDrawer.value = true
}
async function submitSession() {
  saving.value = true; formErrors.value = {}
  try {
    await schedule.updateSession(selectedSession.value.id, { ...sessionForm, instructor_id: Number(sessionForm.instructor_id), sede_id: Number(sessionForm.sede_id), cupo_maximo: Number(sessionForm.cupo_maximo), inicio_en: apiDateTime(sessionForm.inicio_en), fin_en: apiDateTime(sessionForm.fin_en) })
    sessionDrawer.value = false; notice.value = { tone: 'success', title: 'Sesión actualizada', text: 'El horario y el cupo quedaron guardados.' }; await load()
  } catch (error) { formErrors.value = error.fields || {}; notice.value = { tone: 'danger', title: 'No se pudo actualizar', text: error.message } }
  finally { saving.value = false }
}
function openCancel(session) { selectedSession.value = session; cancelReason.value = ''; cancelDialog.value = true }
async function confirmCancel() {
  saving.value = true
  try { await schedule.cancelSession(selectedSession.value.id, cancelReason.value); cancelDialog.value = false; notice.value = { tone: 'success', title: 'Sesión cancelada', text: 'Las reservas activas también fueron canceladas.' }; await load() }
  catch (error) { notice.value = { tone: 'danger', title: 'No se pudo cancelar', text: error.message } }
  finally { saving.value = false }
}
async function openRoster(session) {
  selectedSession.value = session; rosterDialog.value = true
  try { await schedule.loadRoster(session.id) }
  catch (error) { notice.value = { tone: 'danger', title: 'No se pudo abrir la lista', text: error.message } }
}
function openBooking() { memberId.value = ''; rosterDialog.value = false; bookingDialog.value = true }
async function submitBooking() {
  saving.value = true
  try { const result = await schedule.adminBook(selectedSession.value.id, memberId.value); bookingDialog.value = false; notice.value = { tone: result.booking.estado === 'lista_espera' ? 'warning' : 'success', title: result.booking.estado === 'lista_espera' ? 'Socio en lista de espera' : 'Reserva confirmada', text: 'La capacidad de la sesión se actualizó automáticamente.' }; await Promise.all([schedule.loadRoster(selectedSession.value.id), load()]); rosterDialog.value = true }
  catch (error) { notice.value = { tone: 'danger', title: 'No se pudo reservar', text: error.message } }
  finally { saving.value = false }
}
async function mark(item, state) {
  saving.value = true
  try { await schedule.markAttendance(item.id, state); notice.value = { tone: 'success', title: 'Asistencia registrada', text: `${item.usuario_nombre}: ${state}.` }; await schedule.loadRoster(selectedSession.value.id) }
  catch (error) { notice.value = { tone: 'danger', title: 'No se pudo registrar', text: error.message } }
  finally { saving.value = false }
}
async function cancelBooking(item) {
  saving.value = true
  try { const result = await schedule.adminCancel(item.id, 'Cancelada desde la lista administrativa'); notice.value = { tone: 'success', title: 'Reserva cancelada', text: result.promoted ? 'Se promovió automáticamente al siguiente socio en espera.' : 'El cupo quedó actualizado.' }; await Promise.all([schedule.loadRoster(selectedSession.value.id), load()]) }
  catch (error) { notice.value = { tone: 'danger', title: 'No se pudo cancelar', text: error.message } }
  finally { saving.value = false }
}

watch(() => admin.version, bootstrap)
onMounted(bootstrap)
onBeforeUnmount(() => schedule.reset())
</script>

<template>
  <section class="schedule-page">
    <AdminPageHeading :title="title" :description="description">
      <template #actions><AppButton v-if="admin.hasPermission('classes.write')" @click="openCreate"><template #icon><IconCalendarPlus :size="18" /></template>Nueva clase</AppButton></template>
    </AdminPageHeading>
    <AppAlert v-if="notice" :tone="notice.tone" :title="notice.title" class="notice"><p>{{ notice.text }}</p></AppAlert>

    <section class="summary-strip" aria-label="Resumen del período">
      <div><IconClock :size="19" /><span>Sesiones</span><strong>{{ schedule.pagination.total }}</strong></div>
      <div><IconUsers :size="19" /><span>Reservas confirmadas</span><strong>{{ schedule.totals.confirmed }}</strong></div>
      <div><IconCalendarPlus :size="19" /><span>Cupos visibles</span><strong>{{ schedule.totals.available }}</strong></div>
      <div><IconClipboardCheck :size="19" /><span>En espera</span><strong>{{ schedule.totals.waiting }}</strong></div>
    </section>

    <AppCard class="filters" :class="{ 'filters--expanded': filtersExpanded }">
      <AppButton class="filter-toggle" variant="secondary" block :aria-expanded="filtersExpanded" @click="filtersExpanded = !filtersExpanded"><template #icon><IconAdjustmentsHorizontal :size="18" /></template>{{ filtersExpanded ? 'Ocultar filtros' : 'Filtrar agenda' }}</AppButton>
      <form class="filter-grid" @submit.prevent="applyFilters">
        <AppInput v-model="filters.q" label="Buscar clase o entrenador" placeholder="Ej. Funcional" />
        <AppInput v-model="filters.from" label="Desde" type="date" />
        <AppInput v-model="filters.to" label="Hasta" type="date" />
        <AppSelect v-model="filters.status" label="Estado" :options="statusOptions" />
        <div class="filter-actions"><AppButton type="submit" size="sm" :loading="schedule.status === 'loading'">Aplicar</AppButton><AppButton type="button" variant="ghost" size="sm" @click="resetFilters">Limpiar</AppButton></div>
      </form>
    </AppCard>

    <div v-if="schedule.status === 'loading'" class="session-list" aria-label="Cargando agenda"><AppSkeleton v-for="item in 4" :key="item" height="8.5rem" /></div>
    <AppErrorState v-else-if="schedule.status === 'error'" :description="schedule.error" @retry="load" />
    <AppEmptyState v-else-if="!schedule.sessions.length" title="No hay sesiones en este período" description="Probá otro rango de fechas o creá una clase recurrente para generar la agenda."><template v-if="admin.hasPermission('classes.write')" #action><AppButton @click="openCreate">Crear clase</AppButton></template></AppEmptyState>
    <div v-else class="session-list">
      <AppCard v-for="session in schedule.sessions" :key="session.id" :padded="false" class="session-row">
        <div class="session-row__date"><strong>{{ sessionDate(session.inicio_en) }}</strong><span>{{ sessionTime(session.inicio_en) }}–{{ sessionTime(session.fin_en) }}</span></div>
        <div class="session-row__main"><div class="session-row__title"><i :style="{ background: session.color || '#0D7A56' }" /><div><h2>{{ session.nombre }}</h2><p>{{ session.instructor_nombre }} · {{ session.sede_nombre || 'Sede principal' }}</p></div></div><div class="session-row__badges"><AppBadge :tone="statusTone(session.estado)">{{ session.estado }}</AppBadge><AppBadge v-if="session.espera_total" tone="warning">{{ session.espera_total }} en espera</AppBadge></div></div>
        <div class="capacity"><div><span>{{ session.cupos_reservados }} / {{ session.cupo_maximo }}</span><small>cupos confirmados</small></div><progress :value="session.cupos_reservados" :max="session.cupo_maximo">{{ session.cupos_reservados }} de {{ session.cupo_maximo }}</progress></div>
        <div class="session-row__actions"><AppButton v-if="admin.hasPermission('reservations.read')" variant="secondary" size="sm" @click="openRoster(session)">{{ isReservations ? 'Gestionar lista' : 'Ver lista' }}</AppButton><AppButton v-if="session.estado === 'programada' && admin.hasPermission('classes.write')" variant="ghost" size="sm" @click="openEdit(session)">Editar</AppButton><AppButton v-if="session.estado === 'programada' && admin.hasPermission('classes.write')" variant="ghost" size="sm" @click="openCancel(session)">Cancelar</AppButton></div>
      </AppCard>
    </div>
    <nav v-if="schedule.pagination.total_pages > 1" class="pagination" aria-label="Paginación"><AppButton variant="secondary" size="sm" :disabled="filters.page <= 1" @click="changePage(filters.page - 1)"><template #icon><IconChevronLeft :size="17" /></template>Anterior</AppButton><span>Página {{ filters.page }} de {{ schedule.pagination.total_pages }}</span><AppButton variant="secondary" size="sm" :disabled="filters.page >= schedule.pagination.total_pages" @click="changePage(filters.page + 1)">Siguiente<template #icon><IconChevronRight :size="17" /></template></AppButton></nav>

    <AppDrawer :open="classDrawer" title="Nueva clase recurrente" @close="classDrawer = false"><form id="class-form" class="form-stack" @submit.prevent="submitClass"><AppInput v-model="classForm.nombre" label="Nombre" required :error="formErrors.nombre" /><AppTextarea v-model="classForm.descripcion" label="Descripción" :error="formErrors.descripcion" /><div class="form-pair"><AppSelect v-model="classForm.instructor_id" label="Entrenador" :options="trainerOptions" :error="formErrors.instructor_id" /><AppSelect v-model="classForm.sede_id" label="Sede" :options="locationOptions" :error="formErrors.sede_id" /></div><div class="form-pair"><AppInput v-model="classForm.first_date" label="Primera fecha" type="date" required :error="formErrors.first_date" /><AppInput v-model="classForm.repeat_weeks" label="Semanas" type="number" required :error="formErrors.repeat_weeks" /></div><div class="form-pair"><AppInput v-model="classForm.hora_inicio" label="Inicio" type="time" required :error="formErrors.hora_inicio" /><AppInput v-model="classForm.hora_fin" label="Fin" type="time" required :error="formErrors.hora_fin" /></div><div class="form-pair"><AppInput v-model="classForm.cupo_maximo" label="Cupo máximo" type="number" required :error="formErrors.cupo_maximo" /><AppInput v-model="classForm.cancelacion_minutos" label="Cancelación (minutos)" type="number" required :error="formErrors.cancelacion_minutos" /></div><AppTextarea v-model="classForm.notas" label="Notas operativas" :error="formErrors.notas" /></form><template #footer><AppButton variant="ghost" @click="classDrawer = false">Cerrar</AppButton><AppButton type="submit" form="class-form" :loading="saving">Crear clase</AppButton></template></AppDrawer>

    <AppDrawer :open="sessionDrawer" title="Editar sesión" @close="sessionDrawer = false"><form id="session-form" class="form-stack" @submit.prevent="submitSession"><AppSelect v-model="sessionForm.instructor_id" label="Entrenador" :options="trainerOptions" :error="formErrors.instructor_id" /><AppSelect v-model="sessionForm.sede_id" label="Sede" :options="locationOptions" :error="formErrors.sede_id" /><AppInput v-model="sessionForm.inicio_en" label="Inicio" type="datetime-local" required :error="formErrors.inicio_en" /><AppInput v-model="sessionForm.fin_en" label="Fin" type="datetime-local" required :error="formErrors.fin_en" /><AppInput v-model="sessionForm.cupo_maximo" label="Cupo máximo" type="number" required :error="formErrors.cupo_maximo" /><AppTextarea v-model="sessionForm.notas" label="Notas operativas" :error="formErrors.notas" /></form><template #footer><AppButton variant="ghost" @click="sessionDrawer = false">Cerrar</AppButton><AppButton type="submit" form="session-form" :loading="saving">Guardar cambios</AppButton></template></AppDrawer>

    <AppDialog :open="cancelDialog" title="Cancelar esta sesión" description="Las reservas confirmadas y la lista de espera se cancelarán. La acción quedará auditada." @close="cancelDialog = false"><AppTextarea v-model="cancelReason" label="Motivo de cancelación" :rows="3" hint="Mínimo 8 caracteres." /><template #footer><AppButton variant="ghost" @click="cancelDialog = false">Volver</AppButton><AppButton variant="danger" :disabled="cancelReason.trim().length < 8" :loading="saving" @click="confirmCancel">Confirmar cancelación</AppButton></template></AppDialog>

    <AppDialog :open="rosterDialog" :title="schedule.roster.session?.nombre || 'Lista de la sesión'" :description="schedule.roster.session ? `${sessionDate(schedule.roster.session.inicio_en)} · ${sessionTime(schedule.roster.session.inicio_en)}` : ''" @close="rosterDialog = false"><div v-if="schedule.rosterStatus === 'loading'" class="roster-loading"><AppSkeleton v-for="item in 3" :key="item" height="4.5rem" /></div><AppErrorState v-else-if="schedule.rosterStatus === 'error'" :description="schedule.rosterError" @retry="schedule.loadRoster(selectedSession.id)" /><template v-else><div class="roster-toolbar"><p>{{ schedule.roster.items.length }} registros</p><AppButton v-if="schedule.roster.session?.estado === 'programada' && admin.hasPermission('reservations.write')" size="sm" @click="openBooking">Agregar socio</AppButton></div><AppEmptyState v-if="!schedule.roster.items.length" title="La lista está vacía" description="Todavía no hay reservas ni socios en espera para esta sesión." /><ul v-else class="roster"><li v-for="item in schedule.roster.items" :key="item.id"><div><strong>{{ item.usuario_nombre }}</strong><span>{{ item.numero_socio || item.email }}</span><AppBadge :tone="bookingTone(item.estado)">{{ item.asistencia_estado ? attendanceLabel(item.asistencia_estado) : bookingLabel(item.estado) }}<template v-if="item.posicion_espera"> #{{ item.posicion_espera }}</template></AppBadge></div><div v-if="['confirmada','asistio','no_asistio'].includes(item.estado)" class="roster__actions"><template v-if="admin.hasPermission('attendance.write') && schedule.roster.session?.asistencia_habilitada"><AppButton size="sm" variant="secondary" :disabled="saving" @click="mark(item, 'presente')">Presente</AppButton><AppButton size="sm" variant="ghost" :disabled="saving" @click="mark(item, 'tarde')">Tarde</AppButton><AppButton size="sm" variant="ghost" :disabled="saving" @click="mark(item, 'ausente')">Ausente</AppButton><AppButton size="sm" variant="ghost" :disabled="saving" @click="mark(item, 'justificada')">Justificada</AppButton></template><span v-else-if="admin.hasPermission('attendance.write')" class="roster__hint">Asistencia disponible 30 min antes</span><AppButton v-if="item.estado === 'confirmada' && admin.hasPermission('reservations.write')" size="sm" variant="ghost" :disabled="saving" @click="cancelBooking(item)">Cancelar reserva</AppButton></div></li></ul></template><template #footer><AppButton variant="secondary" @click="rosterDialog = false">Cerrar</AppButton></template></AppDialog>

    <AppDialog :open="bookingDialog" title="Agregar socio" description="Si no quedan cupos, la reserva ingresará automáticamente a la lista de espera." @close="bookingDialog = false"><AppSelect v-model="memberId" label="Socio activo" :options="memberOptions" /><template #footer><AppButton variant="ghost" @click="bookingDialog = false">Volver</AppButton><AppButton :disabled="!memberId" :loading="saving" @click="submitBooking">Registrar reserva</AppButton></template></AppDialog>
  </section>
</template>

<style scoped>
.schedule-page { min-width: 0; }.notice { margin-bottom: var(--space-6); }.notice p { margin: 0; }.summary-strip { display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); margin-bottom: var(--space-5); overflow: hidden; border: 1px solid var(--border-subtle); border-radius: var(--radius-card); background: var(--surface-1); }.summary-strip > div { display: grid; grid-template-columns: auto 1fr auto; min-height: 4.5rem; align-items: center; gap: var(--space-2); padding: var(--space-4); border-right: 1px solid var(--border-subtle); }.summary-strip > div:last-child { border-right: 0; }.summary-strip svg { color: var(--info); }.summary-strip span { color: var(--text-secondary); font-size: .76rem; }.summary-strip strong { font-size: 1.25rem; }.filters { margin-bottom: var(--space-5); }.filter-toggle { display: none; }.filter-grid { display: grid; grid-template-columns: minmax(12rem, 1.5fr) repeat(3, minmax(8rem, 1fr)) auto; align-items: end; gap: var(--space-3); }.filter-actions { display: flex; gap: var(--space-2); }.session-list { display: grid; gap: var(--space-3); }.session-row { display: grid; grid-template-columns: 9rem minmax(13rem, 1.5fr) minmax(10rem, .7fr) auto; align-items: center; gap: var(--space-5); padding: var(--space-4); }.session-row__date { display: grid; gap: var(--space-1); }.session-row__date strong { text-transform: capitalize; }.session-row__date span, .session-row__main p, .capacity small { color: var(--text-secondary); font-size: .78rem; }.session-row__main { display: flex; min-width: 0; align-items: center; justify-content: space-between; gap: var(--space-3); }.session-row__title { display: flex; min-width: 0; align-items: center; gap: var(--space-3); }.session-row__title i { flex: 0 0 auto; width: .3rem; height: 2.5rem; border-radius: var(--radius-pill); }.session-row__title h2 { overflow: hidden; margin: 0; font-size: .98rem; text-overflow: ellipsis; white-space: nowrap; }.session-row__main p { overflow: hidden; margin: var(--space-1) 0 0; text-overflow: ellipsis; white-space: nowrap; }.session-row__badges { display: flex; flex-wrap: wrap; gap: var(--space-2); }.capacity { display: grid; gap: var(--space-2); }.capacity > div { display: flex; justify-content: space-between; gap: var(--space-2); }.capacity progress { width: 100%; height: .4rem; accent-color: var(--accent); }.session-row__actions { display: flex; flex-wrap: wrap; justify-content: flex-end; gap: var(--space-1); }.pagination { display: flex; align-items: center; justify-content: center; gap: var(--space-4); margin-top: var(--space-6); color: var(--text-secondary); font-size: .8rem; }.form-stack { display: grid; gap: var(--space-5); }.form-pair { display: grid; grid-template-columns: 1fr 1fr; gap: var(--space-3); }.roster-loading { display: grid; gap: var(--space-3); }.roster-toolbar { display: flex; align-items: center; justify-content: space-between; gap: var(--space-3); margin-bottom: var(--space-4); }.roster-toolbar p { margin: 0; color: var(--text-secondary); font-size: .82rem; }.roster { display: grid; gap: var(--space-2); margin: 0; padding: 0; list-style: none; }.roster li { display: flex; align-items: center; justify-content: space-between; gap: var(--space-4); border: 1px solid var(--border-subtle); border-radius: var(--radius-control); padding: var(--space-3); }.roster li > div:first-child { display: flex; min-width: 10rem; flex-wrap: wrap; align-items: center; gap: var(--space-2); }.roster li span:not(.badge) { width: 100%; color: var(--text-tertiary); font-size: .74rem; }.roster__actions { display: flex; flex-wrap: wrap; justify-content: flex-end; gap: var(--space-1); }.roster__hint { align-self: center; color: var(--text-tertiary); font-size: .72rem; }
@media (max-width: 75rem) { .filter-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); }.filter-actions { grid-column: 1 / -1; }.session-row { grid-template-columns: 8rem minmax(12rem, 1fr) minmax(9rem, .7fr); }.session-row__actions { grid-column: 2 / -1; justify-content: flex-start; } }
@media (max-width: 63.99rem) { .summary-strip { grid-template-columns: repeat(2, minmax(0, 1fr)); }.summary-strip > div:nth-child(2) { border-right: 0; }.summary-strip > div:nth-child(-n+2) { border-bottom: 1px solid var(--border-subtle); }.session-row { grid-template-columns: 7.5rem minmax(0, 1fr); }.capacity, .session-row__actions { grid-column: 2; }.session-row__main { align-items: flex-start; flex-direction: column; } }
@media (max-width: 39.99rem) { .filters { padding: var(--space-3); }.filter-toggle { display: inline-flex; }.filter-grid { display: none; grid-template-columns: 1fr; margin-top: var(--space-4); }.filters--expanded .filter-grid { display: grid; }.form-pair { grid-template-columns: 1fr; }.summary-strip > div { grid-template-columns: auto 1fr; }.summary-strip strong { grid-column: 2; }.filter-actions { grid-column: auto; }.filter-actions > * { flex: 1; }.session-row { grid-template-columns: 1fr; gap: var(--space-3); }.capacity, .session-row__actions { grid-column: auto; }.session-row__actions > * { flex: 1; }.roster li { align-items: flex-start; flex-direction: column; }.roster__actions { width: 100%; justify-content: flex-start; }.roster__actions > * { flex: 1 1 7rem; } }
</style>
