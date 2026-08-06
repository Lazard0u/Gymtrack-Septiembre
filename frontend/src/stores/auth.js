/**
 * GymTrack · stores/auth.js
 * -------------------------------------------------
 * Store de Pinia que maneja todo el estado de autenticación.
 *
 * Flujo de sesión:
 *  1. login()    → POST /api/auth/login   → guarda usuario + token (session_id PHP)
 *  2. registro() → POST /api/auth/registro → redirige al login
 *  3. logout()   → POST /api/auth/logout  → destruye sesión en servidor y en cliente
 *  4. cargarPerfil() → GET /api/perfil    → rehidrata el usuario al recargar la página
 */

import { defineStore } from 'pinia'
import { ref, computed } from 'vue'
import { api } from '../services/api'

export const useAuthStore = defineStore('auth', () => {
  // ── Estado ──────────────────────────────────────────────
  const user  = ref(null)
  const token = ref(localStorage.getItem('token') || null)

  // ── Getters ─────────────────────────────────────────────
  const estaAutenticado = computed(() => !!token.value)
  const esAdmin         = computed(() => user.value?.rol_id === 2)
  const esEmpleado      = computed(() => user.value?.rol_id === 3)
  const esDueno         = computed(() => user.value?.rol_id === 4)

  // ── Acciones ────────────────────────────────────────────

  /**
   * Inicia sesión con email y contraseña.
   * El backend devuelve un token que es el PHP session_id.
   * @throws {Error} si las credenciales son incorrectas
   */
  async function login(email, password) {
    const { ok, data } = await api.post('/auth/login', { email, password })

    if (!ok || data.error) {
      throw new Error(data.mensaje || 'Error al iniciar sesión.')
    }

    user.value  = data.usuario
    token.value = data.token
    localStorage.setItem('token', data.token)

    return data
  }

  /**
   * Registra un nuevo socio.
   * @throws {Error} si el email ya existe o los datos son inválidos
   */
  async function registro(nombre, email, password, turnstileToken = '') {
    const { ok, data } = await api.post('/auth/registro', {
      nombre,
      email,
      password,
      turnstileToken,
    })

    if (!ok || data.error) {
      throw new Error(data.mensaje || 'Error al crear la cuenta.')
    }

    return data
  }

  /**
   * Cierra la sesión tanto en el servidor PHP como en el cliente.
   */
  async function logout() {
    try {
      // Destruimos la sesión en el servidor (importante para seguridad)
      await api.postAuth('/auth/logout', {})
    } finally {
      // Siempre limpiamos el estado local, aunque el servidor falle
      user.value  = null
      token.value = null
      localStorage.removeItem('token')
    }
  }

  function limpiarSesionLocal() {
    user.value = null
    token.value = null
    localStorage.removeItem('token')
  }

  /**
   * Carga el perfil del usuario autenticado desde el servidor.
   * Se llama al montar el Dashboard para rehidratar el usuario
   * si la página fue recargada (Pinia pierde el estado en memoria).
   */
  async function cargarPerfil() {
    if (!token.value) return false

    const { ok, status, data } = await api.get('/perfil')
    if (ok && !data.error) {
      user.value = data.usuario
      return true
    }

    if (status === 401 || status === 403) limpiarSesionLocal()
    return false
  }

  return {
    // Estado
    user,
    token,
    // Getters
    estaAutenticado,
    esAdmin,
    esEmpleado,
    esDueno,
    // Acciones
    login,
    registro,
    logout,
    cargarPerfil,
    limpiarSesionLocal,
  }
})
