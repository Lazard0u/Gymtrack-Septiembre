<!--
  Vista de ruta MemberProfileView. Coordina componentes, estado reactivo y llamadas a la API para completar este flujo de usuario.
  En <script> se declaran imports, estado y funciones; <template> describe la interfaz y <style> limita su presentación.
-->
<script setup>
import { computed, onBeforeUnmount, onMounted, reactive, ref } from 'vue'
import { IconCamera, IconHeart, IconIdBadge2, IconMapPin, IconSettings } from '@tabler/icons-vue'
import { useAuthStore } from '../stores/auth'
import { useMemberStore } from '../stores/member'
import MemberBottomNav from '../components/member/MemberBottomNav.vue'
import MemberTopNav from '../components/member/MemberTopNav.vue'
import PublicFooter from '../components/public/PublicFooter.vue'
import AppAlert from '../components/ui/AppAlert.vue'
import AppButton from '../components/ui/AppButton.vue'
import AppCard from '../components/ui/AppCard.vue'
import AppCheckbox from '../components/ui/AppCheckbox.vue'
import AppEmptyState from '../components/ui/AppEmptyState.vue'
import AppErrorState from '../components/ui/AppErrorState.vue'
import AppInput from '../components/ui/AppInput.vue'
import AppLinkButton from '../components/ui/AppLinkButton.vue'
import AppSelect from '../components/ui/AppSelect.vue'
import AppSkeleton from '../components/ui/AppSkeleton.vue'
import AppToast from '../components/ui/AppToast.vue'

const auth = useAuthStore()
const member = useMemberStore()
const contexts = computed(() => (auth.user?.gimnasios || []).filter((gym) => gym.rol_nombre === 'socio'))
const contextOptions = computed(() => [{ value: '', label: 'Seleccioná un gimnasio' }, ...contexts.value.map((gym) => ({ value: String(gym.gimnasio_id), label: gym.nombre }))])
const hasContext = computed(() => Boolean(auth.user?.active_gym_id))
const initials = computed(() => `${member.profile?.first_name?.[0] || auth.user?.nombre?.[0] || ''}${member.profile?.last_name?.[0] || auth.user?.apellido?.[0] || ''}`.toUpperCase())
const profileForm = reactive({ first_name: '', last_name: '', phone: '', birth_date: '' })
const fields = ref({})
const formError = ref('')
const photoPreview = ref('')
const photoInput = ref(null)
const selectedPhoto = ref(null)
const toast = reactive({ open: false, title: '', message: '', tone: 'success' })

function applyProfile() {
  Object.assign(profileForm, {
    first_name: member.profile?.first_name || '',
    last_name: member.profile?.last_name || '',
    phone: member.profile?.phone || '',
    birth_date: member.profile?.birth_date || '',
  })
}
function notify(title, message = '', tone = 'success') { Object.assign(toast, { open: true, title, message, tone }) }
async function load() {
  if (!hasContext.value) return
  const results = await Promise.allSettled([member.loadProfile(), member.loadFavorites()])
  if (results[0].status === 'fulfilled' && results[0].value) applyProfile()
}
async function switchGym(value) {
  if (!value) return
  try { await auth.cambiarGimnasio(Number(value)); member.reset(); await load() }
  catch (error) { notify('No se pudo cambiar el gimnasio', error.message, 'danger') }
}
async function saveProfile() {
  fields.value = {}
  formError.value = ''
  try {
    await member.saveProfile({ ...profileForm })
    applyProfile()
    notify('Perfil actualizado', 'Tus datos personales quedaron guardados.')
  } catch (error) { fields.value = error.fields || {}; formError.value = error.message }
}
function selectPhoto(event) {
  const file = event.target.files?.[0] || null
  if (photoPreview.value) URL.revokeObjectURL(photoPreview.value)
  selectedPhoto.value = file
  photoPreview.value = file ? URL.createObjectURL(file) : ''
}
async function uploadPhoto() {
  if (!selectedPhoto.value) return
  formError.value = ''
  try {
    await member.uploadPhoto(selectedPhoto.value)
    selectedPhoto.value = null
    if (photoPreview.value) URL.revokeObjectURL(photoPreview.value)
    photoPreview.value = ''
    if (photoInput.value) photoInput.value.value = ''
    notify('Foto actualizada')
  } catch (error) { formError.value = error.message; fields.value = error.fields || {} }
}
async function toggleActivity(activity, checked) {
  try { await member.setFavorite('activity', activity.id, checked); notify(checked ? 'Actividad guardada' : 'Actividad eliminada') }
  catch (error) { notify('No se pudo actualizar', error.message, 'danger') }
}
async function removeGym(gym) {
  try { await member.setFavorite('gym', gym.id, false); notify('Gimnasio eliminado de favoritos') }
  catch (error) { notify('No se pudo actualizar', error.message, 'danger') }
}

