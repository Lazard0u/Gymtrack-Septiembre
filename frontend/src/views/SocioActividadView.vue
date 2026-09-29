<script setup>
import { computed, onMounted, reactive, ref } from 'vue'
import { IconActivity, IconCalendarCheck, IconIdBadge2, IconPlus, IconRulerMeasure } from '@tabler/icons-vue'
import { useAuthStore } from '../stores/auth'
import { useSocioStore } from '../stores/socio'
import SocioBottomNav from '../components/member/SocioBottomNav.vue'
import SocioPageHeader from '../components/member/SocioPageHeader.vue'
import SocioProgresoRing from '../components/member/SocioProgresoRing.vue'
import SocioTopNav from '../components/member/SocioTopNav.vue'
import SocioPesoSparkline from '../components/member/SocioPesoSparkline.vue'
import PublicFooter from '../components/public/PublicFooter.vue'
import AppAlert from '../components/ui/AppAlert.vue'
import AppBadge from '../components/ui/AppBadge.vue'
import AppButton from '../components/ui/AppButton.vue'
import AppCard from '../components/ui/AppCard.vue'
import AppDrawer from '../components/ui/AppDrawer.vue'
import AppEmptyState from '../components/ui/AppEmptyState.vue'
import AppErrorState from '../components/ui/AppErrorState.vue'
import AppInput from '../components/ui/AppInput.vue'
import AppSelect from '../components/ui/AppSelect.vue'
import AppSkeleton from '../components/ui/AppSkeleton.vue'
import AppTextarea from '../components/ui/AppTextarea.vue'
import AppToast from '../components/ui/AppToast.vue'

const auth = useAuthStore()
const member = useSocioStore()
const contexts = computed(() => (auth.user?.gimnasios || []).filter((gym) => gym.rol_nombre === 'socio'))
const contextOptions = computed(() => [{ value: '', label: 'Seleccioná un gimnasio' }, ...contexts.value.map((gym) => ({ value: String(gym.gimnasio_id), label: gym.nombre }))])
const hasContext = computed(() => Boolean(auth.user?.active_gym_id))
const progress = computed(() => member.summary?.monthly_progress || { goal: 0, attended: 0, percentage: 0 })
const weightTrend = computed(() => [...member.measurements].reverse().map((item) => ({ weight_kg: item.weight_kg })))
function weightDelta(index) {
  const current = member.measurements[index]?.weight_kg
  const previous = member.measurements[index + 1]?.weight_kg
  if (current == null || previous == null) return null
  return Math.round((current - previous) * 10) / 10
}
const measurementOpen = ref(false)
const fields = ref({})
const formError = ref('')
const measurement = reactive({ measured_at: localDate(), height_cm: '', weight_kg: '', notes: '' })
const toast = reactive({ open: false, title: '', message: '', tone: 'success' })

function localDate() { const date = new Date(); date.setMinutes(date.getMinutes() - date.getTimezoneOffset()); return date.toISOString().slice(0,10) }
function notify(title, message = '', tone = 'success') { Object.assign(toast, { open: true, title, message, tone }) }
function date(value) { if (!value) return 'Sin fecha'; return new Intl.DateTimeFormat('es-UY', { dateStyle: 'medium' }).format(new Date(`${String(value).slice(0,10)}T12:00:00`)) }
function dateTime(value, timezone = 'America/Montevideo') { if (!value) return 'Sin fecha'; return new Intl.DateTimeFormat('es-UY', { dateStyle: 'medium', timeStyle: 'short', timeZone: timezone }).format(new Date(String(value).replace(' ', 'T'))) }
function money(value, currency = 'UYU') { return new Intl.NumberFormat('es-UY', { style: 'currency', currency }).format(Number(value || 0)) }
function tone(status) { return ({ activa: 'success', presente: 'success', tarde: 'warning', ausente: 'danger', pendiente_pago: 'warning', vencida: 'danger', suspendida: 'neutral' })[status] || 'neutral' }
function label(status) { return String(status || '').replaceAll('_', ' ').replace(/^./, (letter) => letter.toUpperCase()) }
async function load() {
  if (!hasContext.value) return
  await Promise.allSettled([member.loadSummary(), member.loadAttendance(), member.loadMemberships(), member.loadMeasurements(), member.loadPreferences()])
}
async function switchGym(value) {
  if (!value) return
  try { await auth.cambiarGimnasio(Number(value)); member.reset(); await load() }
  catch (error) { notify('No se pudo cambiar el gimnasio', error.message, 'danger') }
}
function openMeasurement() {
  if (!hasContext.value) return
  Object.assign(measurement, { measured_at: localDate(), height_cm: '', weight_kg: '', notes: '' })
  fields.value = {}
  formError.value = ''
  measurementOpen.value = true
}
async function saveMeasurement() {
  fields.value = {}
  formError.value = ''
  try {
    await member.createMeasurement({
      measured_at: measurement.measured_at,
      height_cm: measurement.height_cm === '' ? null : Number(measurement.height_cm),
      weight_kg: measurement.weight_kg === '' ? null : Number(measurement.weight_kg),
      notes: measurement.notes || null,
    })
    measurementOpen.value = false
    notify('Medición guardada', 'El IMC se muestra sólo como referencia cuando hay altura y peso.')
  } catch (error) { fields.value = error.fields || {}; formError.value = error.message }
}

