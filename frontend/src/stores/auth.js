import { defineStore } from 'pinia'
import { computed, ref } from 'vue'
import { api } from '../services/api'

export const useAuthStore = defineStore('auth', () => {
  const user = ref(null)
  const initialized = ref(false)
  const sessionState = ref('unknown')

  const estaAutenticado = computed(() => user.value !== null)
  const esAdmin = computed(() => user.value?.role === 'admin_general')
  const esEmpleado = computed(() => user.value?.role === 'empleado')
  const esDueno = computed(() => user.value?.role === 'dueño')
  const correoVerificado = computed(() => Boolean(user.value?.email_verified))
  const permisos = computed(() => user.value?.permissions || [])

  function applySession(data) {
    user.value = data.usuario
    initialized.value = true
    sessionState.value = 'active'
  }

  async function login(email, password) {
    const response = await api.post('/auth/login', { email, password })
    if (!response.ok || response.data.error) throw apiError(response)
    applySession(response.data)
    return response.data
  }

  async function registro(payload, owner = false) {
    const response = await api.post(owner ? '/auth/registro-dueno' : '/auth/registro', payload)
    if (!response.ok || response.data.error) throw apiError(response)
    return response.data
  }

  async function bootstrap(force = false) {
    if (initialized.value && !force) return estaAutenticado.value
    const response = await api.get('/me')
    initialized.value = true
    if (response.ok && !response.data.error) {
      applySession(response.data)
      return true
    }
    user.value = null
    api.clearCsrf()
    sessionState.value = response.status === 401 ? 'expired' : 'anonymous'
    return false
  }

  async function probe() {
    if (initialized.value) return estaAutenticado.value
    const response = await api.get('/auth/session')
    if (response.ok && response.data?.authenticated) {
      applySession(response.data)
      return true
    }
    initialized.value = true
    user.value = null
    sessionState.value = 'anonymous'
    return false
  }

  async function logout() {
    try { await api.post('/auth/logout', {}) } finally { limpiarSesionLocal('anonymous') }
  }

  async function logoutAll() {
    try { await api.post('/auth/logout-all', {}) } finally { limpiarSesionLocal('anonymous') }
  }

  async function cambiarGimnasio(gymId) {
    const response = await api.post('/me/gym-context', { gym_id: gymId })
    if (!response.ok || response.data.error) throw apiError(response)
    applySession(response.data)
  }

  async function limpiarGimnasio() {
    const response = await api.delete('/me/gym-context')
    if (!response.ok || response.data.error) throw apiError(response)
    applySession(response.data)
  }

  function tienePermiso(permission) { return permisos.value.includes(permission) }

  function limpiarSesionLocal(state = 'expired') {
    user.value = null
    initialized.value = true
    sessionState.value = state
    api.clearCsrf()
  }

  function apiError(response) {
    const error = new Error(response.data?.mensaje || 'No se pudo completar la operación.')
    error.status = response.status
    error.fields = response.data?.fields || {}
    error.code = response.data?.codigo || null
    return error
  }

  return { user, initialized, sessionState, estaAutenticado, esAdmin, esEmpleado, esDueno, correoVerificado, permisos,
    login, registro, bootstrap, probe, cargarPerfil: () => bootstrap(true), logout, logoutAll, cambiarGimnasio, limpiarGimnasio, tienePermiso, limpiarSesionLocal }
})
