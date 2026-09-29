<script setup>
import { computed, onMounted, reactive, ref, watch } from 'vue'
import { IconBell, IconClock, IconInfoCircle } from '@tabler/icons-vue'
import { useAuthStore } from '../stores/auth'
import { useSocioStore } from '../stores/socio'
import { useNotificacionesStore } from '../stores/notificaciones'
import SocioBottomNav from '../components/member/SocioBottomNav.vue'
import SocioPageHeader from '../components/member/SocioPageHeader.vue'
import SocioTopNav from '../components/member/SocioTopNav.vue'
import PublicFooter from '../components/public/PublicFooter.vue'
import AppAlert from '../components/ui/AppAlert.vue'
import AppButton from '../components/ui/AppButton.vue'
import AppCard from '../components/ui/AppCard.vue'
import AppCheckbox from '../components/ui/AppCheckbox.vue'
import AppEmptyState from '../components/ui/AppEmptyState.vue'
import AppErrorState from '../components/ui/AppErrorState.vue'
import AppInput from '../components/ui/AppInput.vue'
import AppSelect from '../components/ui/AppSelect.vue'
import AppSkeleton from '../components/ui/AppSkeleton.vue'
import AppToast from '../components/ui/AppToast.vue'

const auth = useAuthStore()
const member = useSocioStore()
const notifications = useNotificacionesStore()
const contexts = computed(() => (auth.user?.gimnasios || []).filter((gym) => gym.rol_nombre === 'socio'))
const contextOptions = computed(() => [{ value: '', label: 'Seleccioná un gimnasio' }, ...contexts.value.map((gym) => ({ value: String(gym.gimnasio_id), label: gym.nombre }))])
const hasContext = computed(() => Boolean(auth.user?.active_gym_id))
const training = reactive({ monthly_attendance_goal: 8, show_orientation_bmi: true, preferred_schedules: [] })
const messaging = reactive({ internal_transactional: true, email_transactional: true, internal_marketing: false, email_marketing: false, whatsapp_marketing: false, marketing_consent: false })
const trainingFields = ref({})
const trainingError = ref('')
const messagingError = ref('')
const toast = reactive({ open: false, title: '', message: '', tone: 'success' })
const timeRanges = [
  { value: 'morning', label: 'Mañana', description: 'Antes de las 12:00.' },
  { value: 'afternoon', label: 'Tarde', description: 'Entre las 12:00 y las 18:00.' },
  { value: 'evening', label: 'Noche', description: 'Después de las 18:00.' },
]

function notify(title, message = '', tone = 'success') { Object.assign(toast, { open: true, title, message, tone }) }
function apply() {
  Object.assign(training, {
    monthly_attendance_goal: member.preferences.monthly_attendance_goal ?? 8,
    show_orientation_bmi: member.preferences.show_orientation_bmi ?? true,
    preferred_schedules: Array.isArray(member.preferences.preferred_schedules) ? [...member.preferences.preferred_schedules] : [],
  })
  Object.assign(messaging, {
    internal_transactional: true,
    email_transactional: notifications.preferences.email_transactional ?? true,
    internal_marketing: notifications.preferences.internal_marketing ?? false,
    email_marketing: notifications.preferences.email_marketing ?? false,
    whatsapp_marketing: false,
    marketing_consent: notifications.preferences.marketing_consent ?? false,
  })
}
async function load() {
  if (!hasContext.value) return
  await Promise.allSettled([member.loadPreferences(), notifications.loadPreferences()])
  apply()
}
async function switchGym(value) {
  if (!value) return
  try { await auth.cambiarGimnasio(Number(value)); member.reset(); notifications.reset(); await load() }
  catch (error) { notify('No se pudo cambiar el gimnasio', error.message, 'danger') }
}
function toggleTime(value, checked) {
  training.preferred_schedules = checked
    ? [...new Set([...training.preferred_schedules, value])]
    : training.preferred_schedules.filter((item) => item !== value)
}
async function saveTraining() {
  trainingError.value = ''
  trainingFields.value = {}
  try {
    await member.savePreferences({
      monthly_attendance_goal: Number(training.monthly_attendance_goal),
      show_orientation_bmi: Boolean(training.show_orientation_bmi),
      preferred_schedules: training.preferred_schedules,
    })
    apply()
    notify('Preferencias de entrenamiento guardadas')
  } catch (error) { trainingError.value = error.message; trainingFields.value = error.fields || {} }
}
async function saveMessaging() {
  messagingError.value = ''
  try {
    await notifications.savePreferences({
      internal_transactional: true,
      email_transactional: Boolean(messaging.email_transactional),
      internal_marketing: Boolean(messaging.marketing_consent && messaging.internal_marketing),
      email_marketing: Boolean(messaging.marketing_consent && messaging.email_marketing),
      whatsapp_marketing: false,
      marketing_consent: Boolean(messaging.marketing_consent),
    })
    apply()
    notify('Preferencias de notificación guardadas', messaging.marketing_consent ? 'Los envíos respetarán los canales que elegiste.' : 'La publicidad quedó desactivada.')
  } catch (error) { messagingError.value = error.message }
}

