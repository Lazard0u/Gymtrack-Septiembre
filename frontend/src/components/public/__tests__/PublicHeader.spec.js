import { mount } from '@vue/test-utils'
import { createPinia } from 'pinia'
import { createMemoryHistory, createRouter } from 'vue-router'
import PublicHeader from '../PublicHeader.vue'

const routes = [
  { path: '/', name: 'home', component: { template: '<div />' } },
  { path: '/gimnasios', name: 'gyms', component: { template: '<div />' } },
  { path: '/planes', name: 'plans', component: { template: '<div />' } },
  { path: '/para-gimnasios', name: 'for-gyms', component: { template: '<div />' } },
  { path: '/login', name: 'login', component: { template: '<div />' } },
  { path: '/registro', name: 'registro', component: { template: '<div />' } },
  { path: '/dashboard', name: 'dashboard', component: { template: '<div />' } },
]

describe('PublicHeader', () => {
  it('abre el menú móvil, enfoca su contenido y cierra con Escape', async () => {
    const router = createRouter({ history: createMemoryHistory(), routes })
    await router.push('/')
    await router.isReady()
    const wrapper = mount(PublicHeader, { attachTo: document.body, global: { plugins: [createPinia(), router] } })
    const trigger = wrapper.get('[aria-label="Abrir menú"]')
    trigger.element.focus()
    await trigger.trigger('click')
    expect(document.querySelector('[role="dialog"]')).not.toBeNull()
    expect(document.activeElement?.closest('[role="dialog"]')).not.toBeNull()
    document.dispatchEvent(new KeyboardEvent('keydown', { key: 'Escape', bubbles: true }))
    await wrapper.vm.$nextTick()
    await new Promise((resolve) => window.setTimeout(resolve, 250))
    expect(document.querySelector('[role="dialog"]')).toBeNull()
    expect(document.activeElement).toBe(trigger.element)
    wrapper.unmount()
  })
})
