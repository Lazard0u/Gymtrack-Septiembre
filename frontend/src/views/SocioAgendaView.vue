<script setup>
import { computed, onBeforeUnmount, onMounted, reactive, ref } from 'vue'
import { IconBrandGoogle, IconCalendarEvent, IconClock, IconDownload, IconMapPin, IconUsers } from '@tabler/icons-vue'
import { useAuthStore } from '../stores/auth'
import { useNotificacionesStore } from '../stores/notificaciones'
import { useAgendaStore } from '../stores/agenda'
import AppAlert from '../components/ui/AppAlert.vue'
import AppBadge from '../components/ui/AppBadge.vue'
import AppButton from '../components/ui/AppButton.vue'
import AppCard from '../components/ui/AppCard.vue'
import AppDialog from '../components/ui/AppDialog.vue'
import AppEmptyState from '../components/ui/AppEmptyState.vue'
import AppErrorState from '../components/ui/AppErrorState.vue'
import AppLinkButton from '../components/ui/AppLinkButton.vue'
import AppSelect from '../components/ui/AppSelect.vue'
import AppSkeleton from '../components/ui/AppSkeleton.vue'
import PublicFooter from '../components/public/PublicFooter.vue'
import SocioBottomNav from '../components/member/SocioBottomNav.vue'
import SocioPageHeader from '../components/member/SocioPageHeader.vue'
import SocioTopNav from '../components/member/SocioTopNav.vue'

const auth = useAuthStore()
const notifications = useNotificacionesStore()
const schedule = useAgendaStore()
const notice = ref(null)
const workingId = ref(null)
const calendarWorking = ref(null)
const cancelTarget = ref(null)
const filters = reactive({ from: today(), to: plusDays(45), status: 'programada', page: 1, per_page: 30 })
const contexts = computed(() => (auth.user?.gimnasios || []).filter((gym) => gym.rol_nombre === 'socio'))
const contextOptions = computed(() => [{ value: '', label: 'Seleccioná un gimnasio' }, ...contexts.value.map((gym) => ({ value: String(gym.gimnasio_id), label: gym.nombre }))])
const hasContext = computed(() => Boolean(auth.user?.active_gym_id))
const upcomingBookings = computed(() => schedule.bookings.filter((booking) => ['confirmada', 'lista_espera'].includes(booking.estado) && booking.sesion_estado === 'programada'))

function today() { return new Date().toISOString().slice(0, 10) }
function plusDays(days) { const date = new Date(); date.setDate(date.getDate() + days); return date.toISOString().slice(0, 10) }
function parseLocal(value) { return new Date(String(value).replace(' ', 'T')) }
function day(value, timezone = 'America/Montevideo') { return new Intl.DateTimeFormat('es-UY', { weekday: 'long', day: 'numeric', month: 'long', timeZone: timezone }).format(parseLocal(value)) }
function time(value) { return String(value || '').slice(11, 16) }
function sessionTimeDuration(session) {
  const start = new Date(`1970-01-01T${String(session.inicio_en).slice(11, 16)}:00`)
  const end = new Date(`1970-01-01T${String(session.fin_en).slice(11, 16)}:00`)
  return `${Math.max(0, Math.round((end - start) / 60000))} min`
}
function tone(status) { return ({ confirmada: 'success', lista_espera: 'warning', cancelada: 'neutral', asistio: 'success', no_asistio: 'danger' })[status] || 'neutral' }
function stateLabel(status, position) { if (status === 'lista_espera') return `En espera · puesto ${position}`; return ({ confirmada: 'Reserva confirmada', cancelada: 'Cancelada', asistio: 'Asistió', no_asistio: 'No asistió' })[status] || status }
function actionLabel(session) { if (session.reserva_estado === 'lista_espera') return `En espera #${session.posicion_espera}`; if (session.reserva_estado === 'confirmada') return 'Reserva confirmada'; if (session.cupos_disponibles <= 0) return 'Sumarme a la espera'; return 'Reservar lugar' }