onMounted(load)
onBeforeUnmount(() => { if (photoPreview.value) URL.revokeObjectURL(photoPreview.value) })
</script>

<template>
  <div class="member-page">
    <MemberTopNav />
    <main class="container profile-main">
      <header class="page-heading"><div><h1>Tu perfil, bajo tu control.</h1><p>Actualizá tus datos, elegí actividades y administrá los gimnasios que querés volver a visitar.</p></div><div class="heading-actions"><AppLinkButton :to="{ name: 'member-card' }" variant="secondary"><template #icon><IconIdBadge2 :size="18" /></template>Mi carné</AppLinkButton><AppLinkButton :to="{ name: 'member-preferences' }"><template #icon><IconSettings :size="18" /></template>Preferencias</AppLinkButton></div></header>

      <AppCard v-if="contexts.length" class="context-card"><AppSelect :model-value="String(auth.user?.active_gym_id || '')" label="Gimnasio" :options="contextOptions" hint="Tus actividades y preferencias se guardan por gimnasio." @update:model-value="switchGym" /></AppCard>
      <AppEmptyState v-if="!contexts.length" title="No tenés un gimnasio asociado" description="Necesitás una asociación activa como socio para completar este perfil."><template #action><AppLinkButton :to="{ name: 'gyms' }">Explorar gimnasios</AppLinkButton></template></AppEmptyState>
      <AppEmptyState v-else-if="!hasContext" title="Elegí un gimnasio" description="Seleccioná el contexto donde querés administrar tu perfil de socio." />
      <div v-else-if="member.profileStatus === 'loading'" class="profile-loading"><AppSkeleton height="17rem" /><AppSkeleton height="22rem" /></div>
      <AppErrorState v-else-if="member.profileStatus === 'error'" title="No pudimos cargar tu perfil" :description="member.error" @retry="load" />
      <template v-else>
        <AppAlert v-if="formError" tone="danger" title="No pudimos guardar"><p>{{ formError }}</p></AppAlert>
        <div class="profile-layout">
          <aside class="identity-panel" aria-label="Foto y datos de cuenta">
            <div class="avatar">
              <img v-if="photoPreview || member.profile?.avatar_url" :src="photoPreview || member.profile.avatar_url" alt="Tu foto de perfil" />
              <span v-else aria-hidden="true">{{ initials }}</span>
            </div>
            <div><strong>{{ member.profile?.first_name }} {{ member.profile?.last_name }}</strong><span>{{ member.profile?.email }}</span><small>Número de socio: {{ member.profile?.member_number || 'Pendiente' }}</small></div>
            <input ref="photoInput" class="visually-hidden" type="file" accept="image/jpeg,image/png,image/webp" aria-label="Seleccionar foto de perfil" @change="selectPhoto" />
            <AppButton variant="secondary" block @click="photoInput?.click()"><template #icon><IconCamera :size="18" /></template>Elegir foto</AppButton>
            <AppButton v-if="selectedPhoto" block :loading="member.working === 'photo'" @click="uploadPhoto">Guardar foto</AppButton>
            <p>JPG, PNG o WebP. Entre 128 y 6000 px, hasta 5 MB.</p>
          </aside>

          <form class="profile-form" @submit.prevent="saveProfile">
            <div><h2>Datos personales</h2><p>El correo pertenece a tu cuenta y se cambia mediante un flujo de verificación separado.</p></div>
            <div class="form-grid"><AppInput v-model="profileForm.first_name" label="Nombre" name="member-first-name" autocomplete="given-name" minlength="2" maxlength="100" required :error="fields.first_name" /><AppInput v-model="profileForm.last_name" label="Apellido" name="member-last-name" autocomplete="family-name" minlength="2" maxlength="100" required :error="fields.last_name" /><AppInput v-model="profileForm.phone" label="Teléfono" name="member-phone" type="tel" autocomplete="tel" maxlength="20" :error="fields.phone" /><AppInput v-model="profileForm.birth_date" label="Fecha de nacimiento" name="member-birth-date" type="date" min="1900-01-01" :max="new Date().toISOString().slice(0,10)" :error="fields.birth_date" /></div>
            <AppButton type="submit" :loading="member.working === 'profile'">Guardar datos</AppButton>
          </form>
        </div>

        <section class="favorites-section" aria-labelledby="activities-title">
          <div class="section-heading"><div><h2 id="activities-title">Actividades favoritas</h2><p>Usamos esta selección sólo para organizar tu experiencia. Las recomendaciones automáticas continúan desactivadas.</p></div><IconHeart :size="24" /></div>
          <div v-if="member.favoritesStatus === 'loading'" class="favorite-loading"><AppSkeleton v-for="item in 4" :key="item" height="4rem" /></div>
          <AppErrorState v-else-if="member.favoritesStatus === 'error'" :description="member.error" @retry="member.loadFavorites" />
          <AppEmptyState v-else-if="!member.favorites.available_activities.length" title="No hay actividades disponibles" description="Este gimnasio todavía no publicó un catálogo de actividades." />
          <div v-else class="activity-grid"><AppCheckbox v-for="activity in member.favorites.available_activities" :key="activity.id" :model-value="activity.favorite" :label="activity.nombre" :description="activity.descripcion || 'Actividad disponible en este gimnasio.'" :disabled="member.working === `favorite-activity-${activity.id}`" @update:model-value="toggleActivity(activity, $event)" /></div>
        </section>

        <section class="favorites-section" aria-labelledby="gyms-favorites-title">
          <div class="section-heading"><div><h2 id="gyms-favorites-title">Gimnasios guardados</h2><p>Guardá desde el mapa las sedes que querés comparar o consultar más adelante.</p></div><AppLinkButton :to="{ name: 'gyms' }" variant="secondary" size="sm"><template #icon><IconMapPin :size="17" /></template>Abrir mapa</AppLinkButton></div>
          <AppEmptyState v-if="!member.favorites.gyms.length" title="Todavía no guardaste gimnasios" description="Abrí el mapa y usá Guardar en la tarjeta de cualquier gimnasio publicado." />
          <div v-else class="favorite-gyms"><article v-for="gym in member.favorites.gyms" :key="gym.id"><div><strong>{{ gym.nombre }}</strong><span>{{ gym.ciudad }}, {{ gym.departamento }}</span></div><div><AppLinkButton :to="{ name: 'gym-detail', params: { slug: gym.slug } }" variant="ghost" size="sm">Ver ficha</AppLinkButton><AppButton variant="ghost" size="sm" :loading="member.working === `favorite-gym-${gym.id}`" @click="removeGym(gym)">Quitar</AppButton></div></article></div>
        </section>
      </template>
    </main>
    <PublicFooter />
    <MemberBottomNav />
    <AppToast v-bind="toast" @close="toast.open = false" />
  </div>
