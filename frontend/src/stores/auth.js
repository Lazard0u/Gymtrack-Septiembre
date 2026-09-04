/**
 * Store Pinia de autenticación.
 * Mantiene en memoria la identidad devuelta por PHP; nunca guarda contraseñas,
 * tokens de sesión ni datos de acceso en almacenamiento persistente del navegador.
 */
import { defineStore } from 'pinia'
import { computed, ref } from 'vue'
import { api } from '../services/api'

export const useAuthStore = defineStore('auth', () => {
  // user=null significa sesión anónima; initialized evita repetir consultas iniciales.
  const user = ref(null)
  const initialized = ref(false)
  const sessionState = ref('unknown')

  // Las vistas consumen estas propiedades derivadas sin repetir reglas de roles.
  const estaAutenticado = computed(() => user.value !== null)
  const esAdmin = computed(() => user.value?.role === 'admin_general')
  const esEmpleado = computed(() => user.value?.role === 'empleado')
  const esDueno = computed(() => user.value?.role === 'dueño')
  const correoVerificado = computed(() => Boolean(user.value?.email_verified))
  const permisos = computed(() => user.value?.permissions || [])

  /** Aplica una respuesta de login o /me y marca la sesión como activa. */
  function applySession(data) {
    user.value = data.usuario
    initialized.value = true
    sessionState.value = 'active'
  }

  /** Permite a integraciones internas aplicar un usuario ya validado por PHP. */
  function applyExternalSession(usuario) {
    if (!usuario) return
    user.value = usuario
    initialized.value = true
    sessionState.value = 'active'
  }

  /** PHP valida la clave y responde con usuario; la cookie llega automáticamente. */
  async function login(email, password) {
    const response = await api.post('/auth/login', { email, password })
    if (!response.ok || response.data.error) throw apiError(response)
    applySession(response.data)
    return response.data
  }

  /** Registra socio o solicitud de dueño, según el endpoint elegido. */
  async function registro(payload, owner = false) {
    const endpoint = owner ? '/auth/registro-dueno' : '/auth/registro'
    const response = await api.post(endpoint, payload)
    if (!response.ok || response.data.error) throw apiError(response)
    return response.data
  }

  /**
   * Recupera el contexto completo. force=true se usa después de verificar correo
   * para reemplazar inmediatamente el estado email_verified de la sesión.
   */
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

  /** Consulta liviana para rutas públicas que admiten una sesión opcional. */
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

  /** Aunque la red falle, el navegador descarta inmediatamente el estado local. */
  async function logout() {
    try {
      await api.post('/auth/logout', {})
    } finally {
      limpiarSesionLocal('anonymous')
    }
  }

  /** Revoca todas las sesiones de la cuenta desde PHP. */
  async function logoutAll() {
    try {
      await api.post('/auth/logout-all', {})
    } finally {
      limpiarSesionLocal('anonymous')
    }
  }

  /** Cambia el gimnasio activo; el backend recalcula rol y permisos efectivos. */
  async function cambiarGimnasio(gymId) {
    const response = await api.post('/me/gym-context', { gym_id: gymId })
    if (!response.ok || response.data.error) throw apiError(response)
    applySession(response.data)
  }

  /** Quita el contexto tenant, operación utilizada principalmente por admin general. */
  async function limpiarGimnasio() {
    const response = await api.delete('/me/gym-context')
    if (!response.ok || response.data.error) throw apiError(response)
    applySession(response.data)
  }

  function tienePermiso(permission) {
    return permisos.value.includes(permission)
  }

  /** Limpia también CSRF para que no pueda reutilizarse después de cerrar sesión. */
  function limpiarSesionLocal(state = 'expired') {
    user.value = null
    initialized.value = true
    sessionState.value = state
    api.clearCsrf()
  }

  /** Convierte el contrato JSON del backend en una excepción útil para formularios. */
  function apiError(response) {
    const error = new Error(response.data?.mensaje || 'No se pudo completar la operación.')
    error.status = response.status
    error.fields = response.data?.fields || {}
    error.code = response.data?.codigo || null
    return error
  }

  return {
    user,
    initialized,
    sessionState,
    estaAutenticado,
    esAdmin,
    esEmpleado,
    esDueno,
    correoVerificado,
    permisos,
    login,
    registro,
    bootstrap,
    probe,
    cargarPerfil: () => bootstrap(true),
    logout,
    logoutAll,
    cambiarGimnasio,
    limpiarGimnasio,
    tienePermiso,
    limpiarSesionLocal,
    applyExternalSession,
  }
})
