<!--
  Vista de ruta MemberCardView. Coordina componentes, estado reactivo y llamadas a la API para completar este flujo de usuario.
  En <script> se declaran imports, estado y funciones; <template> describe la interfaz y <style> limita su presentación.
-->
<script setup>
import { computed, onBeforeUnmount, onMounted, ref } from 'vue'
import QRCode from 'qrcode'
import { IconCopy, IconIdBadge2, IconRefresh, IconShieldCheck } from '@tabler/icons-vue'
import { useAuthStore } from '../stores/auth'
import { useMemberStore } from '../stores/member'
import MemberBottomNav from '../components/member/MemberBottomNav.vue'
import MemberTopNav from '../components/member/MemberTopNav.vue'
import PublicFooter from '../components/public/PublicFooter.vue'
import AppAlert from '../components/ui/AppAlert.vue'
import AppBadge from '../components/ui/AppBadge.vue'
import AppButton from '../components/ui/AppButton.vue'
import AppCard from '../components/ui/AppCard.vue'
import AppEmptyState from '../components/ui/AppEmptyState.vue'
import AppErrorState from '../components/ui/AppErrorState.vue'
import AppSelect from '../components/ui/AppSelect.vue'
import AppSkeleton from '../components/ui/AppSkeleton.vue'
import AppToast from '../components/ui/AppToast.vue'
import brandMark from '../assets/gymtrack-mark.svg'

const auth = useAuthStore()
const member = useMemberStore()
const contexts = computed(() => (auth.user?.gimnasios || []).filter((gym) => gym.rol_nombre === 'socio'))
const contextOptions = computed(() => [{ value: '', label: 'Seleccioná un gimnasio' }, ...contexts.value.map((gym) => ({ value: String(gym.gimnasio_id), label: gym.nombre }))])
const hasContext = computed(() => Boolean(auth.user?.active_gym_id))
const qrDataUrl = ref('')
const qrError = ref('')
const now = ref(Date.now())
const toast = ref({ open: false, title: '', message: '', tone: 'success' })
let clock = null
let renewal = null

const secondsRemaining = computed(() => Math.max(0, Math.ceil((new Date(member.card?.expires_at || 0).getTime() - now.value) / 1000)))
const countdown = computed(() => `${Math.floor(secondsRemaining.value / 60)}:${String(secondsRemaining.value % 60).padStart(2,'0')}`)
const membershipTone = computed(() => ({ activa: 'success', pendiente_pago: 'warning', vencida: 'danger', suspendida: 'neutral' })[member.card?.membership?.estado] || 'neutral')
function label(value) { return String(value || 'sin membresía').replaceAll('_',' ').replace(/^./,(letter) => letter.toUpperCase()) }
function date(value) { if (!value) return 'Sin fecha'; return new Intl.DateTimeFormat('es-UY',{ dateStyle: 'medium' }).format(new Date(`${String(value).slice(0,10)}T12:00:00`)) }
function notify(title, message = '', tone = 'success') { toast.value = { open: true, title, message, tone } }

async function renderQr() {
  qrError.value = ''
  qrDataUrl.value = ''
  if (!member.card?.token) return
  try {
    qrDataUrl.value = await QRCode.toDataURL(member.card.token, {
      errorCorrectionLevel: 'M', margin: 2, width: 560,
      color: { dark: '#0b0e12', light: '#f4f7f9' },
    })
  } catch { qrError.value = 'No se pudo dibujar el código QR en este navegador.' }
}
function scheduleRenewal() {
  window.clearTimeout(renewal)
  const expiresAt = new Date(member.card?.expires_at || 0).getTime()
  const delay = Math.max(1000, expiresAt - Date.now() - 10_000)
  renewal = window.setTimeout(refresh, delay)
}
async function refresh() {
  const ok = await member.loadCard()
  if (!ok) return
  await renderQr()
  scheduleRenewal()
}
async function switchGym(value) {
  if (!value) return
  try { await auth.cambiarGimnasio(Number(value)); member.reset(); await refresh() }
  catch (error) { notify('No se pudo cambiar el gimnasio', error.message, 'danger') }
}
async function copyToken() {
  try { await navigator.clipboard.writeText(member.card.token); notify('Token copiado', 'Caduca pronto y sólo sirve para verificar este carné.') }
  catch { notify('No se pudo copiar', 'Tu navegador bloqueó el portapapeles. Usá el código QR.', 'danger') }
}

