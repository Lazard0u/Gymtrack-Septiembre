<script setup>
import AppSpinner from './AppSpinner.vue'

defineProps({
  variant: { type: String, default: 'primary' },
  size: { type: String, default: 'md' },
  type: { type: String, default: 'button' },
  loading: Boolean,
  disabled: Boolean,
  block: Boolean,
})
</script>

<template>
  <button
    :class="['app-button', `app-button--${variant}`, `app-button--${size}`, { 'app-button--block': block }]"
    :type="type"
    :disabled="disabled || loading"
    :aria-busy="loading || undefined"
  >
    <AppSpinner v-if="loading" size="sm" />
    <slot name="icon" />
    <span><slot /></span>
  </button>
</template>

<style scoped>
.app-button {
  display: inline-flex;
  min-height: 2.75rem;
  align-items: center;
  justify-content: center;
  gap: var(--space-2);
  border: 1px solid transparent;
  border-radius: var(--radius-control);
  padding-inline: var(--space-5);
  cursor: pointer;
  font-size: 0.9rem;
  font-weight: 720;
  line-height: 1;
  text-decoration: none;
  transition: background-color var(--duration-fast) var(--ease-standard), border-color var(--duration-fast) var(--ease-standard), color var(--duration-fast) var(--ease-standard), transform var(--duration-fast) var(--ease-out);
}

.app-button--primary { background: var(--accent); color: var(--text-on-accent); }
.app-button--primary:hover:not(:disabled) { background: var(--accent-hover); transform: translateY(-1px); }
.app-button--primary:active:not(:disabled) { background: var(--accent-pressed); transform: translateY(0); }
.app-button--secondary { background: var(--surface-2); border-color: var(--border-strong); color: var(--text-primary); }
.app-button--secondary:hover:not(:disabled) { background: var(--surface-raised); border-color: var(--text-tertiary); }
.app-button--ghost { background: transparent; color: var(--text-secondary); }
.app-button--ghost:hover:not(:disabled) { background: var(--surface-2); color: var(--text-primary); }
.app-button--danger { background: var(--danger); color: var(--text-inverse); }
.app-button--sm { min-height: 2.25rem; padding-inline: var(--space-3); font-size: 0.8rem; }
.app-button--lg { min-height: 3.25rem; padding-inline: var(--space-6); font-size: 0.95rem; }
.app-button--block { width: 100%; }
</style>
