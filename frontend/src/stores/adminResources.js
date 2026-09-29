import { defineStore } from 'pinia'
import { api } from '../services/api'

// guardamos un solo resetter por store en vez de sumar uno cada vez que
// se carga, así esto no crece para siempre
const resetters = new Map()

function createResourceStore(id, endpoint) {
  return defineStore(id, {
    state: () => ({ items: [], pagination: { page: 1, per_page: 20, total: 0, total_pages: 1 }, status: 'idle', error: '', requestId: '', controller: null }),
    actions: {
      async load(query = {}) {
        this.cancel()
        this.status = 'loading'
        this.error = ''
        this.controller = new AbortController()
        resetters.set(id, () => this.reset())
        const response = await api.get(endpoint, { params: query, signal: this.controller.signal })
        if (response.cancelled) return
        this.controller = null
        if (!response.ok || response.data?.error) {
          this.status = 'error'
          this.error = response.data?.mensaje || 'No se pudo cargar esta información.'
          this.requestId = response.data?.request_id || ''
          return
        }
        this.items = response.data?.data?.items || []
        this.pagination = response.data?.meta?.pagination || this.pagination
        this.requestId = response.data?.request_id || ''
        this.status = this.items.length ? 'ready' : 'empty'
      },
      cancel() {
        this.controller?.abort()
        this.controller = null
      },
      reset() {
        this.cancel()
        this.items = []
        this.pagination = { page: 1, per_page: 20, total: 0, total_pages: 1 }
        this.status = 'idle'
        this.error = ''
        this.requestId = ''
      },
    },
  })
}

export const useAdminMembersStore = createResourceStore('admin-members', '/admin/members')
export const useAdminStaffStore = createResourceStore('admin-staff', '/admin/staff')
export const useAdminReservationsStore = createResourceStore('admin-reservations', '/admin/reservations')
export const useAdminMembershipsStore = createResourceStore('admin-memberships', '/admin/memberships')
export const useAdminActivityStore = createResourceStore('admin-activity', '/admin/activity')
export const useAdminExportsStore = createResourceStore('admin-exports', '/admin/exports')

export function resetAdminDomainStores() {
  resetters.forEach((reset) => reset())
}