onMounted(async () => { clock = window.setInterval(() => { now.value = Date.now() }, 1000); if (hasContext.value) await refresh() })
onBeforeUnmount(() => { window.clearInterval(clock); window.clearTimeout(renewal) })
</script>

<template>
  <div class="member-page">
    <MemberTopNav />
    <main class="container card-main">
      <header class="page-heading"><div><h1>Tu carné digital, siempre verificable.</h1><p>Mostrá este código al personal. Cambia cada pocos minutos y no contiene tus datos personales.</p></div><AppButton variant="secondary" :loading="member.cardStatus === 'loading'" @click="refresh"><template #icon><IconRefresh :size="18" /></template>Renovar</AppButton></header>
      <AppCard v-if="contexts.length" class="context-card"><AppSelect :model-value="String(auth.user?.active_gym_id || '')" label="Gimnasio" :options="contextOptions" hint="El carné es válido únicamente para el gimnasio activo." @update:model-value="switchGym" /></AppCard>
      <AppEmptyState v-if="!contexts.length" title="No tenés un gimnasio asociado" description="Necesitás una asociación activa como socio para generar el carné." />
      <AppEmptyState v-else-if="!hasContext" title="Elegí un gimnasio" description="Seleccioná el contexto para generar tu carné." />
      <div v-else-if="member.cardStatus === 'loading'" class="card-loading"><AppSkeleton height="34rem" /><AppSkeleton height="20rem" /></div>
      <AppErrorState v-else-if="member.cardStatus === 'error'" title="No pudimos generar tu carné" :description="member.error" @retry="refresh" />
      <div v-else class="card-layout">
        <section class="digital-card" aria-labelledby="digital-card-title">
          <header><div><img :src="brandMark" alt="" width="42" height="42" /><div><strong id="digital-card-title">GymTrack</strong><span>Carné de socio</span></div></div><AppBadge :tone="membershipTone">{{ label(member.card?.membership?.estado) }}</AppBadge></header>
          <div class="qr-frame"><img v-if="qrDataUrl" :src="qrDataUrl" alt="Código QR temporal de tu carné GymTrack" width="560" height="560" /><AppErrorState v-else-if="qrError" title="QR no disponible" :description="qrError" @retry="renderQr" /><AppSkeleton v-else height="20rem" /></div>
          <div class="card-identity"><span>{{ member.card?.member?.gym?.name }}</span><strong>{{ member.card?.member?.member_number || 'Número pendiente' }}</strong></div>
          <footer><span>Renovación en {{ countdown }}</span><IconShieldCheck :size="20" aria-label="Token firmado" /></footer>
        </section>

        <aside class="card-details">
          <div><IconIdBadge2 :size="24" /><h2>Datos verificados en el servidor</h2><p>El QR lleva un token firmado y temporal. El nombre, la membresía y el gimnasio se consultan en MySQL al escanearlo.</p></div>
          <dl><div><dt>Gimnasio</dt><dd>{{ member.card?.member?.gym?.name }}</dd></div><div><dt>Número de socio</dt><dd>{{ member.card?.member?.member_number || 'Pendiente' }}</dd></div><div><dt>Plan</dt><dd>{{ member.card?.membership?.plan || 'Sin membresía' }}</dd></div><div><dt>Vigencia</dt><dd>{{ member.card?.membership ? `${date(member.card.membership.fecha_inicio)} a ${date(member.card.membership.fecha_vencimiento)}` : 'Sin vigencia' }}</dd></div></dl>
          <AppAlert tone="info" title="Privacidad"><p>El código no incluye nombre, correo, teléfono ni número de socio. Un gimnasio distinto no puede validarlo.</p></AppAlert>
          <AppButton variant="ghost" block @click="copyToken"><template #icon><IconCopy :size="17" /></template>Copiar token temporal</AppButton>
        </aside>
      </div>
    </main>
    <PublicFooter />
    <MemberBottomNav />
    <AppToast v-bind="toast" @close="toast.open = false" />
  </div>
