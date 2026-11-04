import { beforeEach, describe, expect, it, vi } from 'vitest'
import { createPinia, setActivePinia } from 'pinia'
import { api } from '../../services/api'
import { useAuthStore } from '../auth'

vi.mock('../../services/api', () => ({
  api: { get: vi.fn(), post: vi.fn(), delete: vi.fn(), clearCsrf: vi.fn() },
}))

const duenoActivo = { id: 7, role: 'dueño', email_verified: true, permissions: ['members.read', 'reports.export'] }

describe('auth store', () => {
  beforeEach(() => { setActivePinia(createPinia()); vi.clearAllMocks() })

  it('login guarda el usuario y deriva rol, correo verificado y permisos', async () => {
    api.post.mockResolvedValue({ ok: true, data: { error: false, usuario: duenoActivo } })
    const store = useAuthStore()
    await store.login('dueno@ejemplo.test', 'clave-de-prueba')
    expect(api.post).toHaveBeenCalledWith('/auth/login', { email: 'dueno@ejemplo.test', password: 'clave-de-prueba' })
    expect(store.estaAutenticado).toBe(true)
    expect(store.esDueno).toBe(true)
    expect(store.esAdmin).toBe(false)
    expect(store.correoVerificado).toBe(true)
    expect(store.tienePermiso('reports.export')).toBe(true)
    expect(store.tienePermiso('gym.configure')).toBe(false)
    expect(store.sessionState).toBe('active')
  })

  it('un login rechazado lanza un error con estado, campos y código y no deja sesión', async () => {
    api.post.mockResolvedValue({ ok: false, status: 429, data: { mensaje: 'Demasiados intentos.', fields: { email: 'x' }, codigo: 'rate_limited' } })
    const store = useAuthStore()
    await expect(store.login('a@b.test', 'mala')).rejects.toMatchObject({ message: 'Demasiados intentos.', status: 429, code: 'rate_limited', fields: { email: 'x' } })
    expect(store.estaAutenticado).toBe(false)
    expect(store.user).toBeNull()
  })

  it('bootstrap no vuelve a consultar /me salvo que se fuerce', async () => {
    api.get.mockResolvedValue({ ok: true, data: { error: false, usuario: duenoActivo } })
    const store = useAuthStore()
    expect(await store.bootstrap()).toBe(true)
    expect(await store.bootstrap()).toBe(true)
    expect(api.get).toHaveBeenCalledTimes(1)
    await store.bootstrap(true)
    expect(api.get).toHaveBeenCalledTimes(2)
  })

  it('bootstrap con 401 marca la sesión como vencida y borra el CSRF', async () => {
    api.get.mockResolvedValue({ ok: false, status: 401, data: {} })
    const store = useAuthStore()
    expect(await store.bootstrap()).toBe(false)
    expect(store.sessionState).toBe('expired')
    expect(store.user).toBeNull()
    expect(api.clearCsrf).toHaveBeenCalled()
  })

  it('bootstrap con otro error queda anónimo y no vencido', async () => {
    api.get.mockResolvedValue({ ok: false, status: 500, data: {} })
    const store = useAuthStore()
    await store.bootstrap()
    expect(store.sessionState).toBe('anonymous')
  })

  it('probe sin sesión queda anónimo y recuerda que ya consultó', async () => {
    api.get.mockResolvedValue({ ok: true, data: { authenticated: false } })
    const store = useAuthStore()
    expect(await store.probe()).toBe(false)
    expect(await store.probe()).toBe(false)
    expect(api.get).toHaveBeenCalledTimes(1)
    expect(store.sessionState).toBe('anonymous')
  })

  it('logout limpia el estado local y el CSRF aunque la red falle', async () => {
    api.post.mockResolvedValueOnce({ ok: true, data: { error: false, usuario: duenoActivo } })
    const store = useAuthStore()
    await store.login('a@b.test', 'clave')
    api.post.mockRejectedValueOnce(new Error('sin red'))
    await expect(store.logout()).rejects.toThrow('sin red')
    expect(store.user).toBeNull()
    expect(store.sessionState).toBe('anonymous')
    expect(api.clearCsrf).toHaveBeenCalled()
  })

  it('cambiarGimnasio reemplaza la sesión por la que devuelve el backend', async () => {
    api.post.mockResolvedValueOnce({ ok: true, data: { error: false, usuario: duenoActivo } })
    const store = useAuthStore()
    await store.login('a@b.test', 'clave')
    api.post.mockResolvedValueOnce({ ok: true, data: { error: false, usuario: { ...duenoActivo, role: 'empleado', permissions: ['members.read'] } } })
    await store.cambiarGimnasio(3)
    expect(api.post).toHaveBeenLastCalledWith('/me/gym-context', { gym_id: 3 })
    expect(store.esEmpleado).toBe(true)
    expect(store.tienePermiso('reports.export')).toBe(false)
  })

  it('cambiarGimnasio rechazado conserva la sesión anterior', async () => {
    api.post.mockResolvedValueOnce({ ok: true, data: { error: false, usuario: duenoActivo } })
    const store = useAuthStore()
    await store.login('a@b.test', 'clave')
    api.post.mockResolvedValueOnce({ ok: false, status: 403, data: { mensaje: 'No tenés acceso a ese gimnasio.' } })
    await expect(store.cambiarGimnasio(99)).rejects.toThrow('No tenés acceso a ese gimnasio.')
    expect(store.esDueno).toBe(true)
  })

  it('registro usa el endpoint de dueño solo cuando se pide', async () => {
    api.post.mockResolvedValue({ ok: true, data: { error: false } })
    const store = useAuthStore()
    await store.registro({ email: 'a@b.test' })
    expect(api.post).toHaveBeenLastCalledWith('/auth/registro', { email: 'a@b.test' })
    await store.registro({ email: 'a@b.test' }, true)
    expect(api.post).toHaveBeenLastCalledWith('/auth/registro-dueno', { email: 'a@b.test' })
  })
})
