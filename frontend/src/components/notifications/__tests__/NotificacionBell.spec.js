import { beforeEach, describe, expect, it, vi } from 'vitest'
import { mount } from '@vue/test-utils'
import { createPinia, setActivePinia } from 'pinia'
import { api } from '../../../services/api'
import NotificacionBell from '../NotificacionBell.vue'

vi.mock('../../../services/api', () => ({
  api: { get: vi.fn(), patch: vi.fn(), post: vi.fn(), put: vi.fn() },
}))

// motion-v reads prefers-reduced-motion; force it so springs settle synchronously in jsdom.
window.matchMedia = window.matchMedia || (() => ({}))
vi.spyOn(window, 'matchMedia').mockImplementation((query) => ({
  matches: true,
  media: query,
  addEventListener: () => {},
  removeEventListener: () => {},
  addListener: () => {},
  removeListener: () => {},
  dispatchEvent: () => false,
}))

describe('NotificacionBell', () => {
  beforeEach(() => {
    setActivePinia(createPinia())
    vi.clearAllMocks()
  })

  it('muestra el total de no leídas y emite "open" al hacer click', async () => {
    api.get.mockResolvedValue({
      ok: true,
      data: { data: { items: [], unread_count: 7 }, meta: { pagination: { page: 1, per_page: 20, total: 0, total_pages: 1 } } },
    })

    const wrapper = mount(NotificacionBell)
    await vi.waitFor(() => expect(wrapper.get('[role="status"]').text()).toContain('7'))

    expect(wrapper.get('button').attributes('aria-label')).toBe('Ver notificaciones')

    await wrapper.get('button').trigger('click')
    expect(wrapper.emitted('open')).toHaveLength(1)

    wrapper.unmount()
  })

  it('no muestra el badge cuando no hay notificaciones sin leer', async () => {
    api.get.mockResolvedValue({
      ok: true,
      data: { data: { items: [], unread_count: 0 }, meta: { pagination: { page: 1, per_page: 20, total: 0, total_pages: 1 } } },
    })

    const wrapper = mount(NotificacionBell)
    await vi.waitFor(() => expect(api.get).toHaveBeenCalled())
    await wrapper.vm.$nextTick()

    expect(wrapper.find('.count-badge').exists()).toBe(false)
    wrapper.unmount()
  })
})
