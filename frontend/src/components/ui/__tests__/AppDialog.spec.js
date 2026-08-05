import { mount } from '@vue/test-utils'
import { nextTick } from 'vue'
import AppDialog from '../AppDialog.vue'

describe('AppDialog', () => {
  it('mueve el foco, bloquea el scroll y cierra con Escape', async () => {
    const wrapper = mount(AppDialog, { attachTo: document.body, props: { open: false, title: 'Confirmar acción' }, slots: { default: '<button id="confirmar">Confirmar</button>' } })
    await wrapper.setProps({ open: true })
    await nextTick()
    expect(document.body.style.overflow).toBe('hidden')
    expect(document.activeElement?.closest('[role="dialog"]')).not.toBeNull()
    document.dispatchEvent(new KeyboardEvent('keydown', { key: 'Escape', bubbles: true }))
    expect(wrapper.emitted('close')).toHaveLength(1)
    wrapper.unmount()
  })
})
