import { defineStore } from 'pinia'
import { computed, ref } from 'vue'
import { api } from '../services/api'

function failure(response, fallback) {
  const error = new Error(response.data?.mensaje || fallback)
  error.fields = response.data?.fields || {}
  error.requestId = response.data?.request_id || ''
  throw error
}

function queryString(filters = {}) {
  const params = new URLSearchParams()
  Object.entries(filters).forEach(([key, value]) => {
    if (value !== '' && value !== null && value !== undefined) params.set(key, String(value))
  })
  return params.toString()
}

export const useScheduleStore = defineStore('schedule', () => {
  const sessions = ref([])
  const pagination = ref({ page: 1, total: 0, total_pages: 1, per_page: 20 })
  const range = ref({ from: '', to: '' })
  const options = ref({ locations: [], trainers: [], members: [], classes: [] })
  const roster = ref({ session: null, items: [] })
  const bookings = ref([])
  const status = ref('idle')
  const optionsStatus = ref('idle')
  const rosterStatus = ref('idle')
  const bookingsStatus = ref('idle')
  const rosterError = ref('')
  const bookingsError = ref('')
  const error = ref('')
  const requestId = ref('')
  let controller = null

  const totals = computed(() => sessions.value.reduce((result, session) => {
    result.confirmed += Number(session.cupos_reservados || 0)
    result.available += Math.max(0, Number(session.cupos_disponibles || 0))
    result.waiting += Number(session.espera_total || 0)
    return result
  }, { confirmed: 0, available: 0, waiting: 0 }))

  async function loadAdmin(filters = {}) {
    controller?.abort()
    controller = new AbortController()
    status.value = 'loading'
    error.value = ''
    const response = await api.get(`/admin/schedule?${queryString(filters)}`, { signal: controller.signal })
    if (response.cancelled) return
    controller = null
    if (!response.ok || response.data?.error) {
      status.value = 'error'
      error.value = response.data?.mensaje || 'No se pudo cargar la agenda.'
      requestId.value = response.data?.request_id || ''
      return
    }
    sessions.value = response.data.data?.items || []
    pagination.value = response.data.meta?.pagination || pagination.value
    range.value = response.data.data?.range || range.value
    requestId.value = response.data.request_id || ''
    status.value = 'ready'
  }

  async function loadMember(filters = {}) {
    controller?.abort()
    controller = new AbortController()
    status.value = 'loading'
    error.value = ''
    const response = await api.get(`/class-sessions?${queryString(filters)}`, { signal: controller.signal })
    if (response.cancelled) return
    controller = null
    if (!response.ok || response.data?.error) {
      status.value = 'error'
      error.value = response.data?.mensaje || 'No se pudo cargar la agenda.'
      requestId.value = response.data?.request_id || ''
      return
    }
    sessions.value = response.data.data?.items || []
    pagination.value = response.data.meta?.pagination || pagination.value
    range.value = response.data.data?.range || range.value
    status.value = 'ready'
  }

  async function loadOptions() {
    optionsStatus.value = 'loading'
    const response = await api.get('/admin/schedule/options')
    if (!response.ok || response.data?.error) {
      optionsStatus.value = 'error'
      return failure(response, 'No se pudieron cargar las opciones de la agenda.')
    }
    options.value = response.data.data || options.value
    optionsStatus.value = 'ready'
  }

  async function createClass(payload) {
    const response = await api.post('/admin/class-definitions', payload)
    if (!response.ok || response.data?.error) return failure(response, 'No se pudo crear la clase.')
    return response.data.data
  }

  async function updateSession(id, payload) {
    const response = await api.patch(`/admin/class-sessions/${id}`, payload)
    if (!response.ok || response.data?.error) return failure(response, 'No se pudo actualizar la sesión.')
    return response.data.data
  }

  async function cancelSession(id, reason) {
    const response = await api.post(`/admin/class-sessions/${id}/cancel`, { motivo: reason })
    if (!response.ok || response.data?.error) return failure(response, 'No se pudo cancelar la sesión.')
    return response.data.data
  }

  async function loadRoster(sessionId) {
    rosterStatus.value = 'loading'
    rosterError.value = ''
    roster.value = { session: null, items: [] }
    const response = await api.get(`/admin/class-sessions/${sessionId}/roster`)
    if (!response.ok || response.data?.error) {
      rosterStatus.value = 'error'
      rosterError.value = response.data?.mensaje || 'No se pudo cargar la lista de la sesión.'
      return failure(response, 'No se pudo cargar la lista de la sesión.')
    }
    roster.value = response.data.data || { session: null, items: [] }
    rosterStatus.value = 'ready'
  }

  async function adminBook(sessionId, memberId) {
    const response = await api.post(`/admin/class-sessions/${sessionId}/bookings`, { usuario_id: Number(memberId) }, { headers: { 'Idempotency-Key': crypto.randomUUID() } })
    if (!response.ok || response.data?.error) return failure(response, 'No se pudo registrar la reserva.')
    return response.data.data
  }

  async function adminCancel(bookingId, reason) {
    const response = await api.delete(`/admin/bookings/${bookingId}`, { motivo: reason })
    if (!response.ok || response.data?.error) return failure(response, 'No se pudo cancelar la reserva.')
    return response.data.data
  }

  async function markAttendance(bookingId, attendanceState, notes = '') {
    const response = await api.put(`/admin/bookings/${bookingId}/attendance`, { estado: attendanceState, notas: notes })
    if (!response.ok || response.data?.error) return failure(response, 'No se pudo registrar la asistencia.')
    return response.data.data
  }

  async function loadMyBookings() {
    bookingsStatus.value = 'loading'
    bookingsError.value = ''
    const response = await api.get('/bookings/mine')
    if (!response.ok || response.data?.error) {
      bookingsStatus.value = 'error'
      bookingsError.value = response.data?.mensaje || 'No se pudieron cargar tus reservas.'
      return failure(response, 'No se pudieron cargar tus reservas.')
    }
    bookings.value = response.data.data?.items || []
    bookingsStatus.value = 'ready'
  }

  async function memberBook(sessionId) {
    const response = await api.post(`/class-sessions/${sessionId}/book`, {}, { headers: { 'Idempotency-Key': crypto.randomUUID() } })
    if (!response.ok || response.data?.error) return failure(response, 'No se pudo completar la reserva.')
    return response.data.data
  }

  async function memberCancel(bookingId) {
    const response = await api.delete(`/bookings/${bookingId}`, { motivo: 'Cancelada por el socio' })
    if (!response.ok || response.data?.error) return failure(response, 'No se pudo cancelar la reserva.')
    return response.data.data
  }

  function reset() {
    controller?.abort()
    sessions.value = []
    roster.value = { session: null, items: [] }
    bookings.value = []
    status.value = 'idle'
  }

  return {
    sessions, pagination, range, options, roster, bookings, status, optionsStatus, rosterStatus,
    bookingsStatus, rosterError, bookingsError, error, requestId, totals, loadAdmin, loadMember, loadOptions, createClass,
    updateSession, cancelSession, loadRoster, adminBook, adminCancel, markAttendance,
    loadMyBookings, memberBook, memberCancel, reset,
  }
})
