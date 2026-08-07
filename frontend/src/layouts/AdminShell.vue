<script setup>
import { computed, onMounted, ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { IconBell, IconChevronRight, IconLayoutSidebarLeftCollapse, IconLogout, IconUserCircle } from '@tabler/icons-vue'
import AdminNavigation from '../components/admin/AdminNavigation.vue'
import GymContextSelector from '../components/admin/GymContextSelector.vue'
import AppAlert from '../components/ui/AppAlert.vue'
import AppBadge from '../components/ui/AppBadge.vue'
import AppButton from '../components/ui/AppButton.vue'
import AppDialog from '../components/ui/AppDialog.vue'
import AppDrawer from '../components/ui/AppDrawer.vue'
import AppErrorState from '../components/ui/AppErrorState.vue'
import AppIconButton from '../components/ui/AppIconButton.vue'
import AppSkeleton from '../components/ui/AppSkeleton.vue'
import { useAdminStore } from '../stores/admin'
import { useAuthStore } from '../stores/auth'
import brandMark from '../assets/gymtrack-mark.svg'

const admin = useAdminStore()
const auth = useAuthStore()
const route = useRoute()
const router = useRouter()
const mobileOpen = ref(false)
const notificationsOpen = ref(false)
const title = computed(() => route.meta.adminLabel || 'Administración')

async function initialize() {
  const ok = await admin.loadContext()
  if (ok && admin.hasContext && route.name === 'admin-context') await router.replace({ name: 'admin-summary' })
}

async function logout() {
  await auth.logout()
  await router.push({ name: 'home' })
}

onMounted(initialize)
</script>

<template>
  <div class="admin-shell">
    <aside class="admin-sidebar">
      <RouterLink class="admin-brand" :to="{ name: 'dashboard' }" aria-label="GymTrack, volver a mi panel">
        <img :src="brandMark" alt="" width="34" height="34" />
        <span>Gym<span>Track</span></span>
      </RouterLink>
      <div class="admin-sidebar__label">Administración</div>
      <AdminNavigation />
      <div class="admin-sidebar__footer">
        <AppBadge v-if="admin.isDemo" tone="info">Entorno de demostración</AppBadge>
        <RouterLink :to="{ name: 'dashboard' }">Volver a mi panel</RouterLink>
      </div>
    </aside>

    <div class="admin-workspace">
      <header class="admin-header">
        <div class="admin-header__start">
          <AppIconButton class="admin-menu" label="Abrir navegación administrativa" @click="mobileOpen = true"><IconLayoutSidebarLeftCollapse :size="21" /></AppIconButton>
          <nav class="breadcrumbs" aria-label="Migas de pan"><RouterLink :to="{ name: 'admin-summary' }">Administración</RouterLink><IconChevronRight :size="15" /><span aria-current="page">{{ title }}</span></nav>
        </div>
        <div class="admin-header__actions">
          <GymContextSelector @changed="router.push({ name: 'admin-summary' })" />
          <AppIconButton label="Ver notificaciones" @click="notificationsOpen = true"><IconBell :size="19" /></AppIconButton>
          <details class="profile-menu">
            <summary aria-label="Abrir menú de cuenta"><IconUserCircle :size="23" /><span>{{ auth.user?.nombre }}</span></summary>
            <div class="profile-menu__panel"><strong>{{ auth.user?.nombre }} {{ auth.user?.apellido }}</strong><span>{{ auth.user?.email }}</span><RouterLink :to="{ name: 'dashboard' }">Mi panel</RouterLink><button type="button" @click="logout"><IconLogout :size="16" /> Cerrar sesión</button></div>
          </details>
        </div>
      </header>

      <main id="admin-content" class="admin-content">
        <div v-if="admin.status === 'loading'" class="admin-loading" aria-label="Cargando administración"><AppSkeleton height="2rem" width="16rem" /><AppSkeleton height="8rem" /><AppSkeleton height="18rem" /></div>
        <AppErrorState v-else-if="admin.status === 'error'" title="No pudimos abrir Administración" :description="admin.error" @retry="initialize" />
        <section v-else-if="!admin.hasContext && route.name !== 'admin-gym-create'" class="context-gate" aria-labelledby="context-title">
          <h1 id="context-title">Elegí un gimnasio para continuar</h1><p>Las consultas, permisos y acciones administrativas siempre se limitan al gimnasio activo.</p><GymContextSelector @changed="router.replace({ name: 'admin-summary' })" />
          <AppAlert v-if="admin.isGlobalAdmin" tone="warning" title="Modo soporte"><p>Como administrador general, el acceso requiere un motivo y queda registrado en la auditoría.</p></AppAlert>
          <AppButton v-if="admin.hasPermission('gym.configure')" variant="secondary" @click="router.push({ name: 'admin-gym-create' })">Crear un gimnasio</AppButton>
        </section>
        <RouterView v-else :key="`${route.fullPath}:${admin.version}`" />
      </main>
    </div>

    <AppDrawer :open="mobileOpen" title="Administración" side="left" @close="mobileOpen = false"><AdminNavigation @navigate="mobileOpen = false" /><template #footer><AppButton variant="secondary" block @click="router.push({ name: 'dashboard' }); mobileOpen = false">Volver a mi panel</AppButton></template></AppDrawer>
    <AppDialog :open="notificationsOpen" title="Notificaciones" description="Avisos operativos del gimnasio activo." @close="notificationsOpen = false"><p class="notifications-empty">No tenés notificaciones administrativas nuevas.</p><template #footer><AppButton variant="secondary" @click="notificationsOpen = false">Cerrar</AppButton></template></AppDialog>
  </div>
</template>

<style scoped>
.admin-shell { min-height: 100vh; background: var(--bg-canvas); }
.admin-sidebar { position: fixed; inset: 0 auto 0 0; z-index: var(--z-header); display: flex; width: 16rem; flex-direction: column; border-right: 1px solid var(--border-subtle); padding: var(--space-5) var(--space-3); background: var(--surface-1); }
.admin-brand { display: flex; align-items: center; gap: var(--space-3); padding: 0 var(--space-3) var(--space-5); color: var(--text-primary); font-size: 1.05rem; font-weight: 780; letter-spacing: -.035em; text-decoration: none; }.admin-brand img { width: 2.1rem; }.admin-brand span span { color: var(--info); }
.admin-sidebar__label { padding: var(--space-3); color: var(--text-tertiary); font-size: .65rem; font-weight: 760; letter-spacing: .12em; text-transform: uppercase; }
.admin-sidebar__footer { display: grid; gap: var(--space-3); margin-top: auto; padding: var(--space-5) var(--space-3) 0; border-top: 1px solid var(--border-subtle); }.admin-sidebar__footer a { color: var(--text-secondary); font-size: .78rem; text-decoration: none; }.admin-sidebar__footer a:hover { color: var(--text-primary); }
.admin-workspace { min-width: 0; margin-left: 16rem; }
.admin-header { position: sticky; top: 0; z-index: calc(var(--z-header) - 1); display: flex; min-height: 4.25rem; align-items: center; justify-content: space-between; gap: var(--space-5); border-bottom: 1px solid var(--border-glass); padding: var(--space-2) clamp(var(--space-4), 3vw, var(--space-8)); background: var(--surface-glass); backdrop-filter: blur(16px); }
.admin-header__start, .admin-header__actions { display: flex; align-items: center; gap: var(--space-3); }.admin-header__actions { justify-content: flex-end; }
.breadcrumbs { display: flex; align-items: center; gap: var(--space-2); color: var(--text-tertiary); font-size: .75rem; }.breadcrumbs a { color: var(--text-secondary); text-decoration: none; }.breadcrumbs span { color: var(--text-primary); }
.admin-menu { display: none; }
.profile-menu { position: relative; }.profile-menu summary { display: flex; min-height: 2.4rem; align-items: center; gap: var(--space-2); border-radius: var(--radius-control); padding: 0 var(--space-2); cursor: pointer; color: var(--text-secondary); font-size: .78rem; list-style: none; }.profile-menu summary::-webkit-details-marker { display: none; }.profile-menu summary:hover { background: var(--surface-2); color: var(--text-primary); }
.profile-menu__panel { position: absolute; top: calc(100% + var(--space-2)); right: 0; display: grid; width: 15rem; gap: var(--space-2); border: 1px solid var(--border-strong); border-radius: var(--radius-card); padding: var(--space-4); background: var(--surface-raised); box-shadow: var(--shadow-md); }.profile-menu__panel strong { font-size: .82rem; }.profile-menu__panel > span { overflow-wrap: anywhere; color: var(--text-tertiary); font-size: .72rem; }.profile-menu__panel a, .profile-menu__panel button { display: flex; align-items: center; gap: var(--space-2); border: 0; border-radius: var(--radius-control); padding: var(--space-2); background: transparent; color: var(--text-secondary); cursor: pointer; font-size: .78rem; text-align: left; text-decoration: none; }.profile-menu__panel a:hover, .profile-menu__panel button:hover { background: var(--surface-2); color: var(--text-primary); }
.admin-content { width: min(100%, 96rem); min-height: calc(100vh - 4.25rem); margin-inline: auto; padding: clamp(var(--space-5), 3vw, var(--space-10)); }
.admin-loading { display: grid; gap: var(--space-5); }.context-gate { display: grid; max-width: 44rem; gap: var(--space-5); margin: clamp(var(--space-10), 8vw, var(--space-20)) auto; border: 1px solid var(--border-subtle); border-radius: var(--radius-dialog); padding: clamp(var(--space-6), 5vw, var(--space-10)); background: var(--surface-1); }.context-gate h1 { margin: 0; font-size: clamp(2rem, 5vw, 3.3rem); }.context-gate > p { margin: 0; color: var(--text-secondary); }.notifications-empty { margin: 0; color: var(--text-secondary); }
@media (max-width: 74rem) { .profile-menu summary span { display: none; } }
@media (max-width: 63.99rem) { .admin-sidebar { display: none; }.admin-workspace { margin-left: 0; }.admin-menu { display: inline-flex; }.breadcrumbs a, .breadcrumbs svg { display: none; } }
@media (max-width: 47.99rem) { .admin-header { align-items: flex-start; flex-wrap: wrap; padding-block: var(--space-3); }.admin-header__start { min-height: 2.4rem; }.admin-header__actions { width: 100%; justify-content: space-between; }.admin-header__actions > :first-child { min-width: 0; flex: 1; }.admin-content { padding: var(--space-5) var(--space-4) var(--space-10); } }
</style>
