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
  { path: '/administracion/resumen', name: 'admin-summary', component: { template: '<div />' } },
]

describe('PublicHeader', () => {
  it('expone la navegación móvil principal y mantiene visible el registro', async () => {
    const router = createRouter({ history: createMemoryHistory(), routes })
    await router.push('/')
    await router.isReady()
    const wrapper = mount(PublicHeader, { attachTo: document.body, global: { plugins: [createPinia(), router] } })
    const mobileNav = wrapper.get('nav[aria-label="Navegación principal móvil"]')
    expect(mobileNav.findAll('a')).toHaveLength(5)
    expect(mobileNav.text()).toContain('Inicio')
    expect(mobileNav.text()).toContain('Gimnasios')
    expect(mobileNav.text()).toContain('Planes')
    expect(wrapper.text()).toContain('Crear cuenta')
    wrapper.unmount()
  })
})
