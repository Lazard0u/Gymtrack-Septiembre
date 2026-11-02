<script setup>
import { IconArrowRight } from '@tabler/icons-vue'
import PublicHeader from '../components/public/PublicHeader.vue'
import PublicFooter from '../components/public/PublicFooter.vue'
import ExploradorGimnasios from '../components/public/ExploradorGimnasios.vue'
</script>

<template>
  <div class="public-page">
    <PublicHeader />
    <main class="container">
      <header class="slim">
        <div>
          <h1>Gimnasios</h1>
          <p>Elegí en el mapa o en la lista. Los datos de esta demo son ficticios y están marcados como tal.</p>
        </div>
        <RouterLink class="slim__link" :to="{ name: 'plans' }">Comparar planes<IconArrowRight :size="18" aria-hidden="true" /></RouterLink>
      </header>
      <ExploradorGimnasios compact-heading />
    </main>
    <PublicFooter />
  </div>
</template>

<style scoped>
.public-page main { padding-bottom: clamp(var(--space-12), 6vw, var(--space-16)); }
.slim { display: flex; align-items: flex-end; justify-content: space-between; gap: var(--space-6); padding-block: clamp(var(--space-8), 4vw, var(--space-12)) var(--space-6); }
.slim h1 { margin: 0 0 var(--space-2); font-size: clamp(2rem, 4vw, 2.9rem); line-height: 1.05; }
.slim p { max-width: 46ch; margin: 0; color: var(--text-secondary); line-height: 1.6; text-wrap: pretty; }
.slim__link { display: inline-flex; flex: 0 0 auto; align-items: center; gap: var(--space-2); min-height: 2.75rem; color: var(--status-info-strong); font-size: .9rem; font-weight: 700; text-decoration: none; }
.slim__link svg { transition: transform var(--duration-fast) var(--ease-out); }
.slim__link:hover svg { transform: translateX(3px); }
@media (max-width: 47.99rem) { .slim { align-items: flex-start; flex-direction: column; gap: var(--space-3); } }
@media (prefers-reduced-motion: reduce) { .slim__link svg { transition: none; } }
</style>
