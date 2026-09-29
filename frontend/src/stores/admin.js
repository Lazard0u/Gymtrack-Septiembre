import { defineStore } from 'pinia'
import { computed, ref } from 'vue'
import { api } from '../services/api'
import { useAuthStore } from './auth'
import { resetAdminDomainStores } from './adminResources'
import { useAdminManagementStore } from './adminManagement'
import { useNotificacionesStore } from './notificaciones'
import { usePromocionesStore } from './promociones'
import { usePagosStore } from './pagos'
import { useAgendaStore } from './agenda'

export const useAdminStore = defineStore('administration', () => {
  const status = ref('idle')
  const gyms = ref([])
  const activeGymId = ref(null)
  const permissions = ref([])
  const effectiveRole = ref(null)
  const globalRole = ref(null)
  const supportMode = ref(false)
  const supportReason = ref('')
  const isDemo = ref(false)
  const error = ref('')
  const requestId = ref('')
  const version = ref(0)
  const summary = ref({ widgets: [], alerts: [] })
  const summaryStatus = ref('idle')
  const summaryError = ref('')
  let contextController = null
  let summaryController = null

  const activeGym = computed(() => gyms.value.find((gym) => gym.gimnasio_id === activeGymId.value) || null)
  const hasContext = computed(() => activeGymId.value !== null)
  const isGlobalAdmin = computed(() => globalRole.value === 'admin_general')

  async function loadContext() {
    contextController?.abort()
    contextController = new AbortController()
    status.value = 'loading'
    error.value = ''
    const response = await api.get('/admin/context', { signal: contextController.signal })
    if (response.cancelled) return false
    contextController = null
    if (!response.ok || response.data?.error) {
      status.value = 'error'
      error.value = response.data?.mensaje || 'No se pudo cargar el contexto administrativo.'
      requestId.value = response.data?.request_id || ''
      return false
    }
    const data = response.data.data
    gyms.value = data.gyms || []
    activeGymId.value = data.active_gym_id || null
    permissions.value = data.permissions || []
    effectiveRole.value = data.effective_role
    globalRole.value = data.global_role
    supportMode.value = Boolean(data.support_mode)
    supportReason.value = data.support_reason || ''
    isDemo.value = Boolean(data.is_demo)
    requestId.value = response.data.request_id || ''
    status.value = 'ready'
    return true
  }

  async function selectGym(gymId, reason = '') {
    status.value = 'switching'
    error.value = ''
    const response = await api.post('/admin/context/select', { gym_id: Number(gymId), reason })
    if (!response.ok || response.data?.error) {
      status.value = 'ready'
      const failure = new Error(response.data?.mensaje || 'No se pudo cambiar el gimnasio.')
      failure.fields = response.data?.fields || {}
      failure.requestId = response.data?.request_id || ''
      throw failure
    }
    resetAdminDomainStores()
    useAdminManagementStore().reset()
    useNotificacionesStore().reset()
    usePromocionesStore().reset()
    summaryController?.abort()
    summary.value = { widgets: [], alerts: [] }
    const auth = useAuthStore()
    auth.applyExternalSession(response.data.data.usuario)
    activeGymId.value = Number(gymId)
    permissions.value = response.data.data.usuario?.permissions || []
    effectiveRole.value = response.data.data.usuario?.role || effectiveRole.value
    supportMode.value = Boolean(response.data.data.support_mode)
    supportReason.value = response.data.data.support_reason || ''
    version.value += 1
    status.value = 'ready'
    return true
  }

  async function loadSummary() {
    if (!hasContext.value) return
    summaryController?.abort()
    summaryController = new AbortController()
    summaryStatus.value = 'loading'
    summaryError.value = ''
    const response = await api.get('/admin/summary', { signal: summaryController.signal })
    if (response.cancelled) return
    summaryController = null
    if (!response.ok || response.data?.error) {
      summaryStatus.value = 'error'
      summaryError.value = response.data?.mensaje || 'No se pudo cargar el resumen.'
      requestId.value = response.data?.request_id || ''
      return
    }
    summary.value = response.data.data
    requestId.value = response.data.request_id || ''
    summaryStatus.value = 'ready'
  }

  function hasPermission(permission) {
    return permissions.value.includes(permission)
  }

  function reset() {
    contextController?.abort()
    contextController = null
    summaryController?.abort()
    summaryController = null
    status.value = 'idle'
    gyms.value = []
    activeGymId.value = null
    permissions.value = []
    effectiveRole.value = null
    globalRole.value = null
    supportMode.value = false
    supportReason.value = ''
    isDemo.value = false
    error.value = ''
    requestId.value = ''
    summary.value = { widgets: [], alerts: [] }
    summaryStatus.value = 'idle'
    summaryError.value = ''
  }

  return { status, gyms, activeGymId, activeGym, hasContext, permissions, effectiveRole, globalRole, isGlobalAdmin, supportMode, supportReason, isDemo, error, requestId, version, summary, summaryStatus, summaryError, loadContext, selectGym, loadSummary, hasPermission, reset }
})

// se llama al hacer logout, junta el reset de todos los stores que dependen
// del gimnasio activo (si no, queda pegado el estado del usuario anterior)
export function resetAdminStores() {
  useAdminStore().reset()
  resetAdminDomainStores()
  useAdminManagementStore().reset()
  useNotificacionesStore().reset()
  usePromocionesStore().reset()
  usePagosStore().reset()
  useAgendaStore().reset()
}
