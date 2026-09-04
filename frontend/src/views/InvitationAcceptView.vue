<!--
  Vista de ruta InvitationAcceptView. Coordina componentes, estado reactivo y llamadas a la API para completar este flujo de usuario.
  En <script> se declaran imports, estado y funciones; <template> describe la interfaz y <style> limita su presentación.
-->
<script setup>
import { reactive, ref } from 'vue'
import { useRoute } from 'vue-router'
import AuthShell from '../components/auth/AuthShell.vue'
import AppAlert from '../components/ui/AppAlert.vue'
import AppButton from '../components/ui/AppButton.vue'
import AppCheckbox from '../components/ui/AppCheckbox.vue'
import AppInput from '../components/ui/AppInput.vue'
import { api } from '../services/api'

const route = useRoute()
const loading = ref(false)
const success = ref(false)
const error = ref('')
const fields = ref({})
const form = reactive({ nombre: '', apellido: '', telefono: '', password: '', password_confirmation: '', terminos: false, privacidad: false })

async function submit() {
  error.value = ''; fields.value = {}
  if (form.password !== form.password_confirmation) {
    fields.value = { password_confirmation: 'Las contraseñas no coinciden.' }
    return
  }
  loading.value = true
  const response = await api.post('/invitations/accept', { token: route.params.token, ...form })
  loading.value = false
  if (!response.ok || response.data?.error) {
    error.value = response.data?.mensaje || 'No se pudo aceptar la invitación.'
    fields.value = response.data?.fields || {}
    return
  }
  success.value = true
}
</script>

<template>
  <AuthShell>
    <template #title>Unite al gimnasio</template>
    <template #description>Completá tus datos para aceptar la invitación. El enlace sólo puede utilizarse una vez.</template>
    <AppAlert v-if="error" tone="danger" title="No pudimos aceptar la invitación"><p>{{ error }}</p></AppAlert>
    <AppAlert v-if="success" tone="success" title="Invitación aceptada"><p>Tu cuenta quedó asociada al gimnasio. Ya podés ingresar.</p><p><RouterLink :to="{ name: 'login' }">Ir al inicio de sesión</RouterLink></p></AppAlert>
    <form v-if="!success" class="invitation-form" novalidate @submit.prevent="submit">
      <div class="name-grid"><AppInput v-model.trim="form.nombre" label="Nombre" name="given-name" autocomplete="given-name" required :error="fields.nombre" :disabled="loading" /><AppInput v-model.trim="form.apellido" label="Apellido" name="family-name" autocomplete="family-name" required :error="fields.apellido" :disabled="loading" /></div>
      <AppInput v-model.trim="form.telefono" label="Teléfono" name="phone" type="tel" autocomplete="tel" :error="fields.telefono" :disabled="loading" />
      <AppInput v-model="form.password" label="Contraseña" name="password" type="password" autocomplete="new-password" minlength="12" hint="12 caracteres, con mayúscula, minúscula y número. Si ya tenés cuenta, tu contraseña actual no se modifica." required :error="fields.password" :disabled="loading" />
      <AppInput v-model="form.password_confirmation" label="Confirmar contraseña" name="password-confirmation" type="password" autocomplete="new-password" minlength="12" required :error="fields.password_confirmation" :disabled="loading" />
      <AppCheckbox v-model="form.terminos" label="Acepto los términos de uso." name="terms" required :error="fields.terminos" />
      <AppCheckbox v-model="form.privacidad" label="Acepto la política de privacidad." name="privacy" required :error="fields.privacidad" />
      <AppButton type="submit" block :loading="loading">Aceptar invitación</AppButton>
    </form>
    <template #footer>¿Ya completaste la invitación? <RouterLink :to="{ name: 'login' }">Iniciá sesión</RouterLink></template>
  </AuthShell>
</template>

<style scoped>
.invitation-form { display: grid; gap: var(--space-4); }.name-grid { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: var(--space-4); }.alert p { margin: var(--space-2) 0 0; }.alert a { color: var(--status-info-strong); }@media (max-width: 30rem) { .name-grid { grid-template-columns: 1fr; } }
</style>
