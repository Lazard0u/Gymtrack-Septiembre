import { mount } from '@vue/test-utils'
import { createPinia, setActivePinia } from 'pinia'
import DemoDataNotice from '../DemoDataNotice.vue'
import { useAuthStore } from '../../../stores/auth'
import { useSystemStore } from '../../../stores/system'

describe('DemoDataNotice', () => {
  beforeEach(() => {
    localStorage.clear()
    setActivePinia(createPinia())
  })

  it('permanece oculto cuando no hay dataset ni cuenta demo', () => {
    const wrapper = mount(DemoDataNotice)
    expect(wrapper.text()).not.toContain('Datos de demostración')
  })

  it('identifica un dataset demo activo', async () => {
    const system = useSystemStore()
    const wrapper = mount(DemoDataNotice)
    system.demoDataActive = true
    await wrapper.vm.$nextTick()
    expect(wrapper.get('[role="status"]').text()).toContain('Datos de demostración')
  })

  it('identifica también una cuenta demo aunque el dataset global no esté activo', async () => {
    const auth = useAuthStore()
    const wrapper = mount(DemoDataNotice)
    auth.user = { is_demo: true }
    await wrapper.vm.$nextTick()
    expect(wrapper.get('[role="status"]').text()).toContain('Datos de demostración')
  })
})
