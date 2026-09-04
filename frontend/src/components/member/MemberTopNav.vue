<!--
  Componente del área del socio MemberTopNav. Resume datos del store y ofrece navegación o acciones según permisos.
  En <script> se declaran imports, estado y funciones; <template> describe la interfaz y <style> limita su presentación.
-->
<script setup>
import { computed, ref } from 'vue'
import { useRouter } from 'vue-router'
import { IconLogout, IconUserCircle } from '@tabler/icons-vue'
import { useAuthStore } from '../../stores/auth'
import { useMemberStore } from '../../stores/member'
import { useNotificationsStore } from '../../stores/notifications'
import NotificationBell from '../notifications/NotificationBell.vue'
import NotificationCenterDrawer from '../notifications/NotificationCenterDrawer.vue'
import AppButton from '../ui/AppButton.vue'
import brandMark from '../../assets/gymtrack-mark.svg'

const auth = useAuthStore()
const member = useMemberStore()
const notifications = useNotificationsStore()
const router = useRouter()
const notificationsOpen = ref(false)
const notificationKey = computed(() => Number(auth.user?.active_gym_id || 0))

async function logout() {
  await auth.logout()
  member.reset()
  notifications.reset()
  await router.push({ name: 'home' })
}
</script>

<template>
  <header class="member-top-nav">
    <div class="container member-top-nav__inner">
      <RouterLink class="member-brand" :to="{ name: 'home' }" aria-label="GymTrack, ir al inicio">
        <img :src="brandMark" alt="" width="36" height="36" />
        <span>Gym<strong>Track</strong></span>
      </RouterLink>
      <nav aria-label="Navegación del socio">
        <RouterLink :to="{ name: 'dashboard' }">Mi panel</RouterLink>
        <RouterLink :to="{ name: 'member-schedule' }">Agenda</RouterLink>
        <RouterLink :to="{ name: 'member-activity' }">Progreso</RouterLink>
        <RouterLink :to="{ name: 'member-payments' }">Pagos</RouterLink>
        <RouterLink :to="{ name: 'member-profile' }">Perfil</RouterLink>
      </nav>
      <div class="member-top-nav__actions">
        <NotificationBell :key="notificationKey" @open="notificationsOpen = true" />
        <RouterLink class="profile-link" :to="{ name: 'member-profile' }" aria-label="Abrir mi perfil"><IconUserCircle :size="21" /></RouterLink>
        <AppButton variant="ghost" size="sm" @click="logout"><template #icon><IconLogout :size="17" /></template>Salir</AppButton>
      </div>
    </div>
    <NotificationCenterDrawer :open="notificationsOpen" :preferences-route="{ name: 'member-preferences' }" @close="notificationsOpen = false" />
  </header>
</template>

<style scoped>
.member-top-nav { position: sticky; top: 0; z-index: var(--z-header); border-bottom: 1px solid var(--border-glass); background: var(--surface-glass); backdrop-filter: blur(16px); }
.member-top-nav__inner { display: flex; min-height: var(--header-height); align-items: center; gap: var(--space-5); }
.member-brand { display: inline-flex; flex: 0 0 auto; align-items: center; gap: var(--space-2); color: var(--text-primary); text-decoration: none; }
.member-brand img { width: 2.25rem; height: 2.25rem; object-fit: contain; }
.member-brand span { font-weight: 780; letter-spacing: -.035em; }
.member-brand strong { color: var(--info); }
.member-top-nav nav { display: flex; min-width: 0; align-items: center; gap: clamp(var(--space-3), 2vw, var(--space-5)); margin-left: auto; white-space: nowrap; }
.member-top-nav nav a { color: var(--text-secondary); font-size: .79rem; font-weight: 660; text-decoration: none; transition: color var(--duration-fast) var(--ease-standard); }
.member-top-nav nav a:hover,.member-top-nav nav a.router-link-active { color: var(--text-primary); }
.member-top-nav__actions { display: flex; flex: 0 0 auto; align-items: center; gap: var(--space-1); }
.profile-link { display: none; width: 2.75rem; height: 2.75rem; place-items: center; border-radius: var(--radius-control); color: var(--text-secondary); }
.profile-link:hover { background: var(--surface-2); color: var(--text-primary); }
@media (max-width: 63.99rem) {
  .member-top-nav nav { display: none; }
  .member-top-nav__actions { margin-left: auto; }
  .profile-link { display: grid; }
}
@media (max-width: 26rem) {
  .member-top-nav__actions :deep(.app-button) { width: 2.75rem; padding: 0; }
  .member-top-nav__actions :deep(.app-button span) { position: absolute; width: 1px; height: 1px; overflow: hidden; clip: rect(0 0 0 0); }
}
@media (prefers-reduced-transparency: reduce) { .member-top-nav { background: var(--bg-canvas); backdrop-filter: none; } }
</style>