async function load() {
  if (!hasContext.value) return
  await Promise.allSettled([schedule.loadMember(filters), schedule.loadMyBookings()])
}
async function switchGym(value) {
  if (!value) return
  try {
    await auth.cambiarGimnasio(Number(value))
    notifications.reset()
    await notifications.load()
    notice.value = null
    await load()
  }
  catch (error) { notice.value = { tone: 'danger', title: 'No se pudo cambiar el gimnasio', text: error.message } }
}
async function book(session) {
  workingId.value = session.id
  try {
    const result = await schedule.memberBook(session.id)
    notice.value = { tone: result.booking.estado === 'lista_espera' ? 'warning' : 'success', title: result.booking.estado === 'lista_espera' ? 'Ingresaste a la lista de espera' : 'Lugar reservado', text: result.booking.estado === 'lista_espera' ? `Tu posición actual es ${result.booking.posicion_espera}. Si se libera un cupo, la reserva se confirmará automáticamente.` : 'Tu reserva ya aparece en “Próximas reservas”.' }
    await load()
  } catch (error) { notice.value = { tone: 'danger', title: 'No se pudo reservar', text: error.message } }
  finally { workingId.value = null }
}
async function cancelBooking() {
  if (!cancelTarget.value) return
  workingId.value = cancelTarget.value.id
  try { const result = await schedule.memberCancel(cancelTarget.value.id); notice.value = { tone: 'success', title: 'Reserva cancelada', text: result.promoted ? 'Tu lugar fue asignado al siguiente socio en espera.' : 'El cupo quedó liberado.' }; cancelTarget.value = null; await load() }
  catch (error) { notice.value = { tone: 'danger', title: 'No se pudo cancelar', text: error.message }; cancelTarget.value = null }
  finally { workingId.value = null }
}
async function addToGoogle(booking) {
  calendarWorking.value = `google-${booking.id}`
  try {
    const event = await schedule.calendar(booking.id)
    const url = event.google_calendar_url || event.google_url
    if (!url) throw new Error('El servidor no devolvió un enlace de calendario válido.')
    window.open(url, '_blank', 'noopener,noreferrer')
    notice.value = { tone: 'info', title: 'Evento preparado', text: 'Google Calendar se abrió con los datos reales de tu reserva. Los cambios posteriores no se sincronizan automáticamente.' }
  } catch (error) { notice.value = { tone: 'danger', title: 'No se pudo preparar el evento', text: error.message } }
  finally { calendarWorking.value = null }
}
async function downloadIcs(booking) {
  calendarWorking.value = `ics-${booking.id}`
  try {
    await schedule.downloadIcs(booking.id)
    notice.value = { tone: 'success', title: 'Archivo ICS descargado', text: 'Podés importarlo en tu aplicación de calendario preferida.' }
  } catch (error) { notice.value = { tone: 'danger', title: 'No se pudo descargar el calendario', text: error.message } }
  finally { calendarWorking.value = null }
}
onMounted(load)
onBeforeUnmount(() => schedule.reset())
</script>

