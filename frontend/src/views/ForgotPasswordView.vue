<script setup>
import { reactive, ref } from 'vue'
import AuthShell from '../components/auth/AuthShell.vue'
import AppAlert from '../components/ui/AppAlert.vue'
import AppButton from '../components/ui/AppButton.vue'
import AppInput from '../components/ui/AppInput.vue'
import { api } from '../services/api'

const form=reactive({email:''});const loading=ref(false);const message=ref('');const error=ref('')
async function submit(){if(loading.value)return;loading.value=true;error.value='';message.value='';const response=await api.post('/auth/password/forgot',form);loading.value=false;if(response.ok)message.value=response.data.mensaje;else error.value=response.data.mensaje}
</script>
<template><AuthShell><template #title>Recuperá tu acceso</template><template #description>Te enviaremos un enlace de un solo uso si el correo corresponde a una cuenta.</template><AppAlert v-if="message" tone="success" title="Revisá tu correo"><p>{{message}}</p></AppAlert><AppAlert v-if="error" tone="danger" title="No pudimos procesarlo"><p>{{error}}</p></AppAlert><form v-if="!message" class="form" @submit.prevent="submit"><AppInput v-model.trim="form.email" label="Correo electrónico" type="email" name="email" autocomplete="email" maxlength="150" required :disabled="loading"/><AppButton type="submit" block :loading="loading">Enviar enlace</AppButton></form><template #footer><RouterLink :to="{name:'login'}">Volver a iniciar sesión</RouterLink></template></AuthShell></template>
<style scoped>.form{display:grid;gap:var(--space-5)}</style>
