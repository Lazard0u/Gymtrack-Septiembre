import { defineStore } from 'pinia'
import { api } from '../services/api'

export const useGimnasiosPublicosStore = defineStore('public-gyms', {
  state: () => ({
    items: [],
    status: 'idle',
    error: '',
    loadedAt: 0,
    focus: { id: null, slug: '', version: 0 },
    requestVersion: 0,
  }),
  actions: {
    async load(force = false) {
      if (!force && this.status === 'ready' && Date.now() - this.loadedAt < 30_000) return this.items
      const version = ++this.requestVersion
      this.status = 'loading'
      this.error = ''
      let response
      try {
        response = await api.get('/public/gimnasios')
      } catch {
        if (version === this.requestVersion) {
          this.status = 'error'
          this.error = 'No se pudo cargar el catálogo de gimnasios.'
        }
        return this.items
      }
      if (version !== this.requestVersion) return this.items
      if (!response.ok || response.data?.error) {
        this.status = 'error'
        this.error = response.data?.mensaje || 'No se pudo cargar el catálogo de gimnasios.'
        return this.items
      }
      const unique = new Map()
      for (const gym of response.data?.gimnasios || []) unique.set(Number(gym.id), gym)
      this.items = [...unique.values()]
      this.loadedAt = Date.now()
      this.status = 'ready'
      return this.items
    },
    async refreshAndFocus(gym = null) {
      await this.load(true)
      if (gym) this.requestFocus(gym)
      return this.items
    },
    requestFocus(gym) {
      this.focus = {
        id: Number(gym?.id) || null,
        slug: String(gym?.slug || ''),
        version: this.focus.version + 1,
      }
    },
    remove(id) {
      this.items = this.items.filter((gym) => Number(gym.id) !== Number(id))
      this.loadedAt = Date.now()
    },
  },
})
