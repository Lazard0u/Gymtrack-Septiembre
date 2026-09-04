/**
 * Prueba automatizada de promotions.spec. Prepara el escenario, ejecuta acciones públicas y verifica resultados sin alterar la lógica de producción.
 * Los imports declaran dependencias; funciones y estados documentan el recorrido de los datos y sus fallos esperables.
 */
import { beforeEach, describe, expect, it, vi } from 'vitest'
import { createPinia, setActivePinia } from 'pinia'
import { api } from '../../services/api'
import { usePromotionsStore } from '../promotions'

vi.mock('../../services/api', () => ({
  api: { get: vi.fn(), post: vi.fn(), patch: vi.fn() },
}))

describe('promotions store', () => {
  beforeEach(() => {
    setActivePinia(createPinia())
    vi.clearAllMocks()
  })

  it('conserva paginación y estados provenientes de MySQL', async () => {
    api.get.mockResolvedValue({
      ok: true,
      data: {
        data: { items: [{ id: 3, nombre: 'CENTRO15DEMO', estado: 'activa', canales: ['internal'] }] },
        meta: { pagination: { page: 1, per_page: 20, total: 1, total_pages: 1 } },
      },
    })

    const store = usePromotionsStore()
    expect(await store.load({ status: 'activa' })).toBe(true)
    expect(store.items[0]).toMatchObject({ estado: 'activa', canales: ['internal'] })
    expect(store.pagination.total).toBe(1)
    expect(store.listStatus).toBe('ready')
  })

  it('permite sólo transiciones implementadas por el backend', async () => {
    api.post.mockResolvedValue({ ok: true, data: { data: { promotion: { id: 3, estado: 'programada' }, queued: 1 } } })
    const store = usePromotionsStore()

    await expect(store.transition(3, 'resume')).rejects.toThrow('Transición de promoción no permitida.')
    expect(api.post).not.toHaveBeenCalled()
    await store.transition(3, 'schedule')
    expect(api.post).toHaveBeenCalledWith('/admin/promotions/3/schedule', {})
  })

  it('no confirma una mutación rechazada por el servidor', async () => {
    api.patch.mockResolvedValue({ ok: false, status: 409, data: { mensaje: 'La versión cambió. Recargá la promoción.' } })
    const store = usePromotionsStore()

    await expect(store.save({ nombre: 'Oferta' }, 3)).rejects.toThrow('La versión cambió. Recargá la promoción.')
    expect(store.working).toBe(false)
  })
})
