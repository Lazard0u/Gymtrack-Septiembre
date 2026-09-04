<!--
  Vista de ruta ResetPasswordView. Coordina componentes, estado reactivo y llamadas a la API para completar este flujo de usuario.
  En <script> se declaran imports, estado y funciones; <template> describe la interfaz y <style> limita su presentación.
-->
<script setup>
import { reactive, ref } from 'vue'
import { useRoute } from 'vue-router'
import AuthShell from '../components/auth/AuthShell.vue';import AppAlert from '../components/ui/AppAlert.vue';import AppButton from '../components/ui/AppButton.vue';import AppInput from '../components/ui/AppInput.vue';import {api} from '../services/api'
const route=useRoute();const form=reactive({password:'',password_confirmation:''});const loading=ref(false);const message=ref('');const error=ref('')
async function submit(){if(loading.value)return;if(form.password!==form.password_confirmation){error.value='Las contraseñas no coinciden.';return}loading.value=true;error.value='';const response=await api.post('/auth/password/reset',{token:String(route.params.token||''),...form});loading.value=false;if(response.ok)message.value=response.data.mensaje;else error.value=response.data.mensaje}
</script>
<template><AuthShell><template #title>Elegí una contraseña nueva</template><template #description>El enlace funciona una sola vez y vence por seguridad.</template><AppAlert v-if="message" tone="success" title="Contraseña actualizada"><p>{{message}}</p><p><RouterLink :to="{name:'login'}">Iniciar sesión</RouterLink></p></AppAlert><AppAlert v-if="error" tone="danger" title="No pudimos restablecerla"><p>{{error}}</p></AppAlert><form v-if="!message" class="form" @submit.prevent="submit"><AppInput v-model="form.password" label="Nueva contraseña" type="password" autocomplete="new-password" minlength="12" maxlength="200" hint="12 caracteres, con mayúscula, minúscula y número." required :disabled="loading"/><AppInput v-model="form.password_confirmation" label="Confirmar contraseña" type="password" autocomplete="new-password" minlength="12" maxlength="200" required :disabled="loading"/><AppButton type="submit" block :loading="loading">Guardar contraseña</AppButton></form><template #footer><RouterLink :to="{name:'forgot-password'}">Solicitar otro enlace</RouterLink></template></AuthShell></template>
<style scoped>.form{display:grid;gap:var(--space-5)}.alert a{color:var(--status-info-strong)}</style>
