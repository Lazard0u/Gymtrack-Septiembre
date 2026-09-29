<script setup>
import { computed, useId } from 'vue'
const props = defineProps({ modelValue: { type: String, default: '' }, label: { type: String, required: true }, name: { type: String, default: '' }, rows: { type: Number, default: 4 }, hint: { type: String, default: '' }, error: { type: String, default: '' }, maxlength: { type: [Number, String], default: undefined }, required: Boolean, disabled: Boolean })
const emit = defineEmits(['update:modelValue'])
const uid = useId()
const inputId = computed(() => props.name || `textarea-${uid}`)
</script>

<template>
  <label class="field" :for="inputId">
    <span>{{ label }}</span>
    <textarea :id="inputId" class="field__control" :name="name" :rows="rows" :value="modelValue" :maxlength="maxlength" :required="required" :disabled="disabled" :aria-invalid="error ? 'true' : undefined" :aria-describedby="hint || error ? `${inputId}-help` : undefined" @input="emit('update:modelValue', $event.target.value)" />
    <span v-if="error || hint" :id="`${inputId}-help`" :class="['field__help', { 'field__help--error': error }]">{{ error || hint }}</span>
  </label>
</template>

<style scoped>
.field { display: grid; gap: var(--space-2); color: var(--text-primary); font-size: .875rem; font-weight: 660; }
.field__control { width: 100%; resize: vertical; border: 1px solid var(--border-strong); border-radius: var(--radius-control); padding: var(--space-3); background: var(--bg-subtle); color: var(--text-primary); }
.field__help { color: var(--text-tertiary); font-size: .75rem; font-weight: 500; }
.field__help--error { color: var(--status-danger-strong); }
</style>