onMounted(load)
</script>

<template>
  <div class="member-page">
    <SocioTopNav />
    <main class="container activity-main">
      <SocioPageHeader title="Progreso" description="Consultá tu objetivo mensual, membresías y mediciones opcionales sin interpretaciones clínicas.">
        <template v-if="hasContext" #actions><AppButton @click="openMeasurement"><template #icon><IconPlus :size="18" /></template>Registrar medición</AppButton></template>
      </SocioPageHeader>
      <AppCard v-if="contexts.length" class="context-card"><AppSelect :model-value="String(auth.user?.active_gym_id || '')" label="Gimnasio" :options="contextOptions" hint="Asistencia, membresías y mediciones se aíslan por gimnasio." @update:model-value="switchGym" /></AppCard>
      <AppEmptyState v-if="!contexts.length" title="No tenés un gimnasio asociado" description="Necesitás una asociación activa como socio para consultar tu progreso." />
      <AppEmptyState v-else-if="!hasContext" title="Elegí un gimnasio" description="Seleccioná el contexto donde querés consultar tu actividad." />
      <template v-else>
        <section class="progress-summary" aria-labelledby="monthly-progress-title">
          <div v-if="member.summaryStatus === 'loading'" class="progress-loading"><AppSkeleton height="15rem" /><AppSkeleton height="15rem" /></div>
          <AppErrorState v-else-if="member.summaryStatus === 'error'" :description="member.error" @retry="member.loadSummary" />
          <template v-else>
            <div class="progress-number">
              <div class="progress-number__ring">
                <SocioProgresoRing :percentage="progress.percentage" :size="104" :stroke="10" />
                <div class="progress-number__ring-label"><strong>{{ progress.attended }}</strong><span>/{{ progress.goal }}</span></div>
              </div>
              <div class="progress-number__copy">
                <span id="monthly-progress-title"><IconActivity :size="16" aria-hidden="true" />Asistencias este mes</span>
                <p>{{ progress.percentage }}% de tu objetivo. El conteo usa asistencias marcadas por el gimnasio.</p>
              </div>
            </div>
            <div class="next-session"><span>Próxima clase</span><template v-if="member.summary?.next_class"><strong>{{ member.summary.next_class.nombre }}</strong><p>{{ dateTime(member.summary.next_class.inicio_en, member.summary.next_class.zona_horaria) }}</p><AppBadge :tone="member.summary.next_class.reserva_estado === 'confirmada' ? 'success' : 'warning'">{{ label(member.summary.next_class.reserva_estado) }}</AppBadge></template><template v-else><strong>Sin reserva próxima</strong><p>Tu próxima reserva aparecerá acá.</p></template></div>
          </template>
        </section>

        <section class="activity-section" aria-labelledby="attendance-title">
          <header><div><h2 id="attendance-title">Historial de asistencia</h2><p>Registros confirmados por el personal del gimnasio.</p></div><IconCalendarCheck :size="24" /></header>
          <div v-if="member.attendanceStatus === 'loading'" class="list-loading"><AppSkeleton v-for="item in 4" :key="item" height="4.5rem" /></div>
          <AppErrorState v-else-if="member.attendanceStatus === 'error'" :description="member.error" @retry="member.loadAttendance" />
          <AppEmptyState v-else-if="member.attendanceStatus === 'empty'" title="Todavía no hay asistencias" description="Cuando el gimnasio confirme una asistencia aparecerá en este historial." />
          <div v-else class="attendance-list"><article v-for="item in member.attendance" :key="item.id"><i :style="{ background: item.color || 'var(--accent)' }" /><div><strong>{{ item.clase_nombre }}</strong><span>{{ item.sede_nombre || 'Sede principal' }}<template v-if="item.instructor_nombre">, {{ item.instructor_nombre }}</template></span></div><time>{{ dateTime(item.inicio_en, item.zona_horaria) }}</time><AppBadge :tone="tone(item.estado)">{{ label(item.estado) }}</AppBadge></article></div>
          <div v-if="member.attendancePagination.total_pages > 1" class="pagination"><AppButton variant="secondary" size="sm" :disabled="member.attendancePagination.page <= 1" @click="member.loadAttendance(member.attendancePagination.page - 1)">Anterior</AppButton><span>Página {{ member.attendancePagination.page }} de {{ member.attendancePagination.total_pages }}</span><AppButton variant="secondary" size="sm" :disabled="member.attendancePagination.page >= member.attendancePagination.total_pages" @click="member.loadAttendance(member.attendancePagination.page + 1)">Siguiente</AppButton></div>
        </section>

        <section class="activity-section" aria-labelledby="memberships-title">
          <header><div><h2 id="memberships-title">Membresías</h2><p>Planes asociados a tu cuenta y su vigencia registrada.</p></div><IconIdBadge2 :size="24" /></header>
          <div v-if="member.membershipsStatus === 'loading'" class="list-loading"><AppSkeleton v-for="item in 2" :key="item" height="7rem" /></div>
          <AppErrorState v-else-if="member.membershipsStatus === 'error'" :description="member.error" @retry="member.loadMemberships" />
          <AppEmptyState v-else-if="member.membershipsStatus === 'empty'" title="No hay membresías registradas" description="Consultá los planes disponibles desde la sección de pagos." />
          <div v-else class="membership-list"><article v-for="item in member.memberships" :key="item.id"><div><AppBadge :tone="tone(item.estado)">{{ label(item.estado) }}</AppBadge><strong>{{ item.plan }}</strong><span>Número {{ item.numero_socio || 'pendiente' }}</span></div><div><strong>{{ money(item.precio_pagado, item.moneda) }}</strong><span>{{ date(item.fecha_inicio) }} a {{ date(item.fecha_vencimiento) }}</span></div></article></div>
        </section>

        <section class="activity-section" aria-labelledby="measurements-title">
          <header><div><h2 id="measurements-title">Mediciones opcionales</h2><p>Altura y peso quedan en tu contexto de gimnasio. El IMC nunca se presenta como diagnóstico.</p></div><IconRulerMeasure :size="24" /></header>
          <AppAlert tone="info" title="Referencia, no diagnóstico"><p>{{ member.medicalNotice || 'El IMC es orientativo y no constituye un diagnóstico médico.' }}</p></AppAlert>
          <div v-if="member.measurementsStatus === 'loading'" class="list-loading"><AppSkeleton v-for="item in 3" :key="item" height="6rem" /></div>
          <AppErrorState v-else-if="member.measurementsStatus === 'error'" :description="member.error" @retry="member.loadMeasurements" />
          <AppEmptyState v-else-if="member.measurementsStatus === 'empty'" title="No registraste mediciones" description="Podés guardar altura, peso o ambos. Es totalmente opcional."><template #action><AppButton variant="secondary" @click="openMeasurement">Registrar medición</AppButton></template></AppEmptyState>
          <template v-else>
            <SocioPesoSparkline v-if="weightTrend.filter((p) => p.weight_kg != null).length > 1" class="measurement-trend" :points="weightTrend" />
            <div class="measurement-list">
              <article v-for="(item, index) in member.measurements" :key="item.id">
                <time>{{ date(item.measured_at) }}</time>
                <div class="measurement-stat measurement-stat--primary">
                  <span>Peso</span>
                  <strong>{{ item.weight_kg ? `${item.weight_kg} kg` : 'Sin dato' }}</strong>
                  <small v-if="weightDelta(index) !== null" :class="weightDelta(index) < 0 ? 'is-down' : weightDelta(index) > 0 ? 'is-up' : ''">{{ weightDelta(index) > 0 ? '+' : '' }}{{ weightDelta(index) }} kg</small>
                </div>
                <div class="measurement-stat"><span>Altura</span><strong>{{ item.height_cm ? `${item.height_cm} cm` : 'Sin dato' }}</strong></div>
                <div v-if="member.preferences.show_orientation_bmi" class="measurement-stat"><span>IMC</span><strong>{{ item.bmi ?? 'Sin dato' }}</strong></div>
                <p v-if="item.notes">{{ item.notes }}</p>
              </article>
            </div>
          </template>
          <div v-if="member.measurementsPagination.total_pages > 1" class="pagination"><AppButton variant="secondary" size="sm" :disabled="member.measurementsPagination.page <= 1" @click="member.loadMeasurements(member.measurementsPagination.page - 1)">Anterior</AppButton><span>Página {{ member.measurementsPagination.page }} de {{ member.measurementsPagination.total_pages }}</span><AppButton variant="secondary" size="sm" :disabled="member.measurementsPagination.page >= member.measurementsPagination.total_pages" @click="member.loadMeasurements(member.measurementsPagination.page + 1)">Siguiente</AppButton></div>
        </section>
      </template>
    </main>
    <PublicFooter />
    <SocioBottomNav />

    <AppDrawer :open="measurementOpen" title="Registrar medición" @close="measurementOpen = false"><form id="measurement-form" class="measurement-form" @submit.prevent="saveMeasurement"><AppAlert v-if="formError" tone="danger" title="No pudimos guardar"><p>{{ formError }}</p></AppAlert><AppInput v-model="measurement.measured_at" label="Fecha" name="measurement-date" type="date" :max="localDate()" required :error="fields.measured_at" /><div class="measurement-pair"><AppInput v-model="measurement.height_cm" label="Altura en cm" name="measurement-height" type="number" min="80" max="250" step="0.01" :error="fields.height_cm" /><AppInput v-model="measurement.weight_kg" label="Peso en kg" name="measurement-weight" type="number" min="20" max="500" step="0.01" :error="fields.weight_kg" /></div><small v-if="fields.measurement" class="form-field-error">{{ fields.measurement }}</small><AppTextarea v-model="measurement.notes" label="Notas" name="measurement-notes" maxlength="500" :rows="4" hint="Opcional. Evitá guardar información médica sensible." :error="fields.notes" /><AppAlert tone="info" title="Uso orientativo"><p>Si completás altura y peso, GymTrack calcula un IMC orientativo. No clasifica ni diagnostica tu salud.</p></AppAlert></form><template #footer><div class="drawer-actions"><AppButton variant="secondary" @click="measurementOpen = false">Cancelar</AppButton><AppButton type="submit" form="measurement-form" :loading="member.working === 'measurement'">Guardar medición</AppButton></div></template></AppDrawer>
    <AppToast v-bind="toast" @close="toast.open = false" />
  </div>
