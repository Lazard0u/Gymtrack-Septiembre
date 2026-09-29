<script setup>
import { nextTick, onBeforeUnmount, onMounted, reactive, ref } from 'vue'
import AuthShell from '../components/auth/AuthShell.vue'
import AppAlert from '../components/ui/AppAlert.vue'
import AppButton from '../components/ui/AppButton.vue'
import AppInput from '../components/ui/AppInput.vue'
import { useAuthStore } from '../stores/auth'
import { useSystemStore } from '../stores/system'

// Estos estados separan errores de formulario de un fallo posterior del correo.
const auth=useAuthStore();const system=useSystemStore();const loading=ref(false);const error=ref('');const success=ref('');const fields=ref({});const mailPending=ref(false)
const form=reactive({nombre:'',apellido:'',email:'',password:'',password_confirmation:'',terms_accepted:false,privacy_accepted:false,marketing_accepted:false})
const turnstileEl=ref(null);const turnstileToken=ref('');let widgetId=null;let pollId=null

function renderTurnstile(){if(!system.turnstileEnabled||!window.turnstile||!turnstileEl.value||widgetId!==null)return;widgetId=window.turnstile.render(turnstileEl.value,{sitekey:system.turnstileSiteKey,theme:'dark',callback:(token)=>{turnstileToken.value=token},'expired-callback':()=>{turnstileToken.value=''},'error-callback':()=>{turnstileToken.value='';error.value='No se pudo completar la verificación anti robot.'}})}
function resetTurnstile(){turnstileToken.value='';if(window.turnstile&&widgetId!==null)window.turnstile.reset(widgetId)}
async function submit(){if(loading.value)return;error.value='';success.value='';mailPending.value=false;fields.value={}
  if(form.password!==form.password_confirmation){fields.value={password_confirmation:'Las contraseñas no coinciden.'};return}
  if(system.turnstileEnabled&&!turnstileToken.value){error.value='Completá la verificación anti robot.';return}
  loading.value=true;try{const data=await auth.registro({...form,turnstileToken:turnstileToken.value});success.value=data.mensaje}catch(failure){if(failure.code==='verification_email_failed'){success.value=failure.message;mailPending.value=true}else{error.value=failure.message;fields.value=failure.fields||{};resetTurnstile()}}finally{loading.value=false}}
onMounted(async()=>{if(!system.loaded)await system.load();await nextTick();if(system.turnstileEnabled){pollId=window.setInterval(()=>{renderTurnstile();if(widgetId!==null){window.clearInterval(pollId);pollId=null}},250)}})
onBeforeUnmount(()=>{if(pollId!==null)window.clearInterval(pollId);if(window.turnstile&&widgetId!==null)window.turnstile.remove(widgetId)})
</script>

<template>
  <AuthShell>
    <template #title>Creá tu cuenta</template><template #description>Registrate como socio. Después podrás vincularte con uno o más gimnasios.</template>
    <AppAlert v-if="error" tone="danger" title="Revisá el registro"><p>{{ error }}</p></AppAlert>
    <AppAlert v-if="success" :tone="mailPending ? 'warning' : 'success'" :title="mailPending ? 'Cuenta creada, envío pendiente' : 'Cuenta creada'"><p>{{ success }}</p><p><RouterLink :to="{name:'verify-email',query:{email:form.email}}">{{ mailPending ? 'Reintentar el envío' : 'Continuar con la verificación' }}</RouterLink></p></AppAlert>
    <form v-if="!success" class="auth-form" novalidate @submit.prevent="submit">
      <div class="name-grid"><AppInput v-model.trim="form.nombre" label="Nombre" name="given-name" autocomplete="given-name" minlength="2" maxlength="100" required :error="fields.nombre" :disabled="loading"/><AppInput v-model.trim="form.apellido" label="Apellido" name="family-name" autocomplete="family-name" minlength="2" maxlength="100" required :error="fields.apellido" :disabled="loading"/></div>
      <AppInput v-model.trim="form.email" label="Correo electrónico" name="email" type="email" autocomplete="email" inputmode="email" maxlength="150" required :error="fields.email" :disabled="loading"/>
      <AppInput v-model="form.password" label="Contraseña" name="password" type="password" autocomplete="new-password" minlength="12" maxlength="200" hint="12 caracteres, con mayúscula, minúscula y número." required :error="fields.password" :disabled="loading"/>
      <AppInput v-model="form.password_confirmation" label="Confirmar contraseña" name="password-confirmation" type="password" autocomplete="new-password" minlength="12" maxlength="200" required :error="fields.password_confirmation" :disabled="loading"/>
      <label class="check"><input v-model="form.terms_accepted" type="checkbox" required :disabled="loading"/><span>Acepto los <RouterLink to="/terminos">términos de uso</RouterLink>.</span></label>
      <label class="check"><input v-model="form.privacy_accepted" type="checkbox" required :disabled="loading"/><span>Acepto la <RouterLink to="/privacidad">política de privacidad</RouterLink>.</span></label>
      <label class="check check--optional"><input v-model="form.marketing_accepted" type="checkbox" :disabled="loading"/><span>Quiero recibir novedades y promociones. Es opcional.</span></label>
      <div v-if="system.turnstileEnabled" ref="turnstileEl" class="turnstile" aria-label="Verificación anti robot"></div>
      <AppButton type="submit" block :loading="loading">Crear cuenta</AppButton>
    </form>
    <template #footer>¿Ya tenés cuenta? <RouterLink :to="{name:'login'}">Iniciá sesión</RouterLink><br/>¿Representás un gimnasio? <RouterLink :to="{name:'owner-register'}">Solicitá acceso</RouterLink></template>
  </AuthShell>
</template>

<style scoped>
.auth-form{display:grid;gap:var(--space-4)}.name-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:var(--space-4)}.check{display:flex;align-items:flex-start;gap:var(--space-3);color:var(--text-secondary);font-size:.8rem;line-height:1.5}.check input{width:1.1rem;height:1.1rem;margin:.12rem 0 0;accent-color:var(--accent);flex:0 0 auto}.check a,.alert a{color:var(--status-info-strong);text-underline-offset:.2em}.check--optional{color:var(--text-tertiary)}.turnstile{min-height:4.1rem;overflow:hidden}@media(max-width:29.99rem){.name-grid{grid-template-columns:1fr}}
</style>
