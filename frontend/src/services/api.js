/**
 * Cliente de servicios de api. Encapsula la comunicación HTTP, credenciales de sesión y respuestas de error del backend.
 * Los imports declaran dependencias; funciones y estados documentan el recorrido de los datos y sus fallos esperables.
 */
import axios from 'axios'

// CSRF vive sólo en memoria: al recargar se solicita nuevamente junto con la sesión.
let csrfToken = ''

/** Actualiza el token entregado por login, /me o un cambio de contexto. */
export function setCsrfToken(value = '') {
  csrfToken = typeof value === 'string' ? value : ''
}

// withCredentials permite que el navegador envíe la cookie HttpOnly sin leerla.
const client = axios.create({
  baseURL: '/api',
  headers: { 'Content-Type': 'application/json' },
  timeout: 10000,
  withCredentials: true,
})

// Toda mutación recibe CSRF automáticamente; las lecturas GET no lo necesitan.
client.interceptors.request.use((config) => {
  const method = (config.method || 'get').toLowerCase()
  if (csrfToken && ['post', 'put', 'patch', 'delete'].includes(method)) {
    config.headers['X-CSRF-Token'] = csrfToken
  }
  return config
})

/**
 * Normaliza éxito, error HTTP, cancelación y fallo de red en una misma forma.
 * Los stores pueden mostrar mensajes sin depender de detalles propios de Axios.
 */
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

/** Descarga binarios y, si PHP devuelve JSON de error, recupera su mensaje. */
async function download(endpoint, options = {}) {
  try {
    const response = await client.request({ method: 'GET', url: endpoint, responseType: 'blob', ...options })
    return { ok: true, status: response.status, data: response.data, headers: response.headers }
  } catch (error) {
    if (axios.isCancel(error) || error.code === 'ERR_CANCELED') return { ok: false, cancelled: true, status: 0, data: null }
    const response = error.response
    let message = 'No se pudo descargar el archivo. Intentá nuevamente.'
    if (response?.data instanceof Blob) {
      try { message = JSON.parse(await response.data.text())?.mensaje || message } catch { /* respuesta no JSON */ }
    }
    return { ok: false, status: response?.status || 0, data: { error: true, mensaje: message } }
  }
}

// Fachada pequeña utilizada por todos los stores y vistas.
export const api = {
  post: (endpoint, body, options = {}) => request('POST', endpoint, body, options),
  get: (endpoint, options = {}) => request('GET', endpoint, null, options),
  put: (endpoint, body, options = {}) => request('PUT', endpoint, body, options),
  patch: (endpoint, body, options = {}) => request('PATCH', endpoint, body, options),
  delete: (endpoint, body = null, options = {}) => request('DELETE', endpoint, body, options),
  upload: (endpoint, body, options = {}) => request('POST', endpoint, body, { ...options, headers: { ...(options.headers || {}), 'Content-Type': 'multipart/form-data' } }),
  download,
  clearCsrf: () => setCsrfToken(''),
}

export default client
