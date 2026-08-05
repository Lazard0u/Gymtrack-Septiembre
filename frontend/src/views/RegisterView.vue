<template>
  <div class="auth-page">
    <router-link to="/" class="brand">
      <img class="brand-mark" src="../assets/gymtrack-mark.svg" alt="GymTrack" />
      <span class="brand-word">Gym<span>Track</span></span>
    </router-link>

    <main class="auth-card">
      <h1>Unite a GymTrack</h1>
      <p class="muted">Registrate como socio y después buscá uno o varios gimnasios.</p>

      <div v-if="error" class="alert error" role="alert" aria-live="assertive">{{ error }}</div>
      <div v-if="exito" class="alert success" role="status" aria-live="polite">{{ exito }}</div>

      <form @submit.prevent="submitRegister">
        <label>
          Nombre completo
          <input v-model.trim="form.nombre" type="text" autocomplete="name" placeholder="Juan Pérez" required minlength="2" maxlength="100" :disabled="cargando" />
        </label>
        <label>
          Correo electrónico
          <input v-model.trim="form.email" type="email" autocomplete="email" placeholder="juan@correo.com" required maxlength="150" :disabled="cargando" />
        </label>
        <label>
          Contraseña
          <input v-model="form.password" type="password" autocomplete="new-password" placeholder="Mínimo 8 caracteres" required minlength="8" maxlength="200" :disabled="cargando" />
        </label>
        <label>
          Confirmar contraseña
          <input v-model="form.confirmPassword" type="password" autocomplete="new-password" placeholder="Repetí tu contraseña" required minlength="8" maxlength="200" :disabled="cargando" />
        </label>

        <div class="turnstile-box">
          <div ref="turnstileEl"></div>
          <p v-if="turnstileDemo" class="captcha-note">
            Turnstile en modo demo. Para produccion se configuran claves reales de Cloudflare.
          </p>
        </div>

        <button type="submit" class="primary-btn" :disabled="cargando">
          {{ cargando ? 'Creando cuenta...' : 'Crear cuenta' }}
        </button>
      </form>

      <p class="auth-footer">
        ¿Ya tenés cuenta?
        <router-link to="/login">Iniciá sesión</router-link>
      </p>
    </main>
  </div>
</template>

<script setup>
import { computed, nextTick, onBeforeUnmount, onMounted, reactive, ref } from 'vue'
import { useRouter } from 'vue-router'
import { useAuthStore } from '../stores/auth'

const router = useRouter()
const authStore = useAuthStore()
const form = reactive({ nombre: '', email: '', password: '', confirmPassword: '' })
const cargando = ref(false)
const error = ref(null)
const exito = ref(null)
const turnstileEl = ref(null)
const turnstileToken = ref('')
const turnstileWidgetId = ref(null)
let turnstilePollId = null
let redirectTimerId = null
const turnstileSiteKey = import.meta.env.VITE_TURNSTILE_SITE_KEY || '1x00000000000000000000AA'
const turnstileDemo = computed(() => turnstileSiteKey === '1x00000000000000000000AA')

function renderTurnstile() {
  if (!window.turnstile || !turnstileEl.value || turnstileWidgetId.value !== null) return

  turnstileWidgetId.value = window.turnstile.render(turnstileEl.value, {
    sitekey: turnstileSiteKey,
    theme: 'dark',
    callback: (token) => {
      turnstileToken.value = token
    },
    'expired-callback': () => {
      turnstileToken.value = ''
    },
    'error-callback': () => {
      turnstileToken.value = ''
      error.value = 'No se pudo verificar el captcha. Intentá de nuevo.'
    },
  })
}

function resetTurnstile() {
  turnstileToken.value = ''
  if (window.turnstile && turnstileWidgetId.value !== null) {
    window.turnstile.reset(turnstileWidgetId.value)
  }
}

async function submitRegister() {
  error.value = null
  exito.value = null
  if (form.password !== form.confirmPassword) {
    error.value = 'Las contraseñas no coinciden.'
    return
  }
  if (form.password.length < 8) {
    error.value = 'La contraseña debe tener al menos 8 caracteres.'
    return
  }
  if (!turnstileToken.value) {
    error.value = 'Completá la verificación anti robot para crear la cuenta.'
    return
  }
  cargando.value = true
  try {
    await authStore.registro(form.nombre, form.email, form.password, turnstileToken.value)
    exito.value = 'Cuenta creada. Redirigiendo al inicio de sesion...'
    redirectTimerId = window.setTimeout(() => router.push({ name: 'login' }), 1200)
  } catch (e) {
    error.value = e.message
    resetTurnstile()
  } finally {
    cargando.value = false
  }
}

onMounted(async () => {
  await nextTick()
  turnstilePollId = window.setInterval(() => {
    renderTurnstile()
    if (turnstileWidgetId.value !== null) {
      window.clearInterval(turnstilePollId)
      turnstilePollId = null
    }
  }, 250)
})

onBeforeUnmount(() => {
  if (turnstilePollId !== null) window.clearInterval(turnstilePollId)
  if (redirectTimerId !== null) window.clearTimeout(redirectTimerId)
  if (window.turnstile && turnstileWidgetId.value !== null) {
    window.turnstile.remove(turnstileWidgetId.value)
  }
})
</script>

<style scoped>
.auth-page {
  min-height: 100vh;
  display: grid;
  place-items: center;
  padding: 24px;
  background:
    linear-gradient(90deg, rgba(2, 6, 23, 0.96), rgba(2, 6, 23, 0.68)),
    url('https://images.unsplash.com/photo-1571902943202-507ec2618e8f?auto=format&fit=crop&w=1500&q=85') center/cover;
}

.brand {
  position: fixed;
  top: 24px;
  left: 24px;
  display: flex;
  align-items: center;
  gap: 12px;
  text-decoration: none;
}

.auth-card {
  width: min(460px, 100%);
  border: 1px solid rgba(56, 189, 248, 0.28);
  border-radius: 8px;
  padding: 34px;
  background: rgba(7, 17, 31, 0.9);
  box-shadow: 0 30px 90px rgba(0, 0, 0, 0.42);
  backdrop-filter: blur(18px);
}

h1 {
  margin: 8px 0 8px;
  font-size: 2rem;
}

form {
  display: grid;
  gap: 14px;
  margin-top: 22px;
}

label {
  display: grid;
  gap: 8px;
  color: var(--text);
  font-weight: 800;
}

input {
  min-height: 46px;
  border: 1px solid var(--line);
  border-radius: 8px;
  padding: 0 12px;
  background: rgba(2, 6, 23, 0.72);
  color: var(--text);
}

input:focus {
  outline: none;
  border-color: var(--blue-2);
  box-shadow: 0 0 0 4px rgba(0, 119, 255, 0.16);
}

.primary-btn {
  width: 100%;
}

.turnstile-box {
  display: grid;
  gap: 8px;
  min-height: 72px;
}

.captcha-note {
  color: var(--muted);
  font-size: 0.78rem;
  line-height: 1.4;
}

.alert {
  margin-top: 18px;
  border-radius: 8px;
  padding: 12px;
}

.alert.error {
  border: 1px solid rgba(239, 68, 68, 0.38);
  background: rgba(239, 68, 68, 0.12);
}

.alert.success {
  border: 1px solid rgba(34, 197, 94, 0.38);
  background: rgba(34, 197, 94, 0.12);
}

.auth-footer {
  margin-top: 18px;
  color: var(--muted);
}

.auth-footer a {
  color: var(--blue-2);
  font-weight: 900;
  text-decoration: none;
}
</style>