</template>

<style scoped>
.member-page { min-height: 100vh; background: var(--bg-canvas); }.activity-main { min-height: 75vh; padding-bottom: var(--space-20); }.context-card { width: min(100%,30rem); margin-bottom: var(--space-8); padding: var(--space-4); }
.progress-loading,.progress-summary { display: grid; grid-template-columns: minmax(0,1.25fr) minmax(15rem,.75fr); gap: var(--space-4); }.progress-number,.next-session { display: grid; min-height: 15rem; align-content: center; gap: var(--space-3); border-block: 1px solid var(--border-subtle); padding: var(--space-5) 0; }.progress-number { grid-auto-flow: column; justify-content: start; align-items: center; gap: var(--space-5); }.progress-number__ring { position: relative; display: grid; place-items: center; }.progress-number__ring-label { position: absolute; display: grid; justify-items: center; line-height: 1; }.progress-number__ring-label strong { font-size: 1.6rem; font-variant-numeric: tabular-nums; letter-spacing: -.02em; }.progress-number__ring-label span { color: var(--text-tertiary); font-size: .68rem; }.progress-number__copy { display: grid; gap: var(--space-2); min-width: 0; }.progress-number__copy > span { display: inline-flex; align-items: center; gap: var(--space-2); color: var(--text-tertiary); font-size: .75rem; font-weight: 720; }.progress-number__copy svg { color: var(--info); }.next-session > span { color: var(--text-tertiary); font-size: .75rem; font-weight: 720; }.progress-number__copy p,.next-session p { max-width: 55ch; margin: 0; color: var(--text-secondary); }.next-session { padding-inline: var(--space-5); background: var(--surface-1); }.next-session > strong { margin-top: auto; font-size: 1.35rem; }.next-session :deep(.badge) { justify-self: start; }
.activity-section { margin-top: var(--space-16); border-top: 1px solid var(--border-subtle); padding-top: var(--space-8); }.activity-section > header { display: flex; align-items: flex-start; justify-content: space-between; gap: var(--space-6); margin-bottom: var(--space-6); }.activity-section h2 { margin: 0 0 var(--space-2); font-size: clamp(1.7rem,3vw,2.5rem); }.activity-section header p { margin: 0; color: var(--text-secondary); }.activity-section header svg { color: var(--info); }.activity-section > :deep(.alert) { margin-bottom: var(--space-5); }.activity-section :deep(.alert p),.measurement-form :deep(.alert p) { margin: 0; }.list-loading { display: grid; gap: var(--space-3); }
.attendance-list,.membership-list,.measurement-list { border-top: 1px solid var(--border-subtle); }.attendance-list article { display: grid; grid-template-columns: .35rem minmax(0,1fr) minmax(10rem,.55fr) auto; align-items: center; gap: var(--space-4); border-bottom: 1px solid var(--border-subtle); padding: var(--space-4) 0; }.attendance-list i { width: .3rem; height: 2.4rem; border-radius: var(--radius-pill); }.attendance-list article > div { display: grid; min-width: 0; gap: var(--space-1); }.attendance-list span,.attendance-list time { color: var(--text-tertiary); font-size: .75rem; }.attendance-list strong,.attendance-list span { overflow-wrap: anywhere; }.membership-list article { display: flex; align-items: center; justify-content: space-between; gap: var(--space-6); border-bottom: 1px solid var(--border-subtle); padding: var(--space-5) 0; }.membership-list article > div { display: grid; min-width: 0; gap: var(--space-2); }.membership-list article > div:last-child { justify-items: end; }.membership-list span { color: var(--text-tertiary); font-size: .75rem; }.membership-list article > div:last-child strong { font-size: 1.15rem; font-variant-numeric: tabular-nums; }.measurement-trend { margin-bottom: var(--space-5); }.measurement-list article { display: grid; grid-template-columns: minmax(8rem,.55fr) repeat(3,minmax(8rem,1fr)); gap: var(--space-4); border-bottom: 1px solid var(--border-subtle); padding: var(--space-5) 0; }.measurement-stat { display: grid; gap: var(--space-1); align-content: start; }.measurement-list span,.measurement-list time { color: var(--text-tertiary); font-size: .72rem; }.measurement-stat--primary strong { font-size: 1.2rem; }.measurement-stat--primary small { font-weight: 700; }.measurement-stat--primary small.is-down { color: var(--status-success-strong); }.measurement-stat--primary small.is-up { color: var(--status-warning-strong); }.measurement-list p { grid-column: 2/-1; margin: 0; color: var(--text-secondary); overflow-wrap: anywhere; }.pagination { display: flex; align-items: center; justify-content: flex-end; gap: var(--space-3); margin-top: var(--space-5); }.pagination span { color: var(--text-tertiary); font-size: .72rem; }.measurement-form { display: grid; gap: var(--space-5); }.measurement-pair { display: grid; grid-template-columns: 1fr 1fr; gap: var(--space-3); }.form-field-error { color: var(--status-danger-text); }.drawer-actions { display: flex; justify-content: flex-end; gap: var(--space-2); }
@media (max-width: 63.99rem) { .measurement-list article { grid-template-columns: minmax(8rem,.65fr) repeat(3,minmax(7rem,1fr)); } }
@media (max-width: 47.99rem) { .progress-loading,.progress-summary { grid-template-columns: 1fr; }.next-session { padding-inline: 0; background: transparent; }.attendance-list article { grid-template-columns: .3rem minmax(0,1fr) auto; }.attendance-list time { grid-column: 2; }.membership-list article { align-items: flex-start; flex-direction: column; }.membership-list article > div:last-child { justify-items: start; }.measurement-list article { grid-template-columns: 1fr 1fr; }.measurement-list article > time,.measurement-list article > p { grid-column: 1/-1; }.pagination { justify-content: space-between; }.measurement-pair { grid-template-columns: 1fr; }.drawer-actions { display: grid; grid-template-columns: 1fr; }.drawer-actions > * { width: 100%; } }
@media (max-width: 22.5rem) { .measurement-list article { grid-template-columns: 1fr; }.measurement-list article > time,.measurement-list article > p { grid-column: auto; }.pagination { display: grid; grid-template-columns: 1fr 1fr; }.pagination span { grid-column: 1/-1; grid-row: 1; text-align: center; } }
</style>
