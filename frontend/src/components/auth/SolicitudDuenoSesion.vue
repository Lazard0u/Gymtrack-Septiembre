<script setup>
import { reactive, ref } from 'vue'
import { RouterLink } from 'vue-router'
import AuthShell from './AuthShell.vue'
import AppAlert from '../ui/AppAlert.vue'
import AppButton from '../ui/AppButton.vue'
import AppInput from '../ui/AppInput.vue'
import AppTextarea from '../ui/AppTextarea.vue'
import { api } from '../../services/api'
import { useAuthStore } from '../../stores/auth'

// Formulario para quien ya tiene cuenta (por ejemplo, un socio): no crea otro usuario, deja una solicitud pendiente.
const auth = useAuthStore()
const form = reactive({ gym_name: '', message: '' })
const loading = ref(false); const error = ref(''); const success = ref(''); const fields = ref({})

async function submit() {
  if (loading.value) return
  loading.value = true; error.value = ''; fields.value = {}
  const response = await api.post('/me/owner-request', { gym_name: form.gym_name, message: form.message })
  loading.value = false
  if (!response.ok || response.data?.error) { error.value = response.data?.mensaje || 'No pudimos enviar la solicitud. Probá de nuevo.'; fields.value = response.data?.fields || {}; return }
  success.value = response.data.mensaje
}
</script>

<template>
  <AuthShell>
    <template #title>Pedí tu cuenta de dueño</template>
    <template #description>La solicitud se envía con tu cuenta actual ({{ auth.user?.email }}). Un administrador la revisa y vincula el gimnasio. Mientras tanto seguís siendo socio.</template>
    <AppAlert v-if="error" tone="danger" title="Revisá la solicitud"><p>{{ error }}</p></AppAlert>
    <AppAlert v-if="success" tone="success" title="Solicitud enviada"><p>{{ success }}</p><p><RouterLink :to="{ name: 'dashboard' }">Volver a mi panel</RouterLink></p></AppAlert>
    <form v-else class="form" @submit.prevent="submit">
      <AppInput v-model.trim="form.gym_name" label="Nombre del gimnasio" name="owner-gym-name" autocomplete="organization" maxlength="120" required :error="fields.gym_name" />
      <AppTextarea v-model="form.message" label="Contanos sobre tu gimnasio" name="owner-message" :rows="4" hint="Opcional: ciudad, cuántos socios tiene, qué necesitás." />
      <AppButton type="submit" block :loading="loading">Enviar solicitud</AppButton>
    </form>
    <template #footer>¿Preferís volver? <RouterLink :to="{ name: 'dashboard' }">Ir a mi panel</RouterLink></template>
  </AuthShell>
</template>

<style scoped>
.form { display: grid; gap: var(--space-4); }
</style>
