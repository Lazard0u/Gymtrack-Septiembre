<script setup>
import { computed, ref } from 'vue'
import { useRouter } from 'vue-router'
import { IconMenu2 } from '@tabler/icons-vue'
import { useAuthStore } from '../../stores/auth'
import AppButton from '../ui/AppButton.vue'
import AppDrawer from '../ui/AppDrawer.vue'
import AppIconButton from '../ui/AppIconButton.vue'
import AppLinkButton from '../ui/AppLinkButton.vue'
import brandMark from '../../assets/gymtrack-mark.svg'

const router = useRouter()
const auth = useAuthStore()
const menuOpen = ref(false)
const hasSession = computed(() => Boolean(auth.token))

const links = [
  { label: 'Funciones', to: { name: 'home', hash: '#funciones' } },
  { label: 'Gimnasios', to: { name: 'gyms' } },
  { label: 'Planes', to: { name: 'plans' } },
  { label: 'Para gimnasios', to: { name: 'for-gyms' } },
]

function closeMenu() {
  menuOpen.value = false
}

async function logout() {
  await auth.logout()
  closeMenu()
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

      <AppIconButton class="mobile-trigger" label="Abrir menú" @click="menuOpen = true"><IconMenu2 :size="22" /></AppIconButton>
    </div>

    <AppDrawer :open="menuOpen" title="Menú" @close="closeMenu">
      <nav class="mobile-nav" aria-label="Navegación móvil">
        <RouterLink v-for="link in links" :key="link.label" :to="link.to" @click="closeMenu">{{ link.label }}</RouterLink>
      </nav>
      <template #footer>
        <div class="mobile-actions" v-if="hasSession">
          <AppLinkButton :to="{ name: 'dashboard' }" variant="secondary" block @click="closeMenu">Mi panel</AppLinkButton>
          <AppButton variant="ghost" block @click="logout">Cerrar sesión</AppButton>
        </div>
        <div class="mobile-actions" v-else>
          <AppLinkButton :to="{ name: 'login' }" variant="secondary" block @click="closeMenu">Ingresar</AppLinkButton>
          <AppLinkButton :to="{ name: 'registro' }" block @click="closeMenu">Crear cuenta</AppLinkButton>
        </div>
      </template>
    </AppDrawer>
  </header>
</template>

<style scoped>
.site-header { position: sticky; top: 0; z-index: var(--z-header); border-bottom: 1px solid var(--border-glass); background: var(--surface-glass); backdrop-filter: blur(16px); }
.site-header__inner { display: flex; min-height: var(--header-height); align-items: center; gap: var(--space-6); }
.brand { display: inline-flex; align-items: center; gap: var(--space-2); flex: 0 0 auto; text-decoration: none; }
.desktop-nav { display: flex; align-items: center; gap: var(--space-6); margin-inline: auto; }
.desktop-nav a, .mobile-nav a { color: var(--text-secondary); font-size: .86rem; font-weight: 650; text-decoration: none; transition: color var(--duration-fast); }
.desktop-nav a:hover, .desktop-nav a.router-link-active, .mobile-nav a:hover, .mobile-nav a.router-link-active { color: var(--text-primary); }
.desktop-actions { display: flex; align-items: center; gap: var(--space-2); }
.mobile-trigger { display: none; margin-left: auto; }
.mobile-nav { display: grid; }.mobile-nav a { border-bottom: 1px solid var(--border-subtle); padding: var(--space-4) var(--space-2); font-size: 1rem; }
.mobile-actions { display: grid; gap: var(--space-3); }
@media (max-width: 63.99rem) { .desktop-nav, .desktop-actions { display: none; }.mobile-trigger { display: inline-grid; } }
</style>
