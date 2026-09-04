/**
 * Prueba automatizada de member.spec. Prepara el escenario, ejecuta acciones públicas y verifica resultados sin alterar la lógica de producción.
 * Los imports declaran dependencias; funciones y estados documentan el recorrido de los datos y sus fallos esperables.
 */
import { beforeEach, describe, expect, it, vi } from 'vitest'
import { createPinia, setActivePinia } from 'pinia'
import { api } from '../../services/api'
import { useMemberStore } from '../member'

vi.mock('../../services/api', () => ({
  api: {
    get: vi.fn(),
    post: vi.fn(),
    put: vi.fn(),
    patch: vi.fn(),
    delete: vi.fn(),
    upload: vi.fn(),
  },
}))

describe('member store', () => {
  beforeEach(() => {
    setActivePinia(createPinia())
    vi.clearAllMocks()
  })

  it('carga el resumen del socio sin perder próximos eventos ni estados financieros', async () => {
    const summary = {
      next_class: { id: 41, nombre: 'Funcional', inicio_en: '2026-08-19 18:00:00' },
      monthly_progress: { month: '2026-08', goal: 12, attended: 5, percentage: 42 },
      membership: { id: 9, estado: 'activa', plan: 'Mensual' },
      pending_payment: { id: 18, estado: 'pendiente', monto: '1290.00', moneda: 'UYU' },
      promotions: [{ id: 4, nombre: 'Invierno', estado: 'activa' }],
    }
    api.get.mockResolvedValue({ ok: true, data: { data: summary } })

    const store = useMemberStore()
    expect(await store.loadSummary()).toBe(true)

    expect(api.get).toHaveBeenCalledWith('/member/summary')
    expect(store.summary).toEqual(summary)
    expect(store.summaryStatus).toBe('ready')
  })

  it('lee y guarda objetivo, IMC orientativo y horarios preferidos en el servidor', async () => {
    api.get.mockResolvedValue({
      ok: true,
      data: { data: { monthly_attendance_goal: 10, show_orientation_bmi: false, preferred_schedules: ['morning'] } },
    })
    api.put.mockResolvedValue({
      ok: true,
      data: { data: { monthly_attendance_goal: 14, show_orientation_bmi: true, preferred_schedules: ['afternoon', 'evening'] } },
    })

    const store = useMemberStore()
    expect(await store.loadPreferences()).toBe(true)
    expect(store.preferences).toEqual({
      monthly_attendance_goal: 10,
      show_orientation_bmi: false,
      preferred_schedules: ['morning'],
    })

    const payload = {
      monthly_attendance_goal: 14,
      show_orientation_bmi: true,
      preferred_schedules: ['afternoon', 'evening'],
    }
    await store.savePreferences(payload)

    expect(api.put).toHaveBeenCalledWith('/member/preferences', payload)
    expect(store.preferences).toEqual(payload)
    expect(store.working).toBe('')
  })

  it('persiste altas y bajas de gimnasios y actividades favoritas y recarga MySQL', async () => {
    api.post.mockResolvedValue({ ok: true, data: { data: { type: 'gym', target_id: 7, favorite: true } } })
    api.delete.mockResolvedValue({ ok: true, data: { data: { type: 'activity', target_id: 3, favorite: false } } })
    api.get
      .mockResolvedValueOnce({
        ok: true,
        data: { data: { gyms: [{ id: 7, nombre: 'Centro' }], activities: [], available_activities: [] } },
      })
      .mockResolvedValueOnce({
        ok: true,
        data: { data: { gyms: [{ id: 7, nombre: 'Centro' }], activities: [], available_activities: [{ id: 3, favorite: false }] } },
      })

    const store = useMemberStore()
    await store.setFavorite('gym', '7', true)
    expect(api.post).toHaveBeenCalledWith('/member/favorites', { type: 'gym', target_id: 7 })
    expect(store.favorites.gyms).toEqual([{ id: 7, nombre: 'Centro' }])

    await store.setFavorite('activity', 3, false)
    expect(api.delete).toHaveBeenCalledWith('/member/favorites/activity/3')
    expect(api.get).toHaveBeenCalledTimes(2)
    expect(store.favorites.available_activities).toEqual([{ id: 3, favorite: false }])
    expect(store.favoritesStatus).toBe('ready')
  })

  it('envía una clave de idempotencia al crear una medición y refresca mediciones y resumen', async () => {
    const idempotencyKey = '9d8c5cc7-235d-41fb-9620-038fcd9222ee'
    const randomUuid = vi.spyOn(globalThis.crypto, 'randomUUID').mockReturnValue(idempotencyKey)
    const payload = { measured_at: '2026-08-18', height_cm: 172, weight_kg: 68.5, notes: '' }
    const created = { id: 22, ...payload, bmi: 23.15 }
    api.post.mockResolvedValue({ ok: true, data: { data: created } })
    api.get.mockImplementation((endpoint) => {
      if (endpoint === '/member/measurements') {
        return Promise.resolve({
          ok: true,
          data: {
            data: { items: [created], medical_notice: 'El IMC es orientativo.' },
            meta: { pagination: { page: 1, per_page: 20, total: 1, total_pages: 1 } },
          },
        })
      }
      return Promise.resolve({ ok: true, data: { data: { monthly_progress: { goal: 8, attended: 3 } } } })
    })

    const store = useMemberStore()
    expect(await store.createMeasurement(payload)).toEqual(created)

    expect(api.post).toHaveBeenCalledWith('/member/measurements', payload, {
      headers: { 'Idempotency-Key': idempotencyKey },
    })
    expect(api.get).toHaveBeenCalledWith('/member/measurements', { params: { page: 1, per_page: 20 } })
    expect(api.get).toHaveBeenCalledWith('/member/summary')
    expect(store.measurements).toEqual([created])
    expect(store.summary).toEqual({ monthly_progress: { goal: 8, attended: 3 } })
    expect(store.working).toBe('')
    randomUuid.mockRestore()
  })

  it('conserva token temporal, vencimiento y scope del carné devuelto por el backend', async () => {
    const card = {
      token: 'header.payload.signature',
      expires_at: '2026-08-18T19:02:00-03:00',
      member: { member_number: 'GT-0001-000017', gym: { id: 1, name: 'GymTrack Centro' } },
      membership: { id: 9, estado: 'activa', plan: 'Mensual' },
    }
    api.get.mockResolvedValue({ ok: true, data: { data: card } })

    const store = useMemberStore()
    expect(await store.loadCard()).toBe(true)

    expect(api.get).toHaveBeenCalledWith('/member/card')
    expect(store.card).toEqual(card)
    expect(store.cardStatus).toBe('ready')
  })
})
