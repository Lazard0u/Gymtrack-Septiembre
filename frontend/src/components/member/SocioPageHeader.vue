<script setup>
defineProps({
  eyebrow: { type: String, default: '' },
  title: { type: String, required: true },
  description: { type: String, default: '' },
})
</script>

<template>
  <header class="member-page-header">
    <div>
      <span v-if="eyebrow" class="eyebrow">{{ eyebrow }}</span>
      <h1>{{ title }}</h1>
      <p v-if="description">{{ description }}</p>
    </div>
    <div v-if="$slots.actions" class="member-page-header__actions"><slot name="actions" /></div>
  </header>
</template>

<style scoped>
.member-page-header { display: flex; align-items: flex-end; justify-content: space-between; gap: var(--space-6); padding-block: var(--space-6) var(--space-5); }
.member-page-header h1 { margin: 0 0 var(--space-1); font-size: clamp(1.4rem, 3vw, 1.85rem); letter-spacing: -0.02em; }
.member-page-header p { max-width: 42rem; margin: 0; color: var(--text-secondary); font-size: .85rem; }
.member-page-header__actions { display: flex; flex: 0 0 auto; flex-wrap: wrap; gap: var(--space-2); }
@media (max-width: 47.99rem) {
  .member-page-header { align-items: flex-start; flex-direction: column; gap: var(--space-3); padding-block: var(--space-4) var(--space-3); }
  .member-page-header__actions { width: 100%; }
  .member-page-header__actions > :deep(.app-button),
  .member-page-header__actions > :deep(.link-button) { width: 100%; }
}
</style>