watch(() => messaging.marketing_consent, (allowed) => { if (!allowed) { messaging.internal_marketing = false; messaging.email_marketing = false } })
onMounted(load)
</script>

<template>
  <div class="member-page">
    <SocioTopNav />
    <main class="container preferences-main">
      <SocioPageHeader title="Preferencias" description="Definí tu objetivo, tus horarios y los mensajes que querés recibir." />
      <AppCard v-if="contexts.length" class="context-card"><AppSelect :model-value="String(auth.user?.active_gym_id || '')" label="Gimnasio" :options="contextOptions" hint="El objetivo y los horarios se guardan por gimnasio." @update:model-value="switchGym" /></AppCard>
      <AppEmptyState v-if="!contexts.length" title="No tenés un gimnasio asociado" description="Necesitás una asociación activa como socio para configurar estas preferencias." />
      <AppEmptyState v-else-if="!hasContext" title="Elegí un gimnasio" description="Seleccioná el contexto para continuar." />
      <div v-else-if="member.preferencesStatus === 'loading' || notifications.preferencesStatus === 'loading'" class="preferences-loading"><AppSkeleton height="24rem" /><AppSkeleton height="28rem" /></div>
      <template v-else>
        <section class="preference-section" aria-labelledby="training-preferences-title">
          <header><IconClock :size="23" /><div><h2 id="training-preferences-title">Entrenamiento</h2><p>Estos datos organizan tu panel. No generan recomendaciones automáticas.</p></div></header>
          <AppErrorState v-if="member.preferencesStatus === 'error'" :description="member.error" @retry="member.loadPreferences" />
          <form v-else @submit.prevent="saveTraining">
            <AppAlert v-if="trainingError" tone="danger" title="No pudimos guardar"><p>{{ trainingError }}</p></AppAlert>
            <AppInput v-model="training.monthly_attendance_goal" label="Objetivo mensual de asistencias" name="monthly-attendance-goal" type="number" min="1" max="31" step="1" required hint="Entre 1 y 31 asistencias." :error="trainingFields.monthly_attendance_goal" />
            <fieldset><legend>Horarios preferidos</legend><AppCheckbox v-for="range in timeRanges" :key="range.value" :model-value="training.preferred_schedules.includes(range.value)" :label="range.label" :description="range.description" @update:model-value="toggleTime(range.value, $event)" /></fieldset>
            <AppCheckbox v-model="training.show_orientation_bmi" label="Mostrar IMC orientativo" description="Se calcula sólo cuando registrás altura y peso. No es un diagnóstico médico." />
            <AppButton type="submit" :loading="member.working === 'preferences'">Guardar entrenamiento</AppButton>
          </form>
        </section>

        <section class="preference-section" aria-labelledby="notification-preferences-title">
          <header><IconBell :size="23" /><div><h2 id="notification-preferences-title">Notificaciones</h2><p>Los avisos operativos y la publicidad se gestionan por separado.</p></div></header>
          <AppErrorState v-if="notifications.preferencesStatus === 'error'" description="No se pudieron cargar tus preferencias de notificación." @retry="notifications.loadPreferences" />
          <form v-else @submit.prevent="saveMessaging">
            <AppAlert v-if="messagingError" tone="danger" title="No pudimos guardar"><p>{{ messagingError }}</p></AppAlert>
            <fieldset><legend>Avisos necesarios</legend><AppCheckbox :model-value="true" label="Notificaciones internas" description="Confirmaciones de reservas, pagos y cambios importantes. Siempre activas." disabled /><AppCheckbox v-model="messaging.email_transactional" label="Correo transaccional" description="Recibí por correo las confirmaciones necesarias de tu cuenta." /></fieldset>
            <fieldset><legend>Promociones</legend><AppCheckbox v-model="messaging.marketing_consent" label="Acepto recibir publicidad" description="Podés retirar este consentimiento en cualquier momento." /><AppCheckbox v-model="messaging.internal_marketing" label="Promociones internas" description="Muestra promociones relevantes dentro de GymTrack." :disabled="!messaging.marketing_consent" /><AppCheckbox v-model="messaging.email_marketing" label="Promociones por correo" description="Envía campañas al correo verificado de tu cuenta." :disabled="!messaging.marketing_consent" /><AppCheckbox :model-value="false" label="WhatsApp, Beta" description="Todavía no hay un proveedor conectado. Esta opción no realiza envíos." disabled /></fieldset>
            <AppAlert tone="info" title="Control de consentimiento"><p>Al desactivar la publicidad se omiten también los envíos pendientes de marketing. Los avisos operativos continúan disponibles.</p></AppAlert>
            <AppButton type="submit" :loading="notifications.preferencesStatus === 'saving'">Guardar notificaciones</AppButton>
          </form>
        </section>

        <section class="beta-note"><IconInfoCircle :size="22" /><div><h2>Funciones que continúan en beta</h2><p>WhatsApp y la sincronización automática con Google Calendar siguen desactivados. El calendario manual y la descarga ICS sí funcionan.</p></div></section>
      </template>
    </main>
    <PublicFooter />
    <SocioBottomNav />
    <AppToast v-bind="toast" @close="toast.open = false" />
  </div>
