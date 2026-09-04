<!--
  Componente NotificationBell del centro de notificaciones. Sincroniza interacción visible con el store de notificaciones.
  En <script> se declaran imports, estado y funciones; <template> describe la interfaz y <style> limita su presentación.
-->
<script setup>
import { onMounted } from 'vue'
import { IconBell } from '@tabler/icons-vue'
import { useNotificationsStore } from '../../stores/notifications'
import AppIconButton from '../ui/AppIconButton.vue'

defineProps({ label: { type: String, default: 'Ver notificaciones' } })
defineEmits(['open'])
const notifications = useNotificationsStore()
onMounted(() => notifications.load())
</script>

<template>
  <span class="notification-bell">
    <AppIconButton :label="label" @click="$emit('open')"><IconBell :size="19" /></AppIconButton>
    <span v-if="notifications.unread" class="notification-bell__count" aria-live="polite">{{ notifications.unread > 99 ? '99+' : notifications.unread }}</span>
  </span>
</template>

<style scoped>
.notification-bell { position: relative; display: inline-flex; }.notification-bell__count { position: absolute; top: -.3rem; right: -.4rem; display: grid; min-width: 1.25rem; height: 1.25rem; place-items: center; border: 2px solid var(--bg-canvas); border-radius: var(--radius-pill); padding-inline: .2rem; background: var(--danger); color: var(--text-on-accent); font-size: .62rem; font-weight: 780; font-variant-numeric: tabular-nums; pointer-events: none; }
</style>
