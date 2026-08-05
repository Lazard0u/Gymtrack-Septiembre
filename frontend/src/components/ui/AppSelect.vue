<script setup>
import { computed, useId } from 'vue'
const props = defineProps({ modelValue: { type: [String, Number], default: '' }, label: { type: String, required: true }, options: { type: Array, default: () => [] }, name: { type: String, default: '' }, hint: { type: String, default: '' }, error: { type: String, default: '' }, disabled: Boolean })
const emit = defineEmits(['update:modelValue'])
const uid = useId()
const inputId = computed(() => props.name || `select-${uid}`)
</script>

<template>
  <label class="field" :for="inputId">
    <span>{{ label }}</span>
    <select :id="inputId" class="field__control" :name="name" :value="modelValue" :disabled="disabled" :aria-invalid="error ? 'true' : undefined" :aria-describedby="hint || error ? `${inputId}-help` : undefined" @change="emit('update:modelValue', $event.target.value)">
      <option v-for="option in options" :key="option.value" :value="option.value">{{ option.label }}</option>
    </select>
    <span v-if="error || hint" :id="`${inputId}-help`" :class="['field__help', { 'field__help--error': error }]">{{ error || hint }}</span>
  </label>
</template>

<style scoped>
.field { display: grid; gap: var(--space-2); color: var(--text-primary); font-size: .875rem; font-weight: 660; }
.field__control { width: 100%; min-height: 2.75rem; border: 1px solid var(--border-strong); border-radius: var(--radius-control); padding: 0 var(--space-3); background: var(--bg-subtle); color: var(--text-primary); }
.field__help { color: var(--text-tertiary); font-size: .75rem; font-weight: 500; }
.field__help--error { color: var(--status-danger-strong); }
</style>