</template>

<style scoped>
.member-page { min-height: 100vh; background: var(--bg-canvas); }.profile-main { min-height: 75vh; padding-bottom: var(--space-20); }.page-heading { display: flex; align-items: flex-end; justify-content: space-between; gap: var(--space-8); padding-block: clamp(var(--space-10),7vw,var(--space-16)); }.page-heading h1 { max-width: 48rem; margin: 0 0 var(--space-4); font-size: clamp(2.2rem,5vw,4rem); }.page-heading p { max-width: 44rem; margin: 0; color: var(--text-secondary); }.heading-actions { display: flex; flex-wrap: wrap; justify-content: flex-end; gap: var(--space-2); }.context-card { width: min(100%,30rem); margin-bottom: var(--space-8); padding: var(--space-4); }.profile-loading { display: grid; grid-template-columns: minmax(15rem,.65fr) minmax(0,1.35fr); gap: var(--space-4); }.profile-main > :deep(.alert) { margin-bottom: var(--space-5); }.profile-main :deep(.alert p) { margin: 0; }
.profile-layout { display: grid; grid-template-columns: minmax(16rem,.65fr) minmax(0,1.35fr); gap: var(--space-8); align-items: start; }.identity-panel { display: grid; justify-items: start; gap: var(--space-4); border-block: 1px solid var(--border-subtle); padding-block: var(--space-6); }.avatar { display: grid; width: 8rem; height: 8rem; place-items: center; overflow: hidden; border-radius: 50%; background: var(--surface-2); color: var(--status-info-strong); font-size: 2.4rem; font-weight: 780; }.avatar img { width: 100%; height: 100%; object-fit: cover; }.identity-panel > div:nth-child(2) { display: grid; min-width: 0; gap: var(--space-1); }.identity-panel > div:nth-child(2) strong { font-size: 1.15rem; overflow-wrap: anywhere; }.identity-panel > div:nth-child(2) span,.identity-panel > div:nth-child(2) small,.identity-panel > p { color: var(--text-tertiary); font-size: .75rem; overflow-wrap: anywhere; }.identity-panel > p { margin: 0; }.identity-panel :deep(.app-button) { max-width: 18rem; }
.profile-form { display: grid; gap: var(--space-6); }.profile-form h2 { margin: 0 0 var(--space-2); font-size: clamp(1.7rem,3vw,2.5rem); }.profile-form > div:first-child p { max-width: 60ch; margin: 0; color: var(--text-secondary); }.form-grid { display: grid; grid-template-columns: 1fr 1fr; gap: var(--space-4); }.profile-form > :deep(.app-button) { justify-self: start; }
.favorites-section { margin-top: var(--space-16); border-top: 1px solid var(--border-subtle); padding-top: var(--space-8); }.section-heading { display: flex; align-items: flex-start; justify-content: space-between; gap: var(--space-6); margin-bottom: var(--space-6); }.section-heading h2 { margin: 0 0 var(--space-2); font-size: clamp(1.7rem,3vw,2.5rem); }.section-heading p { max-width: 62ch; margin: 0; color: var(--text-secondary); }.section-heading > svg { color: var(--info); }.favorite-loading,.activity-grid { display: grid; grid-template-columns: 1fr 1fr; gap: var(--space-3); }.activity-grid :deep(.checkbox) { border-bottom: 1px solid var(--border-subtle); padding: var(--space-3) 0; }.favorite-gyms { border-top: 1px solid var(--border-subtle); }.favorite-gyms article { display: flex; min-width: 0; align-items: center; justify-content: space-between; gap: var(--space-4); border-bottom: 1px solid var(--border-subtle); padding: var(--space-4) 0; }.favorite-gyms article > div:first-child { display: grid; min-width: 0; gap: var(--space-1); }.favorite-gyms article strong,.favorite-gyms article span { overflow-wrap: anywhere; }.favorite-gyms article span { color: var(--text-tertiary); font-size: .75rem; }.favorite-gyms article > div:last-child { display: flex; flex: 0 0 auto; gap: var(--space-1); }
@media (max-width: 63.99rem) { .profile-layout { grid-template-columns: minmax(14rem,.55fr) minmax(0,1.45fr); } }
@media (max-width: 47.99rem) { .page-heading,.section-heading { align-items: stretch; flex-direction: column; }.heading-actions { display: grid; grid-template-columns: 1fr; }.profile-loading,.profile-layout,.form-grid,.favorite-loading,.activity-grid { grid-template-columns: 1fr; }.identity-panel { justify-items: center; text-align: center; }.identity-panel :deep(.app-button) { width: 100%; max-width: none; }.profile-form > :deep(.app-button) { width: 100%; }.favorite-gyms article { align-items: flex-start; flex-direction: column; }.favorite-gyms article > div:last-child { display: grid; width: 100%; grid-template-columns: 1fr 1fr; }.favorite-gyms article > div:last-child > * { width: 100%; } }
</style>
