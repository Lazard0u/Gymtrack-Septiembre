<!--
  Componente de sistema DemoDataNotice. Comunica contexto global sin interferir con las tareas principales.
  En <script> se declaran imports, estado y funciones; <template> describe la interfaz y <style> limita su presentación.
-->
<script setup>
import { computed } from 'vue'
import { IconDatabase } from '@tabler/icons-vue'
import { useAuthStore } from '../../stores/auth'
import { useSystemStore } from '../../stores/system'

const auth = useAuthStore()
const system = useSystemStore()
const visible = computed(() => system.demoDataActive || Boolean(auth.user?.is_demo))
</script>

<template>
  <div v-if="visible" class="demo-notice" role="status" title="La aplicación está utilizando registros identificados y separables de demostración.">
    <IconDatabase :size="15" aria-hidden="true" />
    <span>Datos de demostración</span>
  </div>
</template>

<style scoped>
.demo-notice { display: flex; width: max-content; min-height: 2rem; align-items: center; gap: var(--space-2); margin: var(--space-4) auto; border: 1px solid var(--border-strong); border-radius: var(--radius-pill); padding: 0 var(--space-3); background: var(--surface-glass); color: var(--text-secondary); font-size: .7rem; font-weight: 700; }
.demo-notice svg { color: var(--info); }
</style>
