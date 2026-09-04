/**
 * Prueba automatizada de AppButton.spec. Prepara el escenario, ejecuta acciones públicas y verifica resultados sin alterar la lógica de producción.
 * Los imports declaran dependencias; funciones y estados documentan el recorrido de los datos y sus fallos esperables.
 */
import { mount } from '@vue/test-utils'
import axe from 'axe-core'
import AppButton from '../AppButton.vue'

describe('AppButton', () => {
  it('bloquea la acción y comunica la carga', async () => {
    const wrapper = mount(AppButton, { props: { loading: true }, slots: { default: 'Guardar' } })
    expect(wrapper.get('button').attributes('disabled')).toBeDefined()
    expect(wrapper.get('button').attributes('aria-busy')).toBe('true')
    await wrapper.get('button').trigger('click')
    expect(wrapper.emitted('click')).toBeUndefined()
  })

  it('no presenta violaciones automáticas de accesibilidad', async () => {
    const wrapper = mount(AppButton, { slots: { default: 'Continuar' }, attachTo: document.body })
    expect((await axe.run(wrapper.element)).violations).toEqual([])
  })
})
