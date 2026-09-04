<!--
  Vista administrativa AdminMemberCardVerifyView. La ruta comprueba rol y permiso; la vista carga, presenta y modifica el recurso mediante su store.
  En <script> se declaran imports, estado y funciones; <template> describe la interfaz y <style> limita su presentación.
-->
<script setup>
import { computed, ref, watch } from 'vue'
import { IconIdBadge2, IconRefresh, IconScan } from '@tabler/icons-vue'
import { api } from '../../services/api'
import { useAdminStore } from '../../stores/admin'
import AdminPageHeading from '../../components/admin/AdminPageHeading.vue'
import AppAlert from '../../components/ui/AppAlert.vue'
import AppBadge from '../../components/ui/AppBadge.vue'
import AppButton from '../../components/ui/AppButton.vue'
import AppCard from '../../components/ui/AppCard.vue'
import AppEmptyState from '../../components/ui/AppEmptyState.vue'
import AppTextarea from '../../components/ui/AppTextarea.vue'

const admin = useAdminStore()
const token = ref('')
const status = ref('idle')
const error = ref('')
const fieldError = ref('')
const result = ref(null)
const activeGymName = computed(() => admin.activeGym?.nombre || 'el gimnasio activo')

function date(value) {
  if (!value) return 'Sin fecha'
  return new Intl.DateTimeFormat('es-UY', { dateStyle: 'medium' }).format(new Date(`${String(value).slice(0, 10)}T12:00:00`))
}

function reset() {
  token.value = ''
  status.value = 'idle'
  error.value = ''
  fieldError.value = ''
  result.value = null
}

async function verify() {
  const value = token.value.trim()
  fieldError.value = ''
  error.value = ''
  result.value = null
  if (!value) {
    fieldError.value = 'Escaneá o pegá el token del carné.'
    return
  }
  status.value = 'loading'
  const response = await api.post('/admin/member-card/verify', { token: value })
  if (!response.ok || response.data?.error) {
    status.value = 'error'
    fieldError.value = response.data?.fields?.token || ''
    error.value = response.data?.mensaje || 'No se pudo verificar el carné.'
    return
  }
  result.value = response.data.data
  status.value = 'ready'
}

watch(() => admin.version, reset)
</script>

<template>
  <section class="card-verifier">
    <AdminPageHeading title="Verificar carné" description="Validá el QR temporal de un socio contra el gimnasio, dataset y membresía activos." />
    <AppAlert tone="info" title="Control de acceso verificable"><p>Podés pegar el token o usar un lector QR USB que escriba como teclado. La cámara automática no está habilitada y no se simula.</p></AppAlert>

    <AppEmptyState v-if="!admin.hasContext" title="Elegí un gimnasio" description="La validación requiere un contexto activo para impedir que un carné se use en otra sede." />
    <div v-else class="verifier-layout">
      <AppCard as="form" class="verifier-form" @submit.prevent="verify">
        <div class="verifier-form__heading"><IconScan :size="24" aria-hidden="true" /><div><h2>Escanear en {{ activeGymName }}</h2><p>El token es temporal y se valida nuevamente en PHP en cada intento.</p></div></div>
        <AppTextarea v-model="token" label="Token del carné" name="member-card-token" :rows="7" :maxlength="4096" :error="fieldError" placeholder="Pegá aquí el contenido leído desde el QR" required />
        <AppAlert v-if="status === 'error'" tone="danger" title="Carné no verificado"><p>{{ error }}</p></AppAlert>
        <div class="form-actions"><AppButton type="submit" :loading="status === 'loading'"><template #icon><IconScan :size="18" /></template>Verificar ahora</AppButton><AppButton type="button" variant="ghost" :disabled="status === 'loading'" @click="reset"><template #icon><IconRefresh :size="18" /></template>Limpiar</AppButton></div>
      </AppCard>

      <AppCard class="verification-result" :class="{ 'verification-result--valid': result?.valid, 'verification-result--invalid': result && !result.valid }">
        <template v-if="result">
          <div class="verification-result__heading"><IconIdBadge2 :size="28" /><div><AppBadge :tone="result.valid ? 'success' : 'danger'">{{ result.valid ? 'Acceso válido' : 'Acceso no válido' }}</AppBadge><h2>{{ result.member?.name }}</h2><p>{{ result.member?.member_number || 'Número de socio pendiente' }}</p></div></div>
          <dl>
            <div><dt>Estado del socio</dt><dd>{{ result.member?.state || 'Sin estado' }}</dd></div>
            <div><dt>Plan</dt><dd>{{ result.membership?.plan || 'Sin membresía vigente' }}</dd></div>
            <div><dt>Inicio</dt><dd>{{ date(result.membership?.fecha_inicio) }}</dd></div>
            <div><dt>Vencimiento</dt><dd>{{ date(result.membership?.fecha_vencimiento) }}</dd></div>
          </dl>
          <AppAlert :tone="result.valid ? 'success' : 'warning'" :title="result.valid ? 'Ingreso autorizado' : 'Revisión necesaria'"><p>{{ result.valid ? 'La asociación y la membresía están vigentes en este gimnasio.' : 'El carné pertenece al socio, pero no acredita una membresía vigente para autorizar el ingreso.' }}</p></AppAlert>
        </template>
        <AppEmptyState v-else title="Esperando un carné" description="El resultado mostrará únicamente datos obtenidos y verificados desde MySQL." />
      </AppCard>
    </div>
  </section>
</template>

<style scoped>
.card-verifier > :deep(.alert) { margin-bottom: var(--space-6); }.card-verifier :deep(.alert p) { margin: 0; }.verifier-layout { display: grid; grid-template-columns: minmax(18rem,.9fr) minmax(18rem,1.1fr); align-items: start; gap: var(--space-4); }.verifier-form,.verification-result { display: grid; min-width: 0; gap: var(--space-5); }.verifier-form__heading,.verification-result__heading { display: flex; align-items: flex-start; gap: var(--space-3); }.verifier-form__heading > svg,.verification-result__heading > svg { flex: 0 0 auto; color: var(--info); }.verifier-form h2,.verification-result h2 { margin: 0 0 var(--space-2); font-size: 1.25rem; }.verifier-form p,.verification-result__heading p { margin: 0; color: var(--text-secondary); font-size: .82rem; }.form-actions { display: flex; flex-wrap: wrap; gap: var(--space-2); }.verification-result { min-height: 27rem; align-content: start; }.verification-result--valid { border-color: var(--success-soft); }.verification-result--invalid { border-color: var(--warning-soft); }.verification-result__heading h2 { margin-top: var(--space-3); }.verification-result dl { display: grid; grid-template-columns: 1fr 1fr; gap: var(--space-4); margin: 0; }.verification-result dl div { display: grid; gap: var(--space-1); border-bottom: 1px solid var(--border-subtle); padding-bottom: var(--space-3); }.verification-result dt { color: var(--text-tertiary); font-size: .72rem; }.verification-result dd { margin: 0; overflow-wrap: anywhere; text-transform: capitalize; }
@media (max-width: 55rem) { .verifier-layout { grid-template-columns: 1fr; }.verification-result { min-height: 20rem; } }
@media (max-width: 30rem) { .form-actions { display: grid; }.form-actions > * { width: 100%; }.verification-result dl { grid-template-columns: 1fr; } }
</style>