</template>

<style scoped>
.member-page { min-height: 100vh; background: var(--bg-canvas); }.card-main { min-height: 75vh; padding-bottom: var(--space-20); }.page-heading { display: flex; align-items: flex-end; justify-content: space-between; gap: var(--space-8); padding-block: clamp(var(--space-10),7vw,var(--space-16)); }.page-heading h1 { max-width: 54rem; margin: 0 0 var(--space-4); font-size: clamp(2.2rem,5vw,4rem); }.page-heading p { max-width: 48rem; margin: 0; color: var(--text-secondary); }.context-card { width: min(100%,30rem); margin-bottom: var(--space-8); padding: var(--space-4); }.card-loading,.card-layout { display: grid; grid-template-columns: minmax(20rem,.9fr) minmax(17rem,1.1fr); gap: clamp(var(--space-6),5vw,var(--space-16)); align-items: center; }.digital-card { position: relative; display: grid; min-width: 0; gap: var(--space-5); overflow: hidden; border-radius: var(--radius-dialog); padding: clamp(var(--space-5),4vw,var(--space-8)); background: var(--surface-inverse); color: var(--text-inverse); box-shadow: 0 24px 60px rgba(0,0,0,.34); }.digital-card::after { position: absolute; inset: auto -8rem -10rem auto; width: 18rem; height: 18rem; border-radius: 50%; background: rgba(9,105,218,.12); content: ''; pointer-events: none; }.digital-card > header,.digital-card > header > div,.digital-card > footer { display: flex; align-items: center; justify-content: space-between; gap: var(--space-3); }.digital-card > header > div > div { display: grid; }.digital-card > header strong { color: var(--text-inverse); font-size: 1rem; }.digital-card > header span { color: #56616d; font-size: .7rem; }.qr-frame { display: grid; width: min(100%,22rem); aspect-ratio: 1; place-items: center; justify-self: center; overflow: hidden; border-radius: var(--radius-card); background: #f4f7f9; }.qr-frame > img { width: 100%; height: 100%; object-fit: contain; }.card-identity { display: grid; gap: var(--space-1); }.card-identity span { color: #56616d; font-size: .75rem; }.card-identity strong { color: var(--text-inverse); font-size: 1.35rem; overflow-wrap: anywhere; }.digital-card > footer { position: relative; z-index: 1; border-top: 1px solid #ccd3da; padding-top: var(--space-4); color: #4d5864; font-size: .72rem; }.digital-card > footer svg { color: #0969da; }
.card-details { display: grid; gap: var(--space-6); }.card-details > div:first-child > svg { margin-bottom: var(--space-6); color: var(--info); }.card-details h2 { margin: 0 0 var(--space-3); font-size: clamp(1.7rem,3vw,2.5rem); }.card-details p { max-width: 62ch; margin: 0; color: var(--text-secondary); }.card-details dl { display: grid; grid-template-columns: 1fr 1fr; gap: var(--space-4); margin: 0; }.card-details dl div { display: grid; gap: var(--space-1); border-bottom: 1px solid var(--border-subtle); padding-bottom: var(--space-3); }.card-details dt { color: var(--text-tertiary); font-size: .72rem; }.card-details dd { margin: 0; overflow-wrap: anywhere; }.card-details :deep(.alert p) { margin: 0; }
@media (max-width: 63.99rem) { .card-loading,.card-layout { grid-template-columns: minmax(18rem,.9fr) minmax(16rem,1.1fr); gap: var(--space-8); } }
@media (max-width: 47.99rem) { .page-heading { align-items: stretch; flex-direction: column; }.page-heading > :last-child { width: 100%; }.card-loading,.card-layout { grid-template-columns: 1fr; }.digital-card { width: min(100%,28rem); justify-self: center; }.card-details dl { grid-template-columns: 1fr; } }
@media (max-width: 22.5rem) { .digital-card { padding: var(--space-4); }.qr-frame { border-radius: var(--radius-control); } }
</style>
