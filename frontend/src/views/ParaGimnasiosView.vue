<script setup>
import { IconChartLine, IconLock, IconSettings, IconUsersGroup } from '@tabler/icons-vue'
import PublicHeader from '../components/public/PublicHeader.vue'
import PublicFooter from '../components/public/PublicFooter.vue'
import AppLinkButton from '../components/ui/AppLinkButton.vue'
import AppPageHeader from '../components/ui/AppPageHeader.vue'
import ownerImage from '../assets/images/gymtrack-owner-studio.webp'

const pillars = [
  { icon: IconSettings, title: 'Operación centralizada', text: 'Socios, equipo, clases, reservas, membresías y pagos dentro de una navegación administrativa visible.' },
  { icon: IconChartLine, title: 'Decisiones con datos', text: 'Finanzas y reportes preparados para consumir movimientos reales, aplicar filtros y exportar.' },
  { icon: IconLock, title: 'Control por gimnasio', text: 'Autorización por rol y separación de datos como requisitos del backend, no como decoración de la interfaz.' },
]
</script>

<template>
  <div class="public-page"><PublicHeader /><main><section class="container owner-hero"><AppPageHeader eyebrow="Para gimnasios" title="La gestión diaria, donde la esperás." description="GymTrack 1.0 mantiene administración, finanzas y reportes visibles y conectados a la operación real." /><div class="owner-hero__image"><img :src="ownerImage" alt="Responsable de un gimnasio planificando la operación" width="1122" height="1402" /></div></section><section class="pillars"><div class="container pillars__grid"><article v-for="pillar in pillars" :key="pillar.title"><component :is="pillar.icon" :size="24" /><h2>{{ pillar.title }}</h2><p>{{ pillar.text }}</p></article></div></section><section class="container owner-cta"><div><IconUsersGroup :size="28" /><h2>Solicitá el alta de tu gimnasio.</h2><p>La solicitud se guarda y queda pendiente de revisión. Después, el equipo se incorpora mediante invitaciones con vencimiento y permisos por gimnasio.</p></div><div class="owner-cta__actions"><AppLinkButton :to="{ name: 'owner-register' }">Solicitar cuenta de dueño</AppLinkButton><AppLinkButton :to="{ name: 'login' }" variant="secondary">Ingresar</AppLinkButton></div></section></main><PublicFooter /></div>
</template>

<style scoped>
.owner-hero { display: grid; grid-template-columns: minmax(0, .95fr) minmax(24rem, 1.05fr); align-items: center; gap: var(--space-12); padding-bottom: var(--space-20); }.owner-hero :deep(.page-header) { align-items: flex-start; flex-direction: column; }.owner-hero__image { height: min(44rem, 70vh); overflow: hidden; border-radius: var(--radius-dialog); }.owner-hero__image img { width: 100%; height: 100%; object-fit: cover; }
.pillars { border-block: 1px solid var(--border-subtle); padding-block: var(--space-16); background: var(--bg-subtle); }.pillars__grid { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: var(--space-8); }.pillars article { padding-right: var(--space-6); }.pillars svg { margin-bottom: var(--space-8); color: var(--info); }.pillars h2 { margin-bottom: var(--space-3); font-size: 1.25rem; }.pillars p { margin: 0; color: var(--text-secondary); font-size: .9rem; }
.owner-cta { display: flex; align-items: flex-end; justify-content: space-between; gap: var(--space-12); padding-block: var(--space-20); }.owner-cta > div:first-child { max-width: 43rem; }.owner-cta svg { margin-bottom: var(--space-5); color: var(--info); }.owner-cta h2 { margin-bottom: var(--space-4); }.owner-cta p { margin: 0; color: var(--text-secondary); }.owner-cta__actions { display: flex; flex: 0 0 auto; gap: var(--space-3); }
@media (max-width: 63.99rem) { .owner-hero { grid-template-columns: 1fr; }.owner-hero__image { height: 34rem; }.pillars__grid { grid-template-columns: 1fr; }.pillars article { max-width: 38rem; padding: 0; }.owner-cta { align-items: flex-start; flex-direction: column; } }
@media (max-width: 47.99rem) { .owner-hero { gap: var(--space-4); }.owner-hero__image { height: 29rem; }.owner-cta__actions { width: 100%; flex-direction: column; } }
</style>