<template>
  <div class="member-shell">
    <SocioTopNav />
    <main class="container member-main">
      <SocioPageHeader title="Agenda" description="Consultá cupos reales, reservá y seguí tu posición si una sesión está completa." />
      <AppAlert v-if="notice" :tone="notice.tone" :title="notice.title" class="notice"><p>{{ notice.text }}</p></AppAlert>
      <AppCard v-if="contexts.length" class="context-card"><AppSelect :model-value="String(auth.user?.active_gym_id || '')" label="Gimnasio" :options="contextOptions" hint="La agenda y tus reservas se aíslan por gimnasio." @update:model-value="switchGym" /></AppCard>
      <AppEmptyState v-if="!contexts.length" title="No tenés un gimnasio asociado" description="Necesitás una asociación activa como socio para consultar clases y reservar."><template #action><AppLinkButton :to="{ name: 'gyms' }">Explorar gimnasios</AppLinkButton></template></AppEmptyState>
      <AppEmptyState v-else-if="!hasContext" title="Elegí un gimnasio" description="Seleccioná arriba el contexto donde querés consultar clases y reservas." />
      <template v-else>
        <section class="bookings-section" aria-labelledby="bookings-title"><div class="section-heading"><div><h2 id="bookings-title">Próximas reservas</h2><p>Guardá una copia manual en tu calendario. Si cambia el horario, deberás actualizarla.</p></div></div><div v-if="schedule.bookingsStatus === 'loading'" class="booking-grid"><AppSkeleton v-for="item in 2" :key="item" height="10rem" /></div><AppErrorState v-else-if="schedule.bookingsStatus === 'error'" :description="schedule.bookingsError" @retry="schedule.loadMyBookings" /><AppEmptyState v-else-if="!upcomingBookings.length" title="Todavía no tenés reservas" description="Elegí una sesión de la agenda para asegurar tu lugar." /><div v-else class="booking-grid"><AppCard v-for="booking in upcomingBookings" :key="booking.id" class="booking-card"><div class="booking-card__top"><i :style="{ background: booking.color || 'var(--accent)' }" /><AppBadge :tone="tone(booking.estado)">{{ stateLabel(booking.estado, booking.posicion_espera) }}</AppBadge></div><h3>{{ booking.nombre }}</h3><p><IconCalendarEvent :size="17" />{{ day(booking.inicio_en, booking.zona_horaria) }} · {{ time(booking.inicio_en) }}</p><p><IconMapPin :size="17" />{{ booking.sede_nombre || 'Sede principal' }}</p><p><IconUsers :size="17" />{{ booking.instructor_nombre }}</p><div class="booking-card__actions"><AppButton variant="secondary" :loading="calendarWorking === `google-${booking.id}`" :disabled="booking.estado !== 'confirmada'" @click="addToGoogle(booking)"><template #icon><IconBrandGoogle :size="17" /></template>Agregar a Google Calendar</AppButton><AppButton variant="ghost" :loading="calendarWorking === `ics-${booking.id}`" :disabled="booking.estado !== 'confirmada'" @click="downloadIcs(booking)"><template #icon><IconDownload :size="17" /></template>Descargar ICS</AppButton><AppButton variant="ghost" :loading="workingId === booking.id" @click="cancelTarget = booking">Cancelar reserva</AppButton></div></AppCard></div></section>

        <section class="agenda-section" aria-labelledby="agenda-title"><div class="section-heading"><h2 id="agenda-title">Agenda disponible</h2><span>{{ schedule.pagination.total }} sesiones · próximos 45 días</span></div><div v-if="schedule.status === 'loading'" class="agenda-list"><AppSkeleton v-for="item in 4" :key="item" height="9rem" /></div><AppErrorState v-else-if="schedule.status === 'error'" :description="schedule.error" @retry="load" /><AppEmptyState v-else-if="!schedule.sessions.length" title="No hay clases programadas" description="Este gimnasio todavía no publicó sesiones en el período consultado." /><div v-else class="agenda-list"><AppCard v-for="session in schedule.sessions" :key="session.id" :padded="false" class="agenda-card"><div class="agenda-card__date"><span>{{ day(session.inicio_en, session.zona_horaria) }}</span><strong>{{ time(session.inicio_en) }}</strong><small>{{ time(session.fin_en) }}</small></div><div class="agenda-card__main"><div><i :style="{ background: session.color || 'var(--accent)' }" /><h3>{{ session.nombre }}</h3></div><p>{{ session.instructor_nombre }} · {{ session.sede_nombre || 'Sede principal' }}</p><div class="agenda-card__facts"><span><IconClock :size="16" />{{ sessionTimeDuration(session) }}</span><span><IconUsers :size="16" />{{ session.cupos_disponibles }} cupos</span><AppBadge v-if="session.espera_total" tone="warning">{{ session.espera_total }} en espera</AppBadge></div></div><div class="agenda-card__action"><AppButton :variant="session.reserva_id ? 'secondary' : 'primary'" :disabled="Boolean(session.reserva_id) || session.estado !== 'programada'" :loading="workingId === session.id" @click="book(session)">{{ actionLabel(session) }}</AppButton></div></AppCard></div></section>
      </template>
    </main>
    <PublicFooter />
    <SocioBottomNav />
    <AppDialog :open="Boolean(cancelTarget)" title="Cancelar tu reserva" description="Si la sesión tiene lista de espera, tu lugar se asignará automáticamente al siguiente socio." @close="cancelTarget = null"><p class="cancel-copy">Podés volver a reservar mientras siga abierto el plazo y haya disponibilidad.</p><template #footer><AppButton variant="ghost" @click="cancelTarget = null">Mantener reserva</AppButton><AppButton variant="danger" :loading="Boolean(workingId)" @click="cancelBooking">Cancelar reserva</AppButton></template></AppDialog>
  </div>
