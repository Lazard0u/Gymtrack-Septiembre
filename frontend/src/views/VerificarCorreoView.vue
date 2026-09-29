<!--
  Pantalla pública que recibe el token del correo y lo entrega una sola vez a PHP.
  El backend decide si el enlace es válido, vencido, usado o si la cuenta ya estaba
  verificada; Vue únicamente traduce ese resultado a un estado comprensible.
-->
<script setup>
// Vue administra el estado local; Vue Router lee el token y define la salida.
import { computed, onMounted, reactive, ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import {
  IconCircleCheck,
  IconClockExclamation,
  IconLinkOff,
  IconMailForward,
  IconShieldCheck,
} from '@tabler/icons-vue'
import AuthShell from '../components/auth/AuthShell.vue'
import AppAlert from '../components/ui/AppAlert.vue'
import AppButton from '../components/ui/AppButton.vue'
import AppInput from '../components/ui/AppInput.vue'
import AppSpinner from '../components/ui/AppSpinner.vue'
import { api } from '../services/api'
import { useAuthStore } from '../stores/auth'

const route = useRoute()
const router = useRouter()
const auth = useAuthStore()

// El correo sólo ayuda al reenvío: nunca se usa para validar el token en cliente.
const form = reactive({ email: String(route.query.email || auth.user?.email || '') })
function tokenFromRoute() {
  // Se prefiere el fragmento porque el navegador no lo envía al servidor web.
  const fragmentToken = new URLSearchParams(String(route.hash || '').replace(/^#/, '')).get('token')
  return String(fragmentToken || route.query.token || '')
}

const state = ref(tokenFromRoute() ? 'verifying' : 'idle')
const message = ref('')
const error = ref('')
const loading = ref(false)

// Cada estado recibe iconografía y texto, sin depender únicamente del color.
const presentation = computed(() => {
  const states = {
    success: { icon: IconCircleCheck, tone: 'success', title: 'Correo verificado' },
    already: { icon: IconShieldCheck, tone: 'success', title: 'Tu correo ya estaba verificado' },
    expired: { icon: IconClockExclamation, tone: 'warning', title: 'El enlace venció' },
    used: { icon: IconShieldCheck, tone: 'info', title: 'El enlace ya fue utilizado' },
    invalid: { icon: IconLinkOff, tone: 'danger', title: 'El enlace no es válido' },
    resent: { icon: IconMailForward, tone: 'success', title: 'Solicitud recibida' },
  }
  return states[state.value] || null
})

const canContinue = computed(() => ['success', 'already', 'used'].includes(state.value))
const canResend = computed(() => ['idle', 'expired', 'invalid', 'resent'].includes(state.value))

/** Envía el token a PHP; no intenta interpretarlo ni guardarlo en el navegador. */
async function verify() {
  const token = tokenFromRoute()
  if (!token) {
    state.value = auth.correoVerificado ? 'already' : 'idle'
    return
  }

  state.value = 'verifying'
  const response = await api.post('/auth/email/verify', { token })
  // Borra el secreto de la barra e historial apenas PHP termina de procesarlo.
  await router.replace({ name: 'verify-email', query: form.email ? { email: form.email } : {} })
  if (response.ok) {
    state.value = response.data.codigo === 'email_already_verified' ? 'already' : 'success'
    message.value = response.data.mensaje
    // Una sesión abierta conserva el estado anterior hasta refrescar /api/me.
    if (auth.estaAutenticado) await auth.bootstrap(true)
    return
  }

  const statesByCode = {
    email_verification_expired: 'expired',
    email_verification_used: 'used',
    email_verification_invalid: 'invalid',
  }
  state.value = statesByCode[response.data?.codigo] || 'invalid'
  error.value = response.data?.mensaje || 'No pudimos comprobar el enlace.'
}

/** Solicita un correo nuevo con respuesta neutral para no revelar cuentas. */
async function resend() {
  if (loading.value) return
  loading.value = true
  error.value = ''
  message.value = ''
  const response = await api.post('/auth/email/resend', { email: form.email })
  loading.value = false
  if (response.ok) {
    state.value = 'resent'
    message.value = response.data.mensaje
  } else {
    error.value = response.data?.mensaje || 'No pudimos procesar la solicitud. Intentá nuevamente.'
  }
}

/** Después de verificar, una sesión existente vuelve al panel; las demás al login. */
async function continueToApp() {
  await router.push(auth.estaAutenticado ? { name: 'dashboard' } : { name: 'login' })
}

// La validación comienza al abrir el enlace; el botón no necesita otro clic.
onMounted(verify)
</script>

<template>
  <AuthShell>
    <template #title>Verificá tu correo</template>
    <template #description>Confirmamos la dirección antes de habilitar las áreas protegidas de GymTrack.</template>

    <section v-if="state === 'verifying'" class="verification-state" aria-live="polite">
      <AppSpinner />
      <div><strong>Validando el enlace</strong><span>Esto demora sólo un momento.</span></div>
    </section>

    <section
      v-else-if="presentation"
      :class="['verification-result', `verification-result--${presentation.tone}`]"
      aria-live="polite"
    >
      <component :is="presentation.icon" :size="25" aria-hidden="true" />
      <div><h2>{{ presentation.title }}</h2><p>{{ message || error }}</p></div>
    </section>

    <AppAlert v-if="error && !presentation" tone="danger" title="No pudimos verificar">
      <p>{{ error }}</p>
    </AppAlert>

    <div v-if="canContinue" class="actions">
      <AppButton block @click="continueToApp">{{ auth.estaAutenticado ? 'Continuar a mi panel' : 'Ir a iniciar sesión' }}</AppButton>
    </div>

    <form v-if="canResend" class="resend-form" @submit.prevent="resend">
      <div class="resend-form__intro">
        <h2>{{ state === 'resent' ? '¿No llegó?' : 'Solicitar otro enlace' }}</h2>
        <p>Ingresá tu correo. Por seguridad, la respuesta será la misma exista o no una cuenta.</p>
      </div>
      <AppInput
        v-model.trim="form.email"
        label="Correo electrónico"
        name="verification-email"
        type="email"
        autocomplete="email"
        inputmode="email"
        maxlength="150"
        required
        :disabled="loading"
      />
      <AppButton type="submit" block :loading="loading">Reenviar verificación</AppButton>
    </form>

    <template #footer><RouterLink :to="{ name: 'login' }">Volver a iniciar sesión</RouterLink></template>
  </AuthShell>
</template>

<style scoped>
/* El resultado se integra al sistema oscuro actual y conserva contraste AA. */
.verification-state,.verification-result{display:grid;grid-template-columns:2rem minmax(0,1fr);align-items:start;gap:var(--space-4);border:1px solid var(--border-subtle);border-radius:var(--radius-card);padding:var(--space-5);background:var(--surface-1)}
.verification-state div,.verification-result div{display:grid;gap:var(--space-1);min-width:0}.verification-state span,.verification-result p{margin:0;color:var(--text-secondary);font-size:.875rem;line-height:1.55;overflow-wrap:anywhere}.verification-result h2{margin:0;font-size:1.05rem}.verification-result--success svg{color:var(--status-success-strong)}.verification-result--warning svg{color:var(--status-warning-strong)}.verification-result--danger svg{color:var(--status-danger-strong)}.verification-result--info svg{color:var(--status-info-strong)}
.actions{display:grid;margin-top:var(--space-5)}.resend-form{display:grid;gap:var(--space-4);margin-top:var(--space-6);padding-top:var(--space-6);border-top:1px solid var(--border-subtle)}.resend-form__intro{display:grid;gap:var(--space-2)}.resend-form__intro h2{margin:0;font-size:1.05rem}.resend-form__intro p{margin:0;color:var(--text-secondary);font-size:.82rem;line-height:1.55}
@media(max-width:29.99rem){.verification-state,.verification-result{padding:var(--space-4)}}
</style>
