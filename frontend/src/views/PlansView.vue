<!--
  Vista de ruta PlansView. Coordina componentes, estado reactivo y llamadas a la API para completar este flujo de usuario.
  En <script> se declaran imports, estado y funciones; <template> describe la interfaz y <style> limita su presentación.
-->
<script setup>
import { IconCreditCard } from '@tabler/icons-vue'
import PublicHeader from '../components/public/PublicHeader.vue'
import PublicFooter from '../components/public/PublicFooter.vue'
import AppCard from '../components/ui/AppCard.vue'
import AppEmptyState from '../components/ui/AppEmptyState.vue'
import AppLinkButton from '../components/ui/AppLinkButton.vue'
import AppPageHeader from '../components/ui/AppPageHeader.vue'
</script>

<template>
  <div class="public-page"><PublicHeader /><main class="container"><AppPageHeader eyebrow="Planes" title="Precios reales, cuando estén listos." description="Cada gimnasio podrá publicar sus membresías y condiciones desde administración. Hasta entonces, no mostramos opciones de ejemplo." /><AppCard class="state-card"><AppEmptyState title="Aún no hay planes publicados" description="Esta vista se alimentará desde el catálogo de membresías en MySQL."><template #icon><IconCreditCard :size="25" /></template><template #action><AppLinkButton :to="{ name: 'gyms' }" variant="secondary">Explorar gimnasios</AppLinkButton></template></AppEmptyState></AppCard></main><PublicFooter /></div>
</template>

<style scoped>
.public-page main { min-height: calc(100vh - var(--header-height)); padding-bottom: var(--space-20); }.state-card { margin-bottom: var(--space-20); }
</style>
