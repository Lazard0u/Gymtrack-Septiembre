/**
 * Prueba automatizada de AppInput.spec. Prepara el escenario, ejecuta acciones públicas y verifica resultados sin alterar la lógica de producción.
 * Los imports declaran dependencias; funciones y estados documentan el recorrido de los datos y sus fallos esperables.
 */
import { mount } from '@vue/test-utils'
import AppInput from '../AppInput.vue'

describe('AppInput', () => {
  it('asocia etiqueta, ayuda, error y actualización de valor', async () => {
    const wrapper = mount(AppInput, { props: { modelValue: '', label: 'Correo', name: 'email', error: 'Ingresá un correo válido' } })
    const input = wrapper.get('input')
    expect(wrapper.get('label').attributes('for')).toBe('email')
    expect(input.attributes('aria-invalid')).toBe('true')
    expect(input.attributes('aria-describedby')).toBe('email-help')
    await input.setValue('persona@example.com')
    expect(wrapper.emitted('update:modelValue')[0]).toEqual(['persona@example.com'])
  })
})