</template>

<style scoped>
.member-page { min-height: 100vh; background: var(--bg-canvas); }.preferences-main { min-height: 75vh; padding-bottom: var(--space-20); }.context-card { width: min(100%,30rem); margin-bottom: var(--space-8); padding: var(--space-4); }.preferences-loading { display: grid; grid-template-columns: 1fr 1fr; gap: var(--space-4); }
.preference-section { display: grid; grid-template-columns: minmax(14rem,.55fr) minmax(0,1.45fr); gap: var(--space-12); border-top: 1px solid var(--border-subtle); padding-block: var(--space-10); }.preference-section > header { display: grid; grid-template-columns: 1.75rem minmax(0,1fr); align-content: start; gap: var(--space-3); }.preference-section > header svg { color: var(--info); }.preference-section h2 { margin: 0 0 var(--space-3); font-size: clamp(1.7rem,3vw,2.5rem); }.preference-section > header p { margin: 0; color: var(--text-secondary); }.preference-section form { display: grid; gap: var(--space-5); }.preference-section form > :deep(.app-button) { justify-self: start; }.preference-section fieldset { display: grid; gap: var(--space-2); min-width: 0; margin: 0; border: 1px solid var(--border-subtle); border-radius: var(--radius-card); padding: var(--space-4); }.preference-section legend { padding-inline: var(--space-2); font-size: .82rem; font-weight: 740; }.preference-section :deep(.alert p) { margin: 0; }
.beta-note { display: grid; grid-template-columns: 1.75rem minmax(0,1fr); gap: var(--space-3); border-top: 1px solid var(--border-subtle); padding-block: var(--space-8); }.beta-note svg { color: var(--warning); }.beta-note h2 { margin: 0 0 var(--space-2); font-size: 1.15rem; }.beta-note p { max-width: 70ch; margin: 0; color: var(--text-secondary); }
@media (max-width: 47.99rem) { .preferences-loading,.preference-section { grid-template-columns: 1fr; }.preference-section { gap: var(--space-6); }.preference-section form > :deep(.app-button) { width: 100%; } }
</style>
