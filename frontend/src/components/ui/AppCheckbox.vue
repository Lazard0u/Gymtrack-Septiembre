<script setup>
import { useId } from 'vue'
const props = defineProps({ modelValue: Boolean, label: { type: String, required: true }, description: { type: String, default: '' }, name: { type: String, default: '' }, disabled: Boolean, required: Boolean, error: { type: String, default: '' } })
defineEmits(['update:modelValue'])
const id = props.name || `checkbox-${useId()}`
</script>

<template>
  <label :class="['checkbox', { 'checkbox--invalid': error }]" :for="id">
    <input :id="id" :name="name" type="checkbox" :checked="modelValue" :disabled="disabled" :required="required" :aria-invalid="error ? 'true' : undefined" :aria-describedby="error ? `${id}-error` : undefined" @change="$emit('update:modelValue', $event.target.checked)" />
    <span><strong>{{ label }}</strong><small v-if="description">{{ description }}</small><small v-if="error" :id="`${id}-error`" class="checkbox__error">{{ error }}</small></span>
  </label>
</template>

<style scoped>
.checkbox { display: grid; min-height: 2.75rem; grid-template-columns: 1.1rem 1fr; align-items: center; gap: var(--space-3); color: var(--text-primary); cursor: pointer; }.checkbox input { width: 1.1rem; height: 1.1rem; margin: 0; accent-color: var(--accent); }.checkbox span { display: grid; gap: var(--space-1); }.checkbox strong { font-size: .82rem; }.checkbox small { color: var(--text-tertiary); font-size: .72rem; font-weight: 500; line-height: 1.45; }.checkbox:has(input:disabled) { cursor: not-allowed; opacity: .58; }.checkbox--invalid input { outline: 2px solid var(--danger); outline-offset: 2px; }.checkbox .checkbox__error { color: var(--status-danger-text); }
</style>
