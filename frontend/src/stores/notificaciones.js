import { defineStore } from 'pinia'
import { ref } from 'vue'
import { api } from '../services/api'

function fail(response, fallback) {
  const error = new Error(response.data?.mensaje || fallback)
  error.fields = response.data?.fields || {}
  error.requestId = response.data?.request_id || ''
  throw error
}

function normalizeNotification(item) {
  const readAt = item.read_at || item.leida_en || (item.leida ? item.creado_en || new Date().toISOString() : null)
  return {
    ...item,
    title: item.title || item.titulo || 'Aviso de GymTrack',
    body: item.body || item.cuerpo || item.message || item.mensaje || '',
    created_at: item.created_at || item.creado_en || null,
    read_at: readAt,
  }
}

export const useNotificacionesStore = defineStore('notifications', () => {
  const items = ref([])
  const pagination = ref({ page: 1, per_page: 20, total: 0, total_pages: 1 })
  const preferenceDefaults = { internal_transactional: true, email_transactional: true, internal_marketing: false, email_marketing: false, whatsapp_marketing: false }
  const preferences = ref({ ...preferenceDefaults })
  const status = ref('idle')
  const preferencesStatus = ref('idle')
  const error = ref('')
  const requestId = ref('')
  const unread = ref(0)
  let controller = null

  async function load(page = 1) {
    status.value = 'loading'
    error.value = ''
    controller?.abort()
    controller = new AbortController()
    const response = await api.get('/me/notifications', { params: { page, per_page: 20 }, signal: controller.signal })
    if (response.cancelled) return false
    controller = null
    if (!response.ok || response.data?.error) {
      status.value = 'error'
      error.value = response.data?.mensaje || 'No se pudieron cargar tus notificaciones.'
      requestId.value = response.data?.request_id || ''
      return false
    }
    items.value = (response.data.data?.items || []).map(normalizeNotification)
    pagination.value = response.data.meta?.pagination || pagination.value
    unread.value = Number(response.data.meta?.unread_total ?? response.data.data?.unread_count ?? response.data.data?.unread_total ?? 0)
    status.value = items.value.length ? 'ready' : 'empty'
    return true
  }

  async function markRead(id) {
    const response = await api.patch(`/me/notifications/${id}/read`, {})
    if (!response.ok || response.data?.error) return fail(response, 'No se pudo marcar la notificación.')
    const item = items.value.find((entry) => Number(entry.id) === Number(id))
    const wasUnread = Boolean(item && !item.read_at && !item.leida_en && !item.leida)
    if (item) Object.assign(item, normalizeNotification({ ...item, ...(response.data.data || {}), leida: true, leida_en: response.data.data?.leida_en || new Date().toISOString() }))
    unread.value = Math.max(0, unread.value - (wasUnread ? 1 : 0))
    return response.data.data
  }

  async function markAllRead() {
    const response = await api.post('/me/notifications/read-all', {})
    if (!response.ok || response.data?.error) return fail(response, 'No se pudieron marcar las notificaciones.')
    const at = response.data.data?.read_at || new Date().toISOString()
    items.value.forEach((item) => { item.read_at = item.read_at || at; item.leida_en = item.leida_en || at; item.leida = true })
    unread.value = 0
    return response.data.data
  }

  async function loadPreferences() {
    preferencesStatus.value = 'loading'
    const response = await api.get('/me/notification-preferences')
    if (!response.ok || response.data?.error) {
      preferencesStatus.value = 'error'
      return fail(response, 'No se pudieron cargar tus preferencias.')
    }
    preferences.value = { ...preferences.value, ...(response.data.data || {}) }
    preferencesStatus.value = 'ready'
    return preferences.value
  }

  async function savePreferences(payload) {
    preferencesStatus.value = 'saving'
    const response = await api.put('/me/notification-preferences', payload)
    if (!response.ok || response.data?.error) {
      preferencesStatus.value = 'error'
      return fail(response, 'No se pudieron guardar tus preferencias.')
    }
    preferences.value = { ...preferences.value, ...(response.data.data || payload) }
    preferencesStatus.value = 'ready'
    return preferences.value
  }

  function reset() {
    controller?.abort()
    controller = null
    items.value = []
    unread.value = 0
    pagination.value = { page: 1, per_page: 20, total: 0, total_pages: 1 }
    preferences.value = { ...preferenceDefaults }
    status.value = 'idle'
    preferencesStatus.value = 'idle'
    error.value = ''
  }

  return { items, pagination, preferences, status, preferencesStatus, error, requestId, unread, load, markRead, markAllRead, loadPreferences, savePreferences, reset }
})
