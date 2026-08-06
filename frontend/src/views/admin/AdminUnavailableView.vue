<script setup>
import { IconLockCheck } from '@tabler/icons-vue'
import AdminPageHeading from '../../components/admin/AdminPageHeading.vue'
import AppAlert from '../../components/ui/AppAlert.vue'
import AppBadge from '../../components/ui/AppBadge.vue'
import AppCard from '../../components/ui/AppCard.vue'

defineProps({ title: { type: String, required: true }, description: { type: String, required: true }, works: { type: String, required: true }, pending: { type: String, required: true }, phase: { type: String, required: true }, beta: Boolean })
</script>

<template><section><AdminPageHeading :title="title" :description="description"><template #actions><AppBadge :tone="beta ? 'warning' : 'info'">{{ beta ? 'Beta' : phase }}</AppBadge></template></AdminPageHeading><AppCard class="scope-card"><IconLockCheck :size="28" /><div><h2>Alcance actual</h2><p>{{ works }}</p></div><AppAlert :tone="beta ? 'warning' : 'info'" :title="`Pendiente para ${phase}`"><p>{{ pending }}</p></AppAlert><p class="scope-card__rule">No se muestran métricas, confirmaciones ni acciones simuladas.</p></AppCard></section></template>

<style scoped>.scope-card { display: grid; max-width: 50rem; gap: var(--space-6); }.scope-card > svg { color: var(--info); }.scope-card h2 { margin-bottom: var(--space-2); font-size: 1.2rem; }.scope-card > div > p, .scope-card__rule { margin: 0; color: var(--text-secondary); }.scope-card__rule { font-size: .78rem; }</style>
