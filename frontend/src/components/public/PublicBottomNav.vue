<!--
  Componente público PublicBottomNav. Forma parte de la navegación o exploración accesible sin requerir una sesión.
  En <script> se declaran imports, estado y funciones; <template> describe la interfaz y <style> limita su presentación.
-->
<script setup>
import { computed } from 'vue'
import { IconBuildingStore, IconCalendarEvent, IconHome, IconMap2, IconUser, IconWallet } from '@tabler/icons-vue'
import { useAuthStore } from '../../stores/auth'

const auth = useAuthStore()

const items = computed(() => {
  if (auth.user?.role === 'socio') {
    return [
      { route: { name: 'home' }, label: 'Inicio', icon: IconHome },
      { route: { name: 'gyms' }, label: 'Gimnasios', icon: IconMap2 },
      { route: { name: 'member-schedule' }, label: 'Actividad', icon: IconCalendarEvent },
      { route: { name: 'plans' }, label: 'Planes', icon: IconWallet },
      { route: { name: 'member-profile' }, label: 'Perfil', icon: IconUser },
    ]
  }

  if (auth.estaAutenticado) {
    return [
      { route: { name: 'home' }, label: 'Inicio', icon: IconHome },
      { route: { name: 'gyms' }, label: 'Gimnasios', icon: IconMap2 },
      { route: { name: 'admin-summary' }, label: 'Administrar', icon: IconBuildingStore },
      { route: { name: 'plans' }, label: 'Planes', icon: IconWallet },
      { route: { name: 'dashboard' }, label: 'Mi panel', icon: IconUser },
    ]
  }

  return [
    { route: { name: 'home' }, label: 'Inicio', icon: IconHome },
    { route: { name: 'gyms' }, label: 'Gimnasios', icon: IconMap2 },
    { route: { name: 'plans' }, label: 'Planes', icon: IconWallet },
    { route: { name: 'for-gyms' }, label: 'Para gimnasios', icon: IconBuildingStore },
    { route: { name: 'login' }, label: 'Ingresar', icon: IconUser },
  ]
})
</script>

<template>
  <nav class="public-bottom-nav" aria-label="Navegación principal móvil">
    <RouterLink v-for="item in items" :key="item.label" :to="item.route" exact-active-class="public-bottom-nav__link--active">
      <component :is="item.icon" :size="21" stroke-width="1.8" aria-hidden="true" />
      <span>{{ item.label }}</span>
    </RouterLink>
  </nav>
</template>

<style scoped>
.public-bottom-nav { display: none; }

@media (max-width: 63.99rem) {
  .public-bottom-nav { position: fixed; inset: auto 0 0; z-index: var(--z-header); display: grid; grid-template-columns: repeat(5, minmax(0, 1fr)); border-top: 1px solid var(--border-strong); padding: var(--space-1) max(var(--space-1), env(safe-area-inset-right)) max(var(--space-1), env(safe-area-inset-bottom)) max(var(--space-1), env(safe-area-inset-left)); background: var(--surface-glass); backdrop-filter: blur(18px); }
  .public-bottom-nav a { display: grid; min-width: 0; min-height: 3.75rem; place-content: center; justify-items: center; gap: .18rem; border-radius: var(--radius-control); color: var(--text-tertiary); font-size: .62rem; font-weight: 680; line-height: 1; text-align: center; text-decoration: none; transition: background-color var(--duration-fast) var(--ease-standard), color var(--duration-fast) var(--ease-standard), transform var(--duration-fast) var(--ease-out); }
  .public-bottom-nav a.public-bottom-nav__link--active, .public-bottom-nav a.router-link-active { background: var(--accent-soft); color: var(--status-info-strong); }
  .public-bottom-nav a:active { transform: scale(.97); }
}

@media (max-width: 22.5rem) {
  .public-bottom-nav a { font-size: .56rem; }
}

@media (prefers-reduced-transparency: reduce) {
  .public-bottom-nav { background: var(--surface-1); backdrop-filter: none; }
}
</style>
