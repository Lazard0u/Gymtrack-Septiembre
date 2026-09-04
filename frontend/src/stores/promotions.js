/**
 * Store Pinia de promotions. Centraliza estado reactivo, llamadas a la API y errores para que las vistas compartan una única fuente de datos.
 * Los imports declaran dependencias; funciones y estados documentan el recorrido de los datos y sus fallos esperables.
 */
import { defineStore } from 'pinia'
import { ref } from 'vue'
import { api } from '../services/api'

function failure(response, fallback) {
  const error = new Error(response.data?.mensaje || fallback)
  error.fields = response.data?.fields || {}
  error.requestId = response.data?.request_id || ''
  throw error
}

export const usePromotionsStore = defineStore('promotions', () => {
  const items = ref([])
  const pagination = ref({ page: 1, per_page: 20, total: 0, total_pages: 1 })
  const selected = ref(null)
  const results = ref(null)
  const resultsStatus = ref('idle')
  const listStatus = ref('idle')
  const detailStatus = ref('idle')
  const working = ref(false)
  const error = ref('')
  const requestId = ref('')
  let controller = null

  async function load(query = {}) {
    controller?.abort()
    controller = new AbortController()
    listStatus.value = 'loading'
    error.value = ''
    const response = await api.get('/admin/promotions', { params: query, signal: controller.signal })
    if (response.cancelled) return false
    controller = null
    if (!response.ok || response.data?.error) {
      listStatus.value = 'error'
      error.value = response.data?.mensaje || 'No se pudieron cargar las promociones.'
      requestId.value = response.data?.request_id || ''
      return false
    }
    items.value = response.data.data?.items || []
    pagination.value = response.data.meta?.pagination || pagination.value
    listStatus.value = items.value.length ? 'ready' : 'empty'
    return true
  }

  async function detail(id) {
    detailStatus.value = 'loading'
    const response = await api.get(`/admin/promotions/${id}`)
    if (!response.ok || response.data?.error) {
      detailStatus.value = 'error'
      return failure(response, 'No se pudo cargar la promoción.')
    }
    selected.value = response.data.data
    detailStatus.value = 'ready'
    return selected.value
  }

  async function save(payload, id = null) {
    working.value = true
    const response = id ? await api.patch(`/admin/promotions/${id}`, payload) : await api.post('/admin/promotions', payload)
    if (!response.ok || response.data?.error) {
      working.value = false
      return failure(response, 'No se pudo guardar la promoción.')
    }
    selected.value = response.data.data
    working.value = false
    return selected.value
  }

  async function transition(id, action, payload = {}) {
    const allowed = ['schedule', 'pause', 'finish']
    if (!allowed.includes(action)) throw new Error('Transición de promoción no permitida.')
    working.value = true
    const response = await api.post(`/admin/promotions/${id}/${action}`, payload)
    if (!response.ok || response.data?.error) {
      working.value = false
      return failure(response, 'No se pudo actualizar el estado de la promoción.')
    }
    selected.value = response.data.data
    working.value = false
    return selected.value
  }

  async function loadResults(id) {
    resultsStatus.value = 'loading'
    const response = await api.get(`/admin/promotions/${id}/results`)
    if (!response.ok || response.data?.error) { resultsStatus.value = 'error'; return failure(response, 'No se pudieron cargar los resultados.') }
    results.value = response.data.data
    resultsStatus.value = 'ready'
    return results.value
  }

  function reset() {
    controller?.abort()
    controller = null
    items.value = []
    selected.value = null
    results.value = null
    resultsStatus.value = 'idle'
    pagination.value = { page: 1, per_page: 20, total: 0, total_pages: 1 }
    listStatus.value = 'idle'
    detailStatus.value = 'idle'
    working.value = false
    error.value = ''
  }

  return { items, pagination, selected, results, listStatus, detailStatus, resultsStatus, working, error, requestId, load, detail, save, transition, loadResults, reset }
})
