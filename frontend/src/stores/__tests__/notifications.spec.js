/**
 * Prueba automatizada de notifications.spec. Prepara el escenario, ejecuta acciones públicas y verifica resultados sin alterar la lógica de producción.
 * Los imports declaran dependencias; funciones y estados documentan el recorrido de los datos y sus fallos esperables.
 */
import { beforeEach, describe, expect, it, vi } from 'vitest'
import { createPinia, setActivePinia } from 'pinia'
import { api } from '../../services/api'
import { useNotificationsStore } from '../notifications'

vi.mock('../../services/api', () => ({
  api: { get: vi.fn(), patch: vi.fn(), post: vi.fn(), put: vi.fn() },
}))

describe('notifications store', () => {
  beforeEach(() => {
    setActivePinia(createPinia())
    vi.clearAllMocks()
  })

  it('usa el total global de no leídas y no sólo la página cargada', async () => {
    api.get.mockResolvedValue({
      ok: true,
      data: {
        data: { items: [{ id: 10, title: 'Reserva confirmada', read_at: null }], unread_count: 7 },
        meta: { pagination: { page: 1, per_page: 20, total: 27, total_pages: 2 } },
      },
    })

    const store = useNotificationsStore()
    expect(await store.load()).toBe(true)
    expect(store.items).toHaveLength(1)
    expect(store.unread).toBe(7)
  })

  it('no descuenta dos veces una notificación ya leída', async () => {
    api.get.mockResolvedValue({ ok: true, data: { data: { items: [{ id: 10, read_at: null }], unread_count: 1 } } })
    api.patch.mockResolvedValue({ ok: true, data: { data: { read_at: '2026-08-12 10:00:00' } } })

    const store = useNotificationsStore()
    await store.load()
    await store.markRead(10)
    await store.markRead(10)

    expect(store.unread).toBe(0)
    expect(api.patch).toHaveBeenCalledTimes(2)
  })

  it('mantiene el marketing desactivado por defecto', () => {
    const store = useNotificationsStore()
    expect(store.preferences).toMatchObject({
      internal_marketing: false,
      email_marketing: false,
      whatsapp_marketing: false,
    })
  })

  it('normaliza el contrato en español del backend para la interfaz', async () => {
    api.get.mockResolvedValue({
      ok: true,
      data: { data: { items: [{ id: 4, titulo: 'Pago confirmado', cuerpo: 'La membresía ya está activa.', leida: false, creado_en: '2026-08-18 10:00:00' }], unread_count: 1 } },
    })

    const store = useNotificationsStore()
    await store.load()

    expect(store.items[0]).toMatchObject({ title: 'Pago confirmado', body: 'La membresía ya está activa.', read_at: null })
  })
})
