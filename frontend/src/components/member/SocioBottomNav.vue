<script setup>
import { computed } from 'vue'
import { useRoute } from 'vue-router'
import { IconCalendarEvent, IconHome, IconTrendingUp, IconUser, IconWallet } from '@tabler/icons-vue'

const items = [
  { route: { name: 'dashboard' }, label: 'Inicio', icon: IconHome },
  { route: { name: 'member-schedule' }, label: 'Agenda', icon: IconCalendarEvent },
  { route: { name: 'member-payments' }, label: 'Pagos', icon: IconWallet },
  { route: { name: 'member-activity' }, label: 'Progreso', icon: IconTrendingUp },
  { route: { name: 'member-profile' }, label: 'Perfil', icon: IconUser },
]

const route = useRoute()
const activeIndex = computed(() => items.findIndex((item) => item.route.name === route.name))
</script>

<template>
  <div class="member-bottom-nav__spacer" aria-hidden="true" />
  <nav class="member-bottom-nav" aria-label="Navegación móvil del socio">
    <span
      class="member-bottom-nav__indicator"
      aria-hidden="true"
      :style="{ transform: `translateX(${Math.max(activeIndex, 0) * 100}%)`, opacity: activeIndex === -1 ? 0 : 1 }"
    />
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
  .member-bottom-nav__indicator { position: absolute; top: var(--space-1); bottom: max(var(--space-1),env(safe-area-inset-bottom)); left: max(var(--space-1),env(safe-area-inset-left)); width: 20%; border-radius: var(--radius-control); background: var(--accent-soft); transition: transform var(--duration-normal) var(--ease-out), opacity var(--duration-fast) var(--ease-standard); pointer-events: none; }
  .member-bottom-nav a { position: relative; display: grid; min-width: 0; min-height: 3.75rem; place-content: center; justify-items: center; gap: .18rem; border-radius: var(--radius-control); color: var(--text-tertiary); font-size: .62rem; font-weight: 680; line-height: 1; text-decoration: none; transition: color var(--duration-fast) var(--ease-standard),transform var(--duration-fast) var(--ease-out); }
  .member-bottom-nav a.router-link-active { color: var(--status-info-strong); }
  .member-bottom-nav a:active { transform: scale(.97); }
}
@media (max-width: 22.5rem) { .member-bottom-nav a { font-size: .58rem; } }
@media (prefers-reduced-transparency: reduce) { .member-bottom-nav { background: var(--surface-1); backdrop-filter: none; } }
@media (prefers-reduced-motion: reduce) { .member-bottom-nav__indicator { transition: opacity var(--duration-fast) var(--ease-standard); } }
</style>
