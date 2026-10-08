<script setup>
import { computed, ref, useId } from 'vue'
import { IconEye, IconEyeOff } from '@tabler/icons-vue'

const props = defineProps({ modelValue: { type: [String, Number], default: '' }, label: { type: String, required: true }, type: { type: String, default: 'text' }, placeholder: { type: String, default: '' }, hint: { type: String, default: '' }, error: { type: String, default: '' }, name: { type: String, default: '' }, autocomplete: { type: String, default: '' }, minlength: { type: [Number, String], default: undefined }, maxlength: { type: [Number, String], default: undefined }, min: { type: [Number, String], default: undefined }, max: { type: [Number, String], default: undefined }, step: { type: [Number, String], default: undefined }, inputmode: { type: String, default: undefined }, required: Boolean, disabled: Boolean })
const emit = defineEmits(['update:modelValue'])
const uid = useId()
const inputId = computed(() => props.name || `input-${uid}`)
const isPassword = computed(() => props.type === 'password')
const revealed = ref(false)
const effectiveType = computed(() => (isPassword.value && revealed.value ? 'text' : props.type))
</script>

<template>
  <label class="field" :for="inputId">
    <span class="field__label">{{ label }}<span v-if="required" aria-hidden="true"> *</span></span>
    <span class="field__wrap">
      <input :id="inputId" class="field__control" :class="{ 'field__control--error': error, 'field__control--toggle': isPassword }" :name="name" :type="effectiveType" :value="modelValue" :placeholder="placeholder" :autocomplete="autocomplete" :minlength="minlength" :maxlength="maxlength" :min="min" :max="max" :step="step" :inputmode="inputmode" :required="required" :disabled="disabled" :aria-invalid="error ? 'true' : undefined" :aria-describedby="hint || error ? `${inputId}-help` : undefined" @input="emit('update:modelValue', $event.target.value)" />
      <button v-if="isPassword" type="button" class="field__toggle" :aria-label="revealed ? 'Ocultar contraseña' : 'Mostrar contraseña'" :aria-pressed="revealed" :disabled="disabled" @click="revealed = !revealed">
        <IconEyeOff v-if="revealed" :size="19" aria-hidden="true" /><IconEye v-else :size="19" aria-hidden="true" />
      </button>
    </span>
    <span v-if="error || hint" :id="`${inputId}-help`" :class="['field__help', { 'field__help--error': error }]">{{ error || hint }}</span>
  </label>
</template>

<style scoped>
.field { display: grid; gap: var(--space-2); color: var(--text-primary); font-size: .875rem; font-weight: 660; }
.field__control { width: 100%; min-height: 2.75rem; border: 1px solid var(--border-strong); border-radius: var(--radius-control); padding: 0 var(--space-3); background: var(--bg-subtle); color: var(--text-primary); transition: border-color var(--duration-fast), background-color var(--duration-fast); }
.field__control:hover:not(:disabled) { border-color: var(--text-tertiary); }
.field__control--error { border-color: var(--danger); }
.field__wrap { position: relative; display: block; }
.field__control--toggle { padding-right: 2.9rem; }
.field__toggle { position: absolute; top: 50%; right: .25rem; display: grid; width: 2.25rem; height: 2.25rem; place-items: center; transform: translateY(-50%); border: 0; border-radius: var(--radius-control); background: transparent; color: var(--text-tertiary); cursor: pointer; transition: color var(--duration-fast), background-color var(--duration-fast); }
.field__toggle:hover:not(:disabled) { background: var(--surface-2); color: var(--text-primary); }
.field__toggle:focus-visible { outline: 2px solid var(--focus); outline-offset: 1px; }
.field__toggle:disabled { cursor: not-allowed; opacity: .5; }
.field__help { color: var(--text-tertiary); font-size: .75rem; font-weight: 500; }
.field__help--error { color: var(--status-danger-strong); }
</style>
