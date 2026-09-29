import { defineStore } from 'pinia'
import { api } from '../services/api'

export const useAdminManagementStore = defineStore('admin-management', {
  state: () => ({
    gym: null,
    locations: [],
    invitations: [],
    plans: [],
    plansPagination: { page: 1, per_page: 20, total: 0, total_pages: 1 },
    trainers: [],
    trainersPagination: { page: 1, per_page: 20, total: 0, total_pages: 1 },
    status: 'idle',
    error: '',
    requestId: '',
  }),
  actions: {
    async loadGym(gymId) {
      this.status = 'loading'; this.error = ''
      const response = await api.get(`/admin/gyms/${gymId}`)
      if (!response.ok) return this.fail(response, 'No se pudo cargar el gimnasio.')
      this.gym = response.data.data; this.status = 'ready'; return this.gym
    },
    async saveGym(gymId, payload) {
      return this.mutate(() => api.patch(`/admin/gyms/${gymId}`, payload), (data) => { this.gym = data })
    },
    async createGym(payload) {
      return this.mutate(() => api.post('/admin/gyms', payload))
    },
    async archiveGym(gymId, motivo) {
      return this.mutate(() => api.delete(`/admin/gyms/${gymId}`, { motivo }))
    },
    async loadLocations(gymId) {
      const response = await api.get(`/admin/gyms/${gymId}/locations`)
      if (!response.ok) return this.fail(response, 'No se pudieron cargar las sedes.')
      this.locations = response.data.data?.items || []; return this.locations
    },
    async saveLocation(gymId, payload, locationId = null) {
      const call = locationId
        ? () => api.patch(`/admin/gyms/${gymId}/locations/${locationId}`, payload)
        : () => api.post(`/admin/gyms/${gymId}/locations`, payload)
      return this.mutate(call, () => this.loadLocations(gymId))
    },
    async loadInvitations() {
      const response = await api.get('/admin/invitations')
      if (!response.ok) return this.fail(response, 'No se pudieron cargar las invitaciones.')
      this.invitations = response.data.data?.items || []; return this.invitations
    },
    async invite(payload) {
      return this.mutate(() => api.post('/admin/invitations', payload), () => this.loadInvitations())
    },
    async revokeInvitation(id) {
      return this.mutate(() => api.delete(`/admin/invitations/${id}`), () => this.loadInvitations())
    },
    async member(id) { return this.read(`/admin/members/${id}`, 'No se pudo cargar la ficha del socio.') },
    async updateMember(id, payload) { return this.mutate(() => api.patch(`/admin/members/${id}`, payload)) },
    async employee(id) { return this.read(`/admin/employees/${id}`, 'No se pudo cargar la ficha del empleado.') },
    async updateEmployee(id, payload) { return this.mutate(() => api.patch(`/admin/employees/${id}`, payload)) },
    async loadTrainers(query = {}) {
      this.status = 'loading'; this.error = ''
      const response = await api.get('/admin/trainers', { params: query })
      if (!response.ok) return this.fail(response, 'No se pudieron cargar los entrenadores.')
      this.trainers = response.data.data?.items || []; this.trainersPagination = response.data.meta?.pagination || this.trainersPagination; this.status = 'ready'; return this.trainers
    },
    async updateTrainer(id, payload) { return this.mutate(() => api.patch(`/admin/trainers/${id}`, payload)) },
    async loadPlans(query = {}) {
      this.status = 'loading'; this.error = ''
      const response = await api.get('/admin/membership-plans', { params: query })
      if (!response.ok) return this.fail(response, 'No se pudieron cargar los planes.')
      this.plans = response.data.data?.items || []; this.plansPagination = response.data.meta?.pagination || this.plansPagination; this.status = 'ready'; return this.plans
    },
    async savePlan(payload, id = null) {
      return this.mutate(() => id ? api.patch(`/admin/membership-plans/${id}`, payload) : api.post('/admin/membership-plans', payload))
    },
    async createMembership(payload) { return this.mutate(() => api.post('/admin/memberships', payload)) },
    async transitionMembership(id, payload) { return this.mutate(() => api.patch(`/admin/memberships/${id}/status`, payload)) },
    async upload(file, categoria) {
      const body = new FormData(); body.append('archivo', file); body.append('categoria', categoria)
      return this.mutate(() => api.upload('/admin/files', body))
    },
    async read(endpoint, fallback) {
      const response = await api.get(endpoint)
      if (!response.ok) return this.fail(response, fallback)
      return { ok: true, data: response.data.data }
    },
    async mutate(call, after) {
      this.status = 'saving'; this.error = ''
      const response = await call()
      if (!response.ok || response.data?.error) return this.fail(response, 'No se pudo guardar el cambio.')
      if (after) await after(response.data.data)
      this.status = 'ready'
      return { ok: true, data: response.data.data, meta: response.data.meta || {} }
    },
    fail(response, fallback) {
      this.status = 'error'; this.error = response.data?.mensaje || fallback; this.requestId = response.data?.request_id || ''
      return { ok: false, message: this.error, fields: response.data?.fields || {}, requestId: this.requestId }
    },
    reset() {
      this.gym = null; this.locations = []; this.invitations = []; this.plans = []; this.trainers = []; this.status = 'idle'; this.error = ''; this.requestId = ''
    },
  },
})
