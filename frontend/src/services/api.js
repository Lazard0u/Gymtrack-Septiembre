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

async function request(method, endpoint, body = null) {
  try {
    const response = await client.request({ method, url: endpoint, data: body })
    if (response.data?.csrf_token) setCsrfToken(response.data.csrf_token)
    return { ok: true, status: response.status, data: response.data }
  } catch (error) {
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
  post: (endpoint, body) => request('POST', endpoint, body),
  get: (endpoint) => request('GET', endpoint),
  put: (endpoint, body) => request('PUT', endpoint, body),
  patch: (endpoint, body) => request('PATCH', endpoint, body),
  delete: (endpoint, body = null) => request('DELETE', endpoint, body),
  clearCsrf: () => setCsrfToken(''),
}

export default client
