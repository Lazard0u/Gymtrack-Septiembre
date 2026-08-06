import { mount } from '@vue/test-utils'
import AdminDataTable from '../AdminDataTable.vue'

const pagination = { page: 1, per_page: 20, total: 1, total_pages: 1 }
const columns = [
  { key: 'nombre', label: 'Nombre', sortable: true },
  { key: 'estado', label: 'Estado', format: 'status' },
]

describe('AdminDataTable', () => {
  it('presenta datos reales y emite ordenamiento desde un botón accesible', async () => {
    const wrapper = mount(AdminDataTable, {
      props: { columns, items: [{ id: 1, nombre: 'Socio Demo', estado: 'activa' }], pagination, status: 'ready' },
    })
    expect(wrapper.text()).toContain('Socio Demo')
    expect(wrapper.text()).toContain('activa')
    await wrapper.get('th button').trigger('click')
    expect(wrapper.emitted('sort')?.[0]).toEqual(['nombre'])
  })

  it('muestra un estado vacío honesto sin fabricar filas', () => {
    const wrapper = mount(AdminDataTable, {
      props: { columns, items: [], pagination: { ...pagination, total: 0 }, status: 'empty', emptyTitle: 'Sin socios', emptyDescription: 'No existen asociaciones.' },
    })
    expect(wrapper.get('[role="status"]').text()).toContain('Sin socios')
    expect(wrapper.find('table').exists()).toBe(false)
  })
})
