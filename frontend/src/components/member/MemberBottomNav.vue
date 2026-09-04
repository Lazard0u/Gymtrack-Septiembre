<!--
  Componente del área del socio MemberBottomNav. Resume datos del store y ofrece navegación o acciones según permisos.
  En <script> se declaran imports, estado y funciones; <template> describe la interfaz y <style> limita su presentación.
-->
<script setup>
import { IconCalendarEvent, IconHome, IconMap2, IconUser, IconWallet } from '@tabler/icons-vue'

const items = [
  { route: { name: 'dashboard' }, label: 'Inicio', icon: IconHome },
  { route: { name: 'gyms' }, label: 'Gimnasios', icon: IconMap2 },
  { route: { name: 'member-schedule' }, label: 'Actividad', icon: IconCalendarEvent },
  { route: { name: 'plans' }, label: 'Planes', icon: IconWallet },
  { route: { name: 'member-profile' }, label: 'Perfil', icon: IconUser },
]
</script>

<template>
  <div class="member-bottom-nav__spacer" aria-hidden="true" />
  <nav class="member-bottom-nav" aria-label="Navegación móvil del socio">
    <RouterLink v-for="item in items" :key="item.label" :to="item.route">
      <component :is="item.icon" :size="21" />
      <span>{{ item.label }}</span>
    </RouterLink>
  </nav>
</template>

<style scoped>
.member-bottom-nav,.member-bottom-nav__spacer { display: none; }
@media (max-width: 63.99rem) {
  .member-bottom-nav__spacer { display: block; height: calc(4.7rem + env(safe-area-inset-bottom)); }
  .member-bottom-nav { position: fixed; inset: auto 0 0; z-index: var(--z-header); display: grid; grid-template-columns: repeat(5,minmax(0,1fr)); border-top: 1px solid var(--border-strong); padding: var(--space-1) max(var(--space-1),env(safe-area-inset-right)) max(var(--space-1),env(safe-area-inset-bottom)) max(var(--space-1),env(safe-area-inset-left)); background: var(--surface-glass); backdrop-filter: blur(18px); }
  .member-bottom-nav a { display: grid; min-width: 0; min-height: 3.75rem; place-content: center; justify-items: center; gap: .18rem; border-radius: var(--radius-control); color: var(--text-tertiary); font-size: .62rem; font-weight: 680; line-height: 1; text-decoration: none; transition: background-color var(--duration-fast) var(--ease-standard),color var(--duration-fast) var(--ease-standard),transform var(--duration-fast) var(--ease-out); }
  .member-bottom-nav a.router-link-active { background: var(--accent-soft); color: var(--status-info-strong); }
  .member-bottom-nav a:active { transform: scale(.97); }
}
@media (max-width: 22.5rem) { .member-bottom-nav a { font-size: .58rem; } }
@media (prefers-reduced-transparency: reduce) { .member-bottom-nav { background: var(--surface-1); backdrop-filter: none; } }
</style>
