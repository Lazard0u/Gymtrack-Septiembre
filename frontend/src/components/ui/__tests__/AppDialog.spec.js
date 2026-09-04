/**
 * Prueba automatizada de AppDialog.spec. Prepara el escenario, ejecuta acciones públicas y verifica resultados sin alterar la lógica de producción.
 * Los imports declaran dependencias; funciones y estados documentan el recorrido de los datos y sus fallos esperables.
 */
import { mount } from '@vue/test-utils'
import { nextTick } from 'vue'
import AppDialog from '../AppDialog.vue'
import AppDrawer from '../AppDrawer.vue'

const settleFocus = async () => {
  await nextTick()
  await nextTick()
}

describe('AppDialog', () => {
  it('coordina foco, Escape y scroll entre capas anidadas', async () => {
    document.body.style.overflow = 'clip'
    const trigger = document.createElement('button')
    trigger.id = 'trigger'
    document.body.append(trigger)
    trigger.focus()

    const drawer = mount(AppDrawer, {
      attachTo: document.body,
      props: { open: false, title: 'Editar promoción' },
      slots: { default: '<button id="drawer-action">Acción del drawer</button>' },
    })
    const dialog = mount(AppDialog, {
      attachTo: document.body,
      props: { open: false, title: 'Confirmar acción' },
      slots: { default: '<button id="dialog-action">Confirmar</button>' },
    })

    await drawer.setProps({ open: true })
    await settleFocus()
    const drawerAction = document.querySelector('#drawer-action')
    drawerAction.focus()

    await dialog.setProps({ open: true })
    await settleFocus()
    expect(document.body.style.overflow).toBe('hidden')
    expect(document.querySelector('.dialog__panel').getAttribute('aria-modal')).toBe('true')
    expect(document.querySelector('.drawer__panel').hasAttribute('inert')).toBe(true)

    // Even if focus is moved outside the top layer, Tab brings it back there.
    drawerAction.focus()
    document.dispatchEvent(new KeyboardEvent('keydown', { key: 'Tab', bubbles: true, cancelable: true }))
    expect(document.activeElement.closest('.dialog__panel')).not.toBeNull()

    document.dispatchEvent(new KeyboardEvent('keydown', { key: 'Escape', bubbles: true, cancelable: true }))
    expect(dialog.emitted('close')).toHaveLength(1)
    expect(drawer.emitted('close')).toBeUndefined()

    await dialog.setProps({ open: false })
    await settleFocus()
    expect(document.activeElement).toBe(drawerAction)
    expect(document.body.style.overflow).toBe('hidden')
    expect(document.querySelector('.drawer__panel').getAttribute('aria-modal')).toBe('true')
    expect(document.querySelector('.drawer__panel').hasAttribute('inert')).toBe(false)

    document.dispatchEvent(new KeyboardEvent('keydown', { key: 'Escape', bubbles: true, cancelable: true }))
    expect(drawer.emitted('close')).toHaveLength(1)
    await drawer.setProps({ open: false })
    await settleFocus()
    expect(document.activeElement).toBe(trigger)
    expect(document.body.style.overflow).toBe('clip')

    dialog.unmount()
    drawer.unmount()
    trigger.remove()
  })

  it('genera relaciones ARIA únicas y apila según el orden de apertura', async () => {
    const first = mount(AppDialog, {
      attachTo: document.body,
      props: { open: true, title: 'Primer diálogo', description: 'Primera descripción' },
    })
    const second = mount(AppDialog, {
      attachTo: document.body,
      props: { open: true, title: 'Segundo diálogo', description: 'Segunda descripción' },
    })
    await settleFocus()

    const panels = [...document.querySelectorAll('.dialog__panel')]
    const titleIds = panels.map((panel) => panel.getAttribute('aria-labelledby'))
    const descriptionIds = panels.map((panel) => panel.getAttribute('aria-describedby'))

    expect(new Set(titleIds).size).toBe(2)
    expect(new Set(descriptionIds).size).toBe(2)
    panels.forEach((panel, index) => {
      expect(document.getElementById(titleIds[index])?.textContent).toContain(index ? 'Segundo' : 'Primer')
      expect(document.getElementById(descriptionIds[index])).not.toBeNull()
    })
    expect(document.querySelectorAll('.dialog')[0].style.zIndex).toContain('+ 0')
    expect(document.querySelectorAll('.dialog')[1].style.zIndex).toContain('+ 1')

    second.unmount()
    first.unmount()
  })
})
