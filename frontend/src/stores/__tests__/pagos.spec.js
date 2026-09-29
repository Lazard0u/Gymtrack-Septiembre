import { beforeEach, describe, expect, it, vi } from 'vitest'
import { createPinia, setActivePinia } from 'pinia'
import { api } from '../../services/api'
import { usePagosStore } from '../pagos'

vi.mock('../../services/api', () => ({ api: { get: vi.fn(), post: vi.fn() } }))

describe('payments store', () => {
  beforeEach(() => { setActivePinia(createPinia()); vi.clearAllMocks() })

  it('conserva moneda y estados reales al cargar pagos', async () => {
    api.get.mockResolvedValue({ ok: true, data: { data: { items: [{ id: 1, estado: 'pendiente', monto: '1290.00', moneda: 'UYU' }] }, meta: { pagination: { page: 1, per_page: 20, total: 1, total_pages: 1 } } } })
    const store = usePagosStore()
    expect(await store.loadPayments()).toBe(true)
    expect(store.items[0]).toMatchObject({ estado: 'pendiente', moneda: 'UYU' })
    expect(store.paymentsStatus).toBe('ready')
  })

  it('no inventa checkout cuando el backend rechaza el proveedor', async () => {
    api.post.mockResolvedValue({ ok: false, status: 503, data: { mensaje: 'Mercado Pago todavía no está configurado.' } })
    const store = usePagosStore()
    await expect(store.checkout(4)).rejects.toThrow('Mercado Pago todavía no está configurado.')
    expect(store.working).toBe('')
  })

  it('expone el archivo devuelto por una exportación real', async () => {
    api.post.mockResolvedValue({ ok: true, data: { data: { id: 9, filename: 'gymtrack-payments.xlsx', download_url: '/api/admin/exports/9/download' } } })
    api.get.mockResolvedValue({ ok: true, data: { data: { items: [] }, meta: { pagination: { page: 1, per_page: 20, total: 0, total_pages: 1 } } } })
    const store = usePagosStore()
    const result = await store.generateReport('xlsx', 'payments')
    expect(result.download_url).toContain('/exports/9/download')
    expect(api.post).toHaveBeenCalledWith('/admin/exports', { type: 'xlsx', module: 'payments', filters: {} })
  })
})
