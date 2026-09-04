<!--
  Componente público PublicHeader. Forma parte de la navegación o exploración accesible sin requerir una sesión.
  En <script> se declaran imports, estado y funciones; <template> describe la interfaz y <style> limita su presentación.
-->
<script setup>
import { computed } from 'vue'
import { useRouter } from 'vue-router'
import { IconUserCircle } from '@tabler/icons-vue'
import { useAuthStore } from '../../stores/auth'
import AppButton from '../ui/AppButton.vue'
import AppLinkButton from '../ui/AppLinkButton.vue'
import PublicBottomNav from './PublicBottomNav.vue'
import brandMark from '../../assets/gymtrack-mark.svg'

const router = useRouter()
const auth = useAuthStore()
const hasSession = computed(() => auth.estaAutenticado)

const links = [
  { label: 'Funciones', to: { name: 'home', hash: '#funciones' } },
  { label: 'Gimnasios', to: { name: 'gyms' } },
  { label: 'Planes', to: { name: 'plans' } },
  { label: 'Para gimnasios', to: { name: 'for-gyms' } },
]

async function logout() {
  await auth.logout()
  await router.push({ name: 'home' })
}
</script>

<template>
  <header class="site-header">
    <div class="container site-header__inner">
      <RouterLink class="brand" :to="{ name: 'home' }" aria-label="GymTrack, ir al inicio">
        <img class="brand-mark" :src="brandMark" alt="" width="36" height="36" />
        <span class="brand-word">Gym<span>Track</span></span>
      </RouterLink>

      <nav class="desktop-nav" aria-label="Navegación principal">
        <RouterLink v-for="link in links" :key="link.label" :to="link.to">{{ link.label }}</RouterLink>
      </nav>

      <div class="desktop-actions">
        <template v-if="hasSession">
          <AppLinkButton :to="{ name: 'dashboard' }" variant="secondary" size="sm">Mi panel</AppLinkButton>
          <AppButton variant="ghost" size="sm" @click="logout">Salir</AppButton>
        </template>
        <template v-else>
          <AppLinkButton :to="{ name: 'login' }" variant="ghost" size="sm">Ingresar</AppLinkButton>
          <AppLinkButton :to="{ name: 'registro' }" size="sm">Crear cuenta</AppLinkButton>
        </template>
      </div>

      <div class="mobile-actions-compact">
        <AppLinkButton v-if="!hasSession" :to="{ name: 'registro' }" size="sm">Crear cuenta</AppLinkButton>
        <RouterLink v-else class="mobile-profile" :to="{ name: auth.user?.role === 'socio' ? 'member-profile' : 'dashboard' }" aria-label="Abrir mi cuenta"><IconUserCircle :size="23" /></RouterLink>
      </div>
    </div>
  </header>
  <PublicBottomNav />
</template>

<style scoped>
.site-header { position: sticky; top: 0; z-index: var(--z-header); border-bottom: 1px solid var(--border-glass); background: var(--surface-glass); backdrop-filter: blur(16px); }
.site-header__inner { display: flex; min-height: var(--header-height); align-items: center; gap: var(--space-6); }
.brand { display: inline-flex; align-items: center; gap: var(--space-2); flex: 0 0 auto; text-decoration: none; }
.desktop-nav { display: flex; align-items: center; gap: var(--space-6); margin-inline: auto; }
.desktop-nav a, .mobile-nav a { color: var(--text-secondary); font-size: .86rem; font-weight: 650; text-decoration: none; transition: color var(--duration-fast); }
.desktop-nav a:hover, .desktop-nav a.router-link-active, .mobile-nav a:hover, .mobile-nav a.router-link-active { color: var(--text-primary); }
.desktop-actions { display: flex; align-items: center; gap: var(--space-2); }
.mobile-actions-compact { display: none; margin-left: auto; }
.mobile-profile { display: grid; width: 3rem; height: 3rem; place-items: center; border-radius: var(--radius-control); color: var(--text-secondary); text-decoration: none; }
@media (max-width: 63.99rem) {
  .desktop-nav, .desktop-actions { display: none; }
  .mobile-actions-compact { display: flex; align-items: center; }
  .mobile-actions-compact :deep(.link-button) { min-height: 3rem; }
  :global(.public-shell), :global(.public-page) { padding-bottom: calc(4.25rem + env(safe-area-inset-bottom)); }
}
@media (hover: hover) and (pointer: fine) { .mobile-profile:hover { background: var(--surface-2); color: var(--text-primary); } }
@media (prefers-reduced-transparency: reduce) { .site-header { background: var(--bg-canvas); backdrop-filter: none; } }
</style>
