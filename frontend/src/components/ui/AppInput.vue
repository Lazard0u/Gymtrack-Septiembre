<script setup>
import { computed, useId } from 'vue'

const props = defineProps({ modelValue: { type: [String, Number], default: '' }, label: { type: String, required: true }, type: { type: String, default: 'text' }, placeholder: { type: String, default: '' }, hint: { type: String, default: '' }, error: { type: String, default: '' }, name: { type: String, default: '' }, autocomplete: { type: String, default: '' }, required: Boolean, disabled: Boolean })
const emit = defineEmits(['update:modelValue'])
const uid = useId()
const inputId = computed(() => props.name || `input-${uid}`)
</script>

<template>
  <label class="field" :for="inputId">
    <span class="field__label">{{ label }}<span v-if="required" aria-hidden="true"> *</span></span>
    <input :id="inputId" class="field__control" :class="{ 'field__control--error': error }" :name="name" :type="type" :value="modelValue" :placeholder="placeholder" :autocomplete="autocomplete" :required="required" :disabled="disabled" :aria-invalid="error ? 'true' : undefined" :aria-describedby="hint || error ? `${inputId}-help` : undefined" @input="emit('update:modelValue', $event.target.value)" />
    <span v-if="error || hint" :id="`${inputId}-help`" :class="['field__help', { 'field__help--error': error }]">{{ error || hint }}</span>
  </label>
</template>

<style scoped>
.field { display: grid; gap: var(--space-2); color: var(--text-primary); font-size: .875rem; font-weight: 660; }
.field__control { width: 100%; min-height: 2.75rem; border: 1px solid var(--border-strong); border-radius: var(--radius-control); padding: 0 var(--space-3); background: var(--bg-subtle); color: var(--text-primary); transition: border-color var(--duration-fast), background-color var(--duration-fast); }
.field__control:hover:not(:disabled) { border-color: var(--text-tertiary); }
.field__control--error { border-color: var(--danger); }
.field__help { color: var(--text-tertiary); font-size: .75rem; font-weight: 500; }
.field__help--error { color: var(--status-danger-strong); }
</style>
