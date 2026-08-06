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
.demo-notice { position: fixed; right: var(--space-3); bottom: var(--space-3); z-index: var(--z-toast); display: inline-flex; min-height: 2rem; align-items: center; gap: var(--space-2); border: 1px solid var(--border-strong); border-radius: var(--radius-pill); padding: 0 var(--space-3); background: var(--surface-glass); color: var(--text-secondary); box-shadow: var(--shadow-md); font-size: .7rem; font-weight: 700; backdrop-filter: blur(12px); }
.demo-notice svg { color: var(--info); }
@media (max-width: 47.99rem) {
  .demo-notice { position: static; width: max-content; margin: var(--space-4) auto; }
}
</style>
