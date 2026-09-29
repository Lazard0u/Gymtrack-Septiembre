import { beforeEach, describe, expect, it, vi } from 'vitest'
import { createPinia, setActivePinia } from 'pinia'
import { api } from '../../services/api'
import { useGimnasiosPublicosStore } from '../gimnasiosPublicos'

vi.mock('../../services/api', () => ({
  api: { get: vi.fn() },
}))

describe('catálogo público compartido de gimnasios', () => {
  beforeEach(() => {
    setActivePinia(createPinia())
    vi.clearAllMocks()
  })

  it('carga una única colección y elimina identificadores duplicados', async () => {
    api.get.mockResolvedValue({
      ok: true,
      data: {
        gimnasios: [
          { id: 1, slug: 'centro', nombre: 'GymTrack Centro' },
          { id: 1, slug: 'centro-actualizado', nombre: 'GymTrack Centro actualizado' },
          { id: 2, slug: 'norte', nombre: 'Norte Fitness Club' },
        ],
      },
    })

    const store = useGimnasiosPublicosStore()
    await store.load()

    expect(api.get).toHaveBeenCalledWith('/public/gimnasios')
    expect(store.items).toHaveLength(2)
    expect(store.items[0].slug).toBe('centro-actualizado')
    expect(store.status).toBe('ready')
  })

  it('refresca el catálogo y publica el gimnasio que debe centrarse en el mapa', async () => {
    const gym = { id: 8, slug: 'nuevo-gimnasio', nombre: 'Nuevo gimnasio' }
    api.get.mockResolvedValue({ ok: true, data: { gimnasios: [gym] } })

    const store = useGimnasiosPublicosStore()
    await store.refreshAndFocus(gym)

    expect(store.items).toEqual([gym])
    expect(store.focus).toMatchObject({ id: 8, slug: 'nuevo-gimnasio', version: 1 })
  })

  it('conserva los últimos datos y expone un error si la red falla', async () => {
    api.get.mockRejectedValue(new Error('sin conexión'))

    const store = useGimnasiosPublicosStore()
    store.items = [{ id: 3, slug: 'existente' }]
    await store.load(true)

    expect(store.items).toEqual([{ id: 3, slug: 'existente' }])
    expect(store.status).toBe('error')
    expect(store.error).toBe('No se pudo cargar el catálogo de gimnasios.')
  })

  it('retira de inmediato un gimnasio desactivado o archivado', () => {
    const store = useGimnasiosPublicosStore()
    store.items = [{ id: 3 }, { id: 4 }]

    store.remove(3)

    expect(store.items).toEqual([{ id: 4 }])
  })
})
