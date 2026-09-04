<!--
  Componente raíz App. Decide la estructura global que rodea a todas las rutas de la aplicación.
  En <script> se declaran imports, estado y funciones; <template> describe la interfaz y <style> limita su presentación.
-->
<script setup>
import { onMounted } from 'vue'
import DemoDataNotice from './components/system/DemoDataNotice.vue'
import { useAuthStore } from './stores/auth'
import { useSystemStore } from './stores/system'

const system = useSystemStore()
const auth = useAuthStore()
onMounted(() => Promise.all([system.load(), auth.probe()]))
</script>

<template>
  <!--
    THESIS: Calma operativa con energía física; oscuro mate, directo y confiable.
    OWN-WORLD: Manrope, carbón frío, azul reservado para acción y foco, fotografía deportiva real.
    STORY: Orientación → exploración → valor para socios → valor para dueños → decisión.
    FIRST VIEWPORT: Marca, propuesta concreta y dos caminos inequívocos sin falsas métricas.
    FORM: Composición editorial asimétrica, superficies mate y ritmo 8px; seed: brief-aprobado.
    FINISH: unreviewed and undocumented is unfinished; this build ends with the finish review, the verdict, and DESIGN.md
  -->
  <router-view />
  <DemoDataNotice />
</template>
