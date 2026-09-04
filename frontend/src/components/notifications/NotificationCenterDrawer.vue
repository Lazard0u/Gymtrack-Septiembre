<!--
  Componente NotificationCenterDrawer del centro de notificaciones. Sincroniza interacción visible con el store de notificaciones.
  En <script> se declaran imports, estado y funciones; <template> describe la interfaz y <style> limita su presentación.
-->
<script setup>
import { computed, ref, watch } from 'vue'
import { IconBellOff, IconCheck, IconSettings } from '@tabler/icons-vue'
import { useRouter } from 'vue-router'
import { useNotificationsStore } from '../../stores/notifications'
import AppAlert from '../ui/AppAlert.vue'
import AppButton from '../ui/AppButton.vue'
import AppDrawer from '../ui/AppDrawer.vue'
import AppEmptyState from '../ui/AppEmptyState.vue'
import AppErrorState from '../ui/AppErrorState.vue'
import AppSkeleton from '../ui/AppSkeleton.vue'

const props = defineProps({ open: Boolean, preferencesRoute: { type: [String, Object], default: '' } })
const emit = defineEmits(['close'])
const notifications = useNotificationsStore()
const router = useRouter()
const working = ref(false)
const actionError = ref('')
const actionRoutes = { member_payments: { name: 'member-payments' }, member_schedule: { name: 'member-schedule' }, member_profile: { name: 'member-profile' }, admin_promotions: { name: 'admin-promotions' } }
const internalPaths = { '/pagos': { name: 'member-payments' }, '/agenda': { name: 'member-schedule' }, '/perfil': { name: 'member-profile' }, '/administracion/promociones': { name: 'admin-promotions' } }
const canMarkAll = computed(() => notifications.unread > 0 && !working.value)

function formatDate(value) {
  if (!value) return ''
  return new Intl.DateTimeFormat('es-UY', { dateStyle: 'medium', timeStyle: 'short' }).format(new Date(String(value).replace(' ', 'T')))
}
async function read(item) {
  actionError.value = ''
  try {
    if (!item.read_at && !item.leida_en && !item.leida) await notifications.markRead(item.id)
  } catch (error) {
    actionError.value = error.message || 'No se pudo marcar la notificación. Intentá nuevamente.'
    return
  }
  const route = actionRoutes[item.action?.kind] || internalPaths[item.link_url]
  if (route) { emit('close'); await router.push(route) }
}
async function readAll() {
  working.value = true
  actionError.value = ''
  try { await notifications.markAllRead() }
  catch (error) { actionError.value = error.message || 'No se pudieron marcar las notificaciones. Intentá nuevamente.' }
  finally { working.value = false }
}
async function openPreferences() { emit('close'); await router.push(props.preferencesRoute || { name: 'member-preferences' }) }
watch(() => props.open, (open) => {
  if (!open) actionError.value = ''
  else if (notifications.status === 'idle') notifications.load()
})
</script>

<template>
  <AppDrawer :open="open" title="Notificaciones" @close="emit('close')">
    <AppAlert v-if="actionError" class="notification-action-error" tone="danger" title="No se guardó el cambio"><p>{{ actionError }}</p></AppAlert>
    <div v-if="notifications.status === 'loading'" class="notification-loading"><AppSkeleton v-for="item in 5" :key="item" height="5.5rem" /></div>
    <AppErrorState v-else-if="notifications.status === 'error'" :description="notifications.error" @retry="notifications.load()" />
    <AppEmptyState v-else-if="notifications.status === 'empty'" title="Estás al día" description="Los avisos de reservas, pagos y promociones aparecerán acá."><template #icon><IconBellOff :size="24" /></template></AppEmptyState>
    <ol v-else class="notification-list">
      <li v-for="item in notifications.items" :key="item.id" :class="{ 'notification-item--unread': !item.read_at }">
        <button type="button" @click="read(item)"><span class="notification-item__dot" aria-hidden="true" /><span><strong>{{ item.title }}</strong><small>{{ item.body }}</small><time>{{ formatDate(item.created_at) }}</time></span></button>
      </li>
    </ol>
    <template #footer><div class="notification-footer"><AppButton variant="secondary" :disabled="!canMarkAll" :loading="working" @click="readAll"><template #icon><IconCheck :size="17" /></template>Marcar todo como leído</AppButton><AppButton v-if="preferencesRoute" variant="ghost" @click="openPreferences"><template #icon><IconSettings :size="17" /></template>Preferencias</AppButton></div></template>
  </AppDrawer>
</template>

<style scoped>
.notification-action-error { margin-bottom: var(--space-4); }.notification-action-error p { margin: 0; }.notification-loading { display: grid; gap: var(--space-2); }.notification-list { display: grid; gap: 1px; margin: calc(var(--space-5) * -1); padding: 0; list-style: none; }.notification-action-error + .notification-list { margin-top: 0; }.notification-list li { border-bottom: 1px solid var(--border-subtle); background: var(--surface-1); transition: background-color var(--duration-fast) var(--ease-standard); }.notification-list li.notification-item--unread { background: var(--accent-soft); }.notification-list button { display: grid; width: 100%; min-height: 5.5rem; grid-template-columns: .55rem minmax(0, 1fr); gap: var(--space-3); border: 0; padding: var(--space-4) var(--space-5); background: transparent; color: var(--text-primary); cursor: pointer; text-align: left; }.notification-list button:hover { background: rgba(255,255,255,.025); }.notification-item__dot { width: .5rem; height: .5rem; margin-top: .35rem; border-radius: 50%; background: transparent; }.notification-item--unread .notification-item__dot { background: var(--info); }.notification-list button > span:last-child { display: grid; min-width: 0; gap: var(--space-1); }.notification-list strong,.notification-list small { overflow-wrap: anywhere; }.notification-list strong { font-size: .85rem; }.notification-list small { color: var(--text-secondary); font-size: .76rem; line-height: 1.5; }.notification-list time { color: var(--text-tertiary); font-size: .67rem; }.notification-footer { display: grid; gap: var(--space-2); }
@media (prefers-reduced-motion: reduce) { .notification-list li { transition: none; } }
</style>
