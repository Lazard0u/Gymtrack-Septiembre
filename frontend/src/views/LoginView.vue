<!--
  Vista de ruta LoginView. Coordina componentes, estado reactivo y llamadas a la API para completar este flujo de usuario.
  En <script> se declaran imports, estado y funciones; <template> describe la interfaz y <style> limita su presentación.
-->
<script setup>
import { reactive, ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import AuthShell from '../components/auth/AuthShell.vue'
import AppAlert from '../components/ui/AppAlert.vue'
import AppButton from '../components/ui/AppButton.vue'
import AppInput from '../components/ui/AppInput.vue'
import { useAuthStore } from '../stores/auth'

const router=useRouter();const route=useRoute();const auth=useAuthStore()
const form=reactive({email:'',password:''});const loading=ref(false);const error=ref('')

async function submit(){if(loading.value)return;error.value='';loading.value=true
  try{const data=await auth.login(form.email,form.password);if(data.usuario.must_change_password){await router.push({name:'change-password'});return}if(!data.usuario.email_verified){await router.push({name:'verify-email',query:{email:data.usuario.email}});return}
    const redirect=typeof route.query.redirect==='string'&&route.query.redirect.startsWith('/')&&!route.query.redirect.startsWith('//')?route.query.redirect:'/dashboard';await router.push(redirect)
  }catch(failure){error.value=failure.message}finally{loading.value=false}}
</script>

<template>
  <AuthShell>
    <template #title>Ingresá a tu cuenta</template>
    <template #description>Usá tu correo y contraseña para continuar en GymTrack.</template>
    <AppAlert v-if="error" tone="danger" title="No pudimos iniciar sesión"><p>{{ error }}</p></AppAlert>
    <form class="auth-form" novalidate @submit.prevent="submit">
      <AppInput v-model.trim="form.email" label="Correo electrónico" name="email" type="email" autocomplete="email" inputmode="email" maxlength="150" required :disabled="loading" />
      <div class="password-row"><span>Contraseña</span><RouterLink :to="{name:'forgot-password'}">¿La olvidaste?</RouterLink></div>
      <AppInput v-model="form.password" class="password-field" label="Contraseña" name="password" type="password" autocomplete="current-password" maxlength="200" required :disabled="loading" />
      <AppButton type="submit" block :loading="loading">Ingresar</AppButton>
    </form>
    <template #footer>¿No tenés cuenta? <RouterLink :to="{name:'registro'}">Creá una cuenta de socio</RouterLink></template>
  </AuthShell>
</template>

<style scoped>
.auth-form{display:grid;gap:var(--space-5)}.auth-form :deep(.alert){margin-bottom:var(--space-2)}.password-row{display:flex;justify-content:space-between;gap:var(--space-3);margin-bottom:calc(var(--space-5) * -1);color:var(--text-primary);font-size:.875rem;font-weight:660}.password-row a{color:var(--status-info-strong);font-weight:650;text-underline-offset:.2em}.password-field :deep(.field__label){position:absolute;width:1px;height:1px;overflow:hidden;clip:rect(0,0,0,0)}
</style>