</template>

<style scoped>
.member-shell { min-height: 100vh; background: var(--bg-canvas); }.member-main { min-height: 75vh; padding-bottom: var(--space-20); }.notice { margin-bottom: var(--space-5); }.notice p, .cancel-copy { margin: 0; }.context-card { width: min(100%, 30rem); margin-bottom: var(--space-10); padding: var(--space-4); }.bookings-section, .agenda-section { padding-block: var(--space-10); border-top: 1px solid var(--border-subtle); }.section-heading { display: flex; align-items: flex-end; justify-content: space-between; gap: var(--space-5); margin-bottom: var(--space-6); }.section-heading h2 { margin: 0; }.section-heading p { max-width: 44rem; margin: var(--space-2) 0 0; color: var(--text-tertiary); font-size: .78rem; }.section-heading > span { color: var(--text-secondary); font-size: .8rem; }.booking-grid { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: var(--space-3); }.booking-card { position: relative; display: grid; min-width: 0; gap: var(--space-3); }.booking-card__top { display: flex; align-items: center; justify-content: space-between; gap: var(--space-3); }.booking-card__top i { width: 2rem; height: .3rem; border-radius: var(--radius-pill); }.booking-card h3 { margin: var(--space-2) 0 0; }.booking-card p { display: flex; min-width: 0; align-items: center; gap: var(--space-2); margin: 0; color: var(--text-secondary); font-size: .82rem; overflow-wrap: anywhere; }.booking-card__actions { display: flex; flex-wrap: wrap; align-items: center; gap: var(--space-2); margin-top: var(--space-2); }.agenda-list { display: grid; gap: var(--space-3); }.agenda-card { display: grid; grid-template-columns: 10rem minmax(0, 1fr) auto; align-items: center; gap: var(--space-6); padding: var(--space-5); }.agenda-card__date { display: grid; gap: var(--space-1); text-transform: capitalize; }.agenda-card__date span { color: var(--text-secondary); font-size: .78rem; }.agenda-card__date strong { font-size: 1.45rem; }.agenda-card__date small { color: var(--text-tertiary); }.agenda-card__main > div { display: flex; align-items: center; gap: var(--space-2); }.agenda-card__main i { width: .3rem; height: 1.6rem; border-radius: var(--radius-pill); }.agenda-card h3 { margin: 0; font-size: 1.05rem; }.agenda-card p { margin: var(--space-2) 0; color: var(--text-secondary); font-size: .82rem; }.agenda-card__facts { display: flex; flex-wrap: wrap; align-items: center; gap: var(--space-4); color: var(--text-tertiary); font-size: .76rem; }.agenda-card__facts > span { display: inline-flex; align-items: center; gap: var(--space-1); }
@media (max-width: 63.99rem) { .agenda-card { grid-template-columns: 8rem minmax(0, 1fr); }.agenda-card__action { grid-column: 2; }.agenda-card__action > * { width: 100%; } }
@media (max-width: 47.99rem) { .booking-grid { grid-template-columns: 1fr; }.booking-card__actions { display: grid; grid-template-columns: 1fr; }.booking-card__actions > * { width: 100%; }.agenda-card { grid-template-columns: 1fr; gap: var(--space-3); }.agenda-card__date { grid-template-columns: 1fr auto auto; align-items: baseline; }.agenda-card__action { grid-column: auto; }.section-heading { align-items: flex-start; }.agenda-card__facts { gap: var(--space-3); } }
</style>
