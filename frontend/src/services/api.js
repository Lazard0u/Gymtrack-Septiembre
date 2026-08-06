import axios from 'axios'

let csrfToken = ''

export function setCsrfToken(value = '') {
  csrfToken = typeof value === 'string' ? value : ''
}

const client = axios.create({
  baseURL: '/api',
  headers: { 'Content-Type': 'application/json' },
  timeout: 10000,
  withCredentials: true,
})

client.interceptors.request.use((config) => {
  const method = (config.method || 'get').toLowerCase()
  if (csrfToken && ['post', 'put', 'patch', 'delete'].includes(method)) {
    config.headers['X-CSRF-Token'] = csrfToken
  }
  return config
})

async function request(method, endpoint, body = null, options = {}) {
  try {
    const response = await client.request({ method, url: endpoint, data: body, ...options })
    const nextCsrf = response.data?.csrf_token || response.data?.data?.csrf_token
    if (nextCsrf) setCsrfToken(nextCsrf)
    return { ok: true, status: response.status, data: response.data }
  } catch (error) {
    if (axios.isCancel(error) || error.code === 'ERR_CANCELED') {
      return { ok: false, cancelled: true, status: 0, data: null }
    }
    const response = error.response
    return {
      ok: false,
      status: response?.status || 0,
      data: response?.data || {
        error: true,
        mensaje: response?.status === 429
          ? 'Demasiados intentos. Esperá un momento y volvé a probar.'
          : 'No se pudo conectar con el servidor. Revisá tu conexión e intentá nuevamente.',
      },
    }
  }
}

export const api = {
  post: (endpoint, body, options = {}) => request('POST', endpoint, body, options),
  get: (endpoint, options = {}) => request('GET', endpoint, null, options),
  put: (endpoint, body, options = {}) => request('PUT', endpoint, body, options),
  patch: (endpoint, body, options = {}) => request('PATCH', endpoint, body, options),
  delete: (endpoint, body = null, options = {}) => request('DELETE', endpoint, body, options),
  clearCsrf: () => setCsrfToken(''),
}

export default client
