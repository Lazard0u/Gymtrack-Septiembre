<script setup>
import PublicHeader from '../components/public/PublicHeader.vue'
import PublicFooter from '../components/public/PublicFooter.vue'
import GymExplorer from '../components/public/GymExplorer.vue'
import AppPageHeader from '../components/ui/AppPageHeader.vue'
</script>

<template>
  <div class="public-page"><PublicHeader /><main class="container"><AppPageHeader eyebrow="Gimnasios" title="Explorá el mapa. Elegí con contexto." description="La estructura de mapa y lista ya está preparada. Los resultados aparecerán cuando los gimnasios publicados se conecten a esta experiencia." /><GymExplorer compact-heading /></main><PublicFooter /></div>
</template>

<style scoped>
.public-page main { padding-bottom: clamp(var(--space-16), 8vw, var(--space-20)); }
</style>
