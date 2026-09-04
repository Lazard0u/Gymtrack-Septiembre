<!--
  Vista de ruta GymsView. Coordina componentes, estado reactivo y llamadas a la API para completar este flujo de usuario.
  En <script> se declaran imports, estado y funciones; <template> describe la interfaz y <style> limita su presentación.
-->
<script setup>
import PublicHeader from '../components/public/PublicHeader.vue'
import PublicFooter from '../components/public/PublicFooter.vue'
import GymExplorer from '../components/public/GymExplorer.vue'
import AppPageHeader from '../components/ui/AppPageHeader.vue'
</script>

<template>
  <div class="public-page"><PublicHeader /><main class="container"><AppPageHeader eyebrow="Gimnasios" title="Explorá el mapa. Elegí con contexto." description="Filtrá por ciudad o categoría y sincronizá cada sede publicada con su marcador. El modo presentación utiliza registros demo identificados en MySQL." /><GymExplorer compact-heading /></main><PublicFooter /></div>
</template>

<style scoped>
.public-page main { padding-bottom: clamp(var(--space-16), 8vw, var(--space-20)); }
</style>
