/**
 * Prueba automatizada de AppCheckbox.spec. Prepara el escenario, ejecuta acciones públicas y verifica resultados sin alterar la lógica de producción.
 * Los imports declaran dependencias; funciones y estados documentan el recorrido de los datos y sus fallos esperables.
 */
import { mount } from '@vue/test-utils'
import AppCheckbox from '../AppCheckbox.vue'

describe('AppCheckbox', () => {
  it('emite el cambio y relaciona el error con el control', async () => {
    const wrapper = mount(AppCheckbox, { props: { modelValue: false, label: 'Acepto', name: 'consent', error: 'Es obligatorio.' } })
    const input = wrapper.get('input')
    expect(input.attributes('aria-invalid')).toBe('true')
    expect(input.attributes('aria-describedby')).toBe('consent-error')
    await input.setValue(true)
    expect(wrapper.emitted('update:modelValue')?.[0]).toEqual([true])
    expect(wrapper.get('#consent-error').text()).toBe('Es obligatorio.')
  })
})
