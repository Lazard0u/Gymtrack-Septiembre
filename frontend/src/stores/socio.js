import { defineStore } from 'pinia'
import { ref } from 'vue'
import { api } from '../services/api'

function failure(response, fallback) {
  const error = new Error(response.data?.mensaje || fallback)
  error.fields = response.data?.fields || {}
  error.code = response.data?.codigo || ''
  error.requestId = response.data?.request_id || ''
  throw error
}

function pagination() {
  return { page: 1, per_page: 20, total: 0, total_pages: 1 }
}

export const useSocioStore = defineStore('member', () => {
  const summary = ref(null)
  const profile = ref(null)
  const preferences = ref({ monthly_attendance_goal: 8, show_orientation_bmi: true, preferred_schedules: [] })
  const favorites = ref({ gyms: [], activities: [], available_activities: [] })
  const attendance = ref([])
  const attendancePagination = ref(pagination())
  const memberships = ref([])
  const measurements = ref([])
  const measurementsPagination = ref(pagination())
  const medicalNotice = ref('')
  const card = ref(null)

  const summaryStatus = ref('idle')
  const profileStatus = ref('idle')
  const preferencesStatus = ref('idle')
  const favoritesStatus = ref('idle')
  const attendanceStatus = ref('idle')
  const membershipsStatus = ref('idle')
  const measurementsStatus = ref('idle')
  const cardStatus = ref('idle')
  const working = ref('')
  const error = ref('')
  const requestId = ref('')
  let controller = null

  function reject(response, fallback, statusRef) {
    statusRef.value = 'error'
    error.value = response.data?.mensaje || fallback
    requestId.value = response.data?.request_id || ''
    return false
  }

  async function loadSummary() {
    summaryStatus.value = 'loading'
    const response = await api.get('/member/summary')
    if (!response.ok || response.data?.error) return reject(response, 'No se pudo cargar tu resumen.', summaryStatus)
    summary.value = response.data.data || null
    summaryStatus.value = 'ready'
    return true
  }

  async function loadProfile() {
    profileStatus.value = 'loading'
    const response = await api.get('/member/profile')
    if (!response.ok || response.data?.error) return reject(response, 'No se pudo cargar tu perfil.', profileStatus)
    profile.value = response.data.data || null
    profileStatus.value = 'ready'
    return true
  }

  async function saveProfile(payload) {
    working.value = 'profile'
    const response = await api.patch('/member/profile', payload)
    working.value = ''
    if (!response.ok || response.data?.error) return failure(response, 'No se pudo guardar tu perfil.')
    profile.value = response.data.data
    return profile.value
  }

  async function uploadPhoto(file) {
    const body = new FormData()
    body.append('photo', file)
    working.value = 'photo'
    const response = await api.upload('/member/profile/photo', body)
    working.value = ''
    if (!response.ok || response.data?.error) return failure(response, 'No se pudo guardar la foto.')
    if (profile.value) profile.value.avatar_url = `${response.data.data?.avatar_url || '/api/member/avatar'}?v=${Date.now()}`
    return response.data.data
  }

  async function loadPreferences() {
    preferencesStatus.value = 'loading'
    const response = await api.get('/member/preferences')
    if (!response.ok || response.data?.error) return reject(response, 'No se pudieron cargar tus preferencias.', preferencesStatus)
    preferences.value = { ...preferences.value, ...(response.data.data || {}) }
    preferencesStatus.value = 'ready'
    return true
  }

  async function savePreferences(payload) {
    working.value = 'preferences'
    const response = await api.put('/member/preferences', payload)
    working.value = ''
    if (!response.ok || response.data?.error) return failure(response, 'No se pudieron guardar tus preferencias.')
    preferences.value = { ...preferences.value, ...(response.data.data || {}) }
    return preferences.value
  }

  async function loadFavorites() {
    favoritesStatus.value = 'loading'
    const response = await api.get('/member/favorites')
    if (!response.ok || response.data?.error) return reject(response, 'No se pudieron cargar tus favoritos.', favoritesStatus)
    favorites.value = { gyms: [], activities: [], available_activities: [], ...(response.data.data || {}) }
    favoritesStatus.value = 'ready'
    return true
  }

  async function setFavorite(type, targetId, favorite) {
    const key = `favorite-${type}-${targetId}`
    working.value = key
    const response = favorite
      ? await api.post('/member/favorites', { type, target_id: Number(targetId) })
      : await api.delete(`/member/favorites/${type}/${targetId}`)
    working.value = ''
    if (!response.ok || response.data?.error) return failure(response, 'No se pudo actualizar el favorito.')
    await loadFavorites()
    return response.data.data
  }

  async function loadAttendance(page = 1) {
    controller?.abort()
    controller = new AbortController()
    attendanceStatus.value = 'loading'
    const response = await api.get('/member/attendance', { params: { page, per_page: 20 }, signal: controller.signal })
    if (response.cancelled) return false
    controller = null
    if (!response.ok || response.data?.error) return reject(response, 'No se pudo cargar tu asistencia.', attendanceStatus)
    attendance.value = response.data.data?.items || []
    attendancePagination.value = response.data.meta?.pagination || pagination()
    attendanceStatus.value = attendance.value.length ? 'ready' : 'empty'
    return true
  }

  async function loadMemberships() {
    membershipsStatus.value = 'loading'
    const response = await api.get('/member/memberships')
    if (!response.ok || response.data?.error) return reject(response, 'No se pudieron cargar tus membresías.', membershipsStatus)
    memberships.value = response.data.data?.items || []
    membershipsStatus.value = memberships.value.length ? 'ready' : 'empty'
    return true
  }

  async function loadMeasurements(page = 1) {
    measurementsStatus.value = 'loading'
    const response = await api.get('/member/measurements', { params: { page, per_page: 20 } })
    if (!response.ok || response.data?.error) return reject(response, 'No se pudieron cargar tus mediciones.', measurementsStatus)
    measurements.value = response.data.data?.items || []
    medicalNotice.value = response.data.data?.medical_notice || ''
    measurementsPagination.value = response.data.meta?.pagination || pagination()
    measurementsStatus.value = measurements.value.length ? 'ready' : 'empty'
    return true
  }

  async function createMeasurement(payload) {
    working.value = 'measurement'
    const response = await api.post('/member/measurements', payload, { headers: { 'Idempotency-Key': crypto.randomUUID() } })
    working.value = ''
    if (!response.ok || response.data?.error) return failure(response, 'No se pudo guardar la medición.')
    await loadMeasurements(1)
    await loadSummary()
    return response.data.data
  }

  async function loadCard() {
    cardStatus.value = 'loading'
    const response = await api.get('/member/card')
    if (!response.ok || response.data?.error) return reject(response, 'No se pudo generar tu carné.', cardStatus)
    card.value = response.data.data || null
    cardStatus.value = 'ready'
    return true
  }

  function reset() {
    controller?.abort()
    controller = null
    summary.value = null
    profile.value = null
    preferences.value = { monthly_attendance_goal: 8, show_orientation_bmi: true, preferred_schedules: [] }
    favorites.value = { gyms: [], activities: [], available_activities: [] }
    attendance.value = []
    memberships.value = []
    measurements.value = []
    card.value = null
    attendancePagination.value = pagination()
    measurementsPagination.value = pagination()
    medicalNotice.value = ''
    summaryStatus.value = 'idle'
    profileStatus.value = 'idle'
    preferencesStatus.value = 'idle'
    favoritesStatus.value = 'idle'
    attendanceStatus.value = 'idle'
    membershipsStatus.value = 'idle'
    measurementsStatus.value = 'idle'
    cardStatus.value = 'idle'
    working.value = ''
    error.value = ''
    requestId.value = ''
  }

  return {
    summary, profile, preferences, favorites, attendance, attendancePagination, memberships, measurements,
    measurementsPagination, medicalNotice, card, summaryStatus, profileStatus, preferencesStatus,
    favoritesStatus, attendanceStatus, membershipsStatus, measurementsStatus, cardStatus, working, error,
    requestId, loadSummary, loadProfile, saveProfile, uploadPhoto, loadPreferences, savePreferences,
    loadFavorites, setFavorite, loadAttendance, loadMemberships, loadMeasurements, createMeasurement,
    loadCard, reset,
  }
})
