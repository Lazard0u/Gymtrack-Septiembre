<!--
  Componente administrativo AdminFilterBar. Presenta controles operativos y delega persistencia a stores o a la vista contenedora.
  En <script> se declaran imports, estado y funciones; <template> describe la interfaz y <style> limita su presentación.
-->
<script setup>
import { reactive, watch } from 'vue'
import { IconSearch, IconX } from '@tabler/icons-vue'
import AppButton from '../ui/AppButton.vue'
import AppInput from '../ui/AppInput.vue'
import AppSelect from '../ui/AppSelect.vue'

const props = defineProps({
  modelValue: { type: Object, required: true },
  filters: { type: Array, default: () => [] },
  loading: Boolean,
  searchPlaceholder: { type: String, default: 'Nombre, correo o referencia' },
})
const emit = defineEmits(['apply', 'clear'])
const form = reactive({ ...props.modelValue })
watch(() => props.modelValue, (value) => Object.assign(form, value), { deep: true })

function submit() { emit('apply', { ...form, page: 1 }) }
function clear() {
  Object.keys(form).forEach((key) => { form[key] = key === 'per_page' ? '20' : '' })
  emit('clear')
}
</script>

<template>
  <form class="filter-bar" role="search" @submit.prevent="submit">
    <AppInput v-model="form.q" label="Buscar" name="admin-search" :placeholder="searchPlaceholder" maxlength="100" />
    <AppSelect v-for="filter in filters" :key="filter.key" v-model="form[filter.key]" :label="filter.label" :name="`filter-${filter.key}`" :options="filter.options" />
    <div class="filter-bar__actions"><AppButton type="submit" size="sm" :loading="loading"><template #icon><IconSearch :size="16" /></template>Aplicar</AppButton><AppButton type="button" variant="ghost" size="sm" @click="clear"><template #icon><IconX :size="16" /></template>Limpiar</AppButton></div>
  </form>
</template>

<style scoped>
.filter-bar { display: grid; grid-template-columns: minmax(14rem, 1.5fr) repeat(3, minmax(9rem, 1fr)) auto; align-items: end; gap: var(--space-3); border-bottom: 1px solid var(--border-subtle); padding: var(--space-4); background: var(--surface-1); }.filter-bar__actions { display: flex; gap: var(--space-2); padding-bottom: .02rem; }
@media (max-width: 79rem) { .filter-bar { grid-template-columns: repeat(2, minmax(0, 1fr)); }.filter-bar__actions { grid-column: 1 / -1; } }
@media (max-width: 47.99rem) { .filter-bar { grid-template-columns: 1fr; }.filter-bar__actions { grid-column: auto; }.filter-bar__actions > * { flex: 1; } }
</style>
